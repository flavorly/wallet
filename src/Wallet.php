<?php

namespace Flavorly\Wallet;

use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Closure;
use Flavorly\Wallet\Contracts\WalletContract as WalletInterface;
use Flavorly\Wallet\Exceptions\WalletLockedException;

/**
 * A plain and simple wallet API for Laravel & Eloquent Model
 * This will ensure atomic locks across the given class/model
 * & also leverage the cache to reduce the number of queries & race conditions
 */
final class Wallet
{
    /**
     * Stores the configuration for the wallet
     * such as decimals, currency, columns to update etc
     */
    protected ?Configuration $configuration;

    /**
     * Cache service is responsible for caching & locking
     */
    protected ?CacheService $cache;

    /**
     * Math Service is responsible for making calculation
     * Under the hood it uses Brick\Math to perform arbitrary precision calculations
     * The wallet math class is different since it casts all the floats as integers
     */
    protected Math $math;

    /**
     * Temporary cache for the balance as a static variable
     * This is used to avoid multiple queries to the database or cache hits
     * when dealing with the same wallet multiple times in the same request or
     * for a given process lifecycle
     */
    protected int|string|null $localCachedRawBalance = null;

    /**
     * Bootstrap the class & resolve all necessary services
     * We take the current Eloquent model as a parameter
     * that should implement the WalletContract
     */
    public function __construct(public readonly WalletInterface $model)
    {
        $this->configuration = app(Configuration::class, ['model' => $model]);
        $this->cache = app(CacheService::class, ['prefix' => $model->getKey()]);
        $this->math = app(Math::class, ['floatScale' => $this->configuration->getDecimals()]);
    }

    /**
     * API Wrapper for the operation class to credit the wallet
     *
     * @throws WalletLockedException
     * @throws \Throwable
     */
    public function credit(float|int|string $amount, array $meta = [], null|string $endpoint = null, bool $throw = false, null|Closure $after = null): bool
    {
        return $this
            ->operation()
            ->meta($meta)
            ->credit($amount)
            ->throw($throw)
            ->after($after)
            ->endpoint($endpoint)
            ->dispatch()
            ->ok();
    }

    /**
     *  API Wrapper for the operation class to debig the wallet
     *
     * @throws WalletLockedException
     * @throws \Throwable
     */
    public function debit(float|int|string $amount, array $meta = [], null|string $endpoint = null, bool $throw = false, null|Closure $after = null): bool
    {
        return $this
            ->operation()
            ->meta($meta)
            ->debit($amount)
            ->throw($throw)
            ->after($after)
            ->endpoint($endpoint)
            ->dispatch()
            ->ok();
    }

    /**
     * Creates a new operation
     * This is the main entry point to create transaction
     * Wallet class is just an API for the actual underlying operation object
     */
    public function operation(): Operation
    {
        return new Operation($this);
    }

    /**
     * Refresh the balance on database & cache if necessary
     * Also sums all the values from the transactions to get the current balance always calculated
     * This ensures that the balance is always correct based on previous transactions
     *
     * This method also locks before doing any operation to avoid new transactions from
     * being created while the balance is being updated
     *
     * The only exception is when we perform within the transaction itself
     * the cache()->isWithin() will return true if we are currently performing
     * a transaction and so we have already applied a lock inside it.
     */
    protected function refreshBalance(): void
    {
        $closure = function () {
            // Sum all the balance
            $balance = $this->model->transactions()->sum('amount');

            // Cache the balance
            $this->cache->put($balance);

            // Update the balance on database
            // Quietly as we dont need more events on this one.
            $this->model->updateQuietly([
                $this->configuration->getBalanceColumn() => $balance,
            ]);

            // Update the local variable just in case we need to re-use it
            $this->localCachedRawBalance = $balance;
        };

        if ($this->cache->isWithin()) {
            $closure();

            return;
        }

        $this->cache->blockAndWrapInTransaction($closure);
    }

    /**
     * Returns the cache service
     */
    public function cache(): CacheService
    {
        return $this->cache;
    }

    /**
     * Returns the wallet math service that will ensure
     * correct scale & precision for the given wallet or transaction
     */
    public function math(): Math
    {
        return $this->math;
    }

    /**
     * Returns the wallet configuration
     */
    public function configuration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * Returns the current balance as a string/float representation
     * Optional can be the cached balance or the actual balance calculated
     */
    public function balance(bool $cached = true): string
    {
        return $this->math()->intToFloat($this->balanceRaw($cached));
    }

    /**
     * Returns the balance without any formatting or casting
     */
    public function balanceRaw(bool $cached = true): int
    {
        if (! $cached) {
            $this->refreshBalance();
        }

        // If we have a local cached balance, return it
        if (null !== $this->localCachedRawBalance) {
            return $this->localCachedRawBalance;
        }

        if ($this->configuration->getBalance()) {
            $this->localCachedRawBalance = $this->configuration->getBalance();
            $this->cache()->put($this->localCachedRawBalance);
        }

        return (int) $this->localCachedRawBalance;
    }

    /**
     * Return the current Balance as a Money instance
     *
     * @throws NumberFormatException
     * @throws RoundingNecessaryException
     * @throws UnknownCurrencyException
     */
    public function balanceAsMoney(): Money
    {
        return Money::of($this->balance(), $this->configuration->getCurrency());
    }

    /**
     * Check if the wallet/model has enough balance for the given amount
     */
    public function hasBalanceFor(float|int|string $amount): bool
    {
        try {
            $this
                ->operation()
                ->debit($amount)
                ->throw(false)
                ->pretend()
                ->dispatch();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Checks if its currently locked
     */
    public function locked(): bool
    {
        return $this->cache->locked();
    }
}
