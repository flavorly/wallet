<?php

namespace Flavorly\Wallet;

use Flavorly\Wallet\Contracts\WalletContract as WalletInterface;

/**
 * A plain and simple wallet API for Laravel & Eloquent Model
 * This will ensure atomic locks across the given class/model
 * & also leverage the cache to reduce the number of queries & race conditions
 *
 */
final class Wallet
{
    /**
     * Stores the configuration for the wallet
     * such as decimals, currency, columns to update etc
     *
     * @var Configuration|null
     */
    public ?Configuration $configuration;

    /**
     * Cache service is responsible for caching & locking
     *
     * @var CacheService|null
     */
    public ?CacheService $cache;

    /**
     * Math Service is responsible for making calculation
     * Under the hood it uses Brick\Math to perform arbitrary precision calculations
     * The wallet math class is different since it casts all the floats as integers
     *
     * @var Math
     */
    public Math $math;

    /**
     * Bootstrap the class & resolve all necessary services
     * We take the current Eloquent model as a parameter
     * that should implement the WalletContract
     *
     *
     * @param  WalletInterface  $model
     */
    public function __construct(public readonly WalletInterface $model)
    {
        $this->configuration = app(Configuration::class, ['model' => $model]);
        $this->cache = app(CacheService::class, ['prefix' => $model->getKey()]);
        $this->math = app(Math::class,['decimalPlaces' => $this->configuration->getDecimals()]);
    }

    /**
     * Creates a new operation
     * This is the main entry point to create transaction
     * Wallet class is just an API for the actual underlying operation object
     *
     * @return Operation
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
     *
     * @return void
     */
    protected function refreshBalance(): void
    {
        $closure = function(){
            // Sum all the balance
            $balance = $this->model->transactions()->sum('amount');

            // Cache the balance
            $this->cache->put($balance);

            // Update the balance on database
            $this->model->update([
                $this->configuration->getBalanceColumn() => $balance,
            ]);
        };

        if($this->cache->isWithin()) {
            $closure();
            return;
        }

        $this->cache->blockAndWrapInTransaction($closure);
    }

    /**
     *
     * @param  bool  $cached
     * @return float|int|string
     */
    public function balance(bool $cached = true): float|int|string
    {
        if(!$cached) {
            $this->refreshBalance();
        }

        if($this->cache->hasCache()) {
            return $this->math->toFloat($this->cache->balance());
        }
        return $this->math->toFloat($this->configuration->getBalance());
    }

    public function locked(): bool
    {
        return $this->cache->locked();
    }
}
