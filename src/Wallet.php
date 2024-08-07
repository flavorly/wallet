<?php

namespace Flavorly\Wallet;

use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Context\AutoContext;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Closure;
use Flavorly\LaravelHelpers\Helpers\Math\Math;
use Flavorly\Wallet\Contracts\WalletContract as WalletInterface;
use Flavorly\Wallet\Enums\TransactionType;
use Flavorly\Wallet\Exceptions\NotEnoughBalanceException;
use Flavorly\Wallet\Exceptions\WalletLockedException;
use Throwable;

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
    protected Configuration $configuration;

    /**
     * Cache service is responsible for caching & locking
     */
    protected CacheService $cache;

    /**
     * Temporary cache for the balance as a static variable
     * This is used to avoid multiple queries to the database or cache hits
     * when dealing with the same wallet multiple times in the same request or
     * for a given process lifecycle
     */
    protected null|string|float|int $localCachedRawBalance = null;

    /**
     * Bootstrap the class & resolve all necessary services
     * We take the current Eloquent model as a parameter
     * that should implement the WalletContract
     */
    public function __construct(public readonly WalletInterface $model)
    {
        $this->configuration = app(Configuration::class, ['model' => $model]);
        $this->cache = app(CacheService::class, ['prefix' => $model->getKey()]);
    }

    /**
     * API Wrapper for the operation class to credit the wallet
     *
     * @param  array<string,mixed>  $meta
     *
     * @throws WalletLockedException
     * @throws Throwable
     */
    public function credit(float|int|string $amount, array $meta = [], ?string $endpoint = null, bool $throw = false, ?Closure $after = null): bool
    {
        return $this
            ->operation(TransactionType::CREDIT)
            ->meta($meta)
            ->credit($amount)
            ->throw($throw)
            ->after($after)
            ->endpoint($endpoint)
            ->dispatch()
            ->ok();
    }

    /**
     * API Wrapper for the operation class to debit the wallet
     *
     * @param  array<string,mixed>  $meta
     *
     * @throws WalletLockedException
     * @throws Throwable
     */
    public function debit(float|int|string $amount, array $meta = [], ?string $endpoint = null, bool $throw = false, ?Closure $after = null): bool
    {
        return $this
            ->operation(TransactionType::DEBIT)
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
    public function operation(TransactionType $type): Operation
    {
        return new Operation($this, $type);
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
    public function balance(bool $cached = true): Math
    {
        return Math::of(
            $this->balanceRaw($cached) ?? 0,
            $this->configuration()->getDecimals(),
            $this->configuration()->getDecimals(),
        )->fromStorage();
    }

    /**
     * Returns the balance without any formatting or casting
     */
    public function balanceRaw(bool $cached = true): int|float|string|null
    {
        if (! $cached) {
            $this->refreshBalance();
        }

        // If we have a local cached balance, return it
        if ($this->localCachedRawBalance !== null) {
            return $this->localCachedRawBalance;
        }

        if ($this->configuration->getBalance()) {
            $this->localCachedRawBalance = $this->configuration->getBalance();
            $this->cache()->put($this->localCachedRawBalance);
        }

        return $this->localCachedRawBalance ?? '0';
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
        return Money::of(
            $this->balance()->toNumber(),
            $this->configuration->getCurrency(),
            new AutoContext(),
        );
    }

    /**
     * Check if the wallet/model has enough balance for the given amount
     */
    public function hasBalanceFor(float|int|string $amount): bool
    {
        try {
            $this
                ->operation(TransactionType::DEBIT)
                ->debit($amount)
                ->throw(false)
                ->pretend()
                ->dispatch();

            return true;
        } catch (NotEnoughBalanceException $e) {
            return false;
        } catch (Throwable $e) {
            report($e);

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
