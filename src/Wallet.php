<?php

namespace Flavorly\Wallet;

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
    public ?Configuration $configuration;

    /**
     * Cache service is responsible for caching & locking
     */
    public ?CacheService $cache;

    /**
     * Math Service is responsible for making calculation
     * Under the hood it uses Brick\Math to perform arbitrary precision calculations
     * The wallet math class is different since it casts all the floats as integers
     */
    public Math $math;

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
    public function credit(float|int|string $amount, array $meta = [], bool $throw = false): bool
    {
        return $this
            ->operation()
            ->meta($meta)
            ->credit($amount)
            ->throw($throw)
            ->dispatch()
            ->ok();
    }

    /**
     *  API Wrapper for the operation class to debig the wallet
     *
     * @throws WalletLockedException
     * @throws \Throwable
     */
    public function debit(float|int|string $amount, array $meta = [], bool $throw = false): bool
    {
        return $this
            ->operation()
            ->meta($meta)
            ->debit($amount)
            ->throw($throw)
            ->dispatch()
            ->ok();
    }

//    /**
//     * Magic method that provides a bridge to the operation class itself
//     * @param $name
//     * @param $arguments
//     * @return mixed
//     */
//    public function __call($name, $arguments)
//    {
//        return (new Operation($this))->{$name}(...$arguments)->dispatch();
//    }

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
            $this->model->update([
                $this->configuration->getBalanceColumn() => $balance,
            ]);
        };

        if ($this->cache->isWithin()) {
            $closure();

            return;
        }

        $this->cache->blockAndWrapInTransaction($closure);
    }

    public function cache(): CacheService
    {
        return $this->cache;
    }

    public function math(): Math
    {
        return $this->math;
    }

    public function balance(bool $cached = true): string
    {
        if (! $cached) {
            $this->refreshBalance();
        }

        if ($this->cache->hasCache() && $this->configuration->getBalance() === $this->cache->balance()) {
            return $this->math->intToFloat($this->cache->balance());
        }

        return $this->math->intToFloat($this->configuration->getBalance());
    }

    /**
     * Check if the wallet/model has enough balance for the given amount
     *
     * @throws \Throwable
     */
    public function hasBalanceFor(float|int|string $amount): bool
    {
        try {
            $this
                ->operation()
                ->debit($amount)
                ->pretend()
                ->dispatch();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Returns the balance without any formatting or casting
     */
    public function balanceRaw(bool $cached = true): int
    {
        if (! $cached) {
            $this->refreshBalance();
        }

        if ($this->cache->hasCache() && $this->configuration->getBalance() === $this->cache->balance()) {
            return (int) $this->cache->balance();
        }

        return (int) $this->configuration->getBalance();
    }

    /**
     * Checks if its currently locked
     */
    public function locked(): bool
    {
        return $this->cache->locked();
    }
}
