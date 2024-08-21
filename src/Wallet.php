<?php

namespace Flavorly\Wallet;

use Closure;
use Flavorly\Wallet\Contracts\HasWallet as WalletInterface;
use Flavorly\Wallet\Exceptions\WalletLockedException;
use Flavorly\Wallet\Services\BalanceService;
use Flavorly\Wallet\Services\CacheService;
use Flavorly\Wallet\Services\ConfigurationService;
use Flavorly\Wallet\Services\OperationService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Model;
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
    protected ConfigurationService $configuration;

    /**
     * Cache service is responsible for caching & locking
     */
    protected CacheService $cache;

    /**
     * Balance Service
     */
    protected BalanceService $balance;

    /**
     * Bootstrap the class & resolve all necessary services
     * We take the current Eloquent model as a parameter
     * that should implement the WalletContract
     */
    public function __construct(public readonly WalletInterface $model)
    {
        $this->configuration = app(ConfigurationService::class, ['model' => $model]);
        $this->cache = app(CacheService::class, ['prefix' => $model->getKey()]);
        $this->balance = app(BalanceService::class, [
            'model' => $model,
            'cache' => $this->cache,
            'configuration' => $this->configuration,
        ]);
    }

    /**
     * API Wrapper for the operation class to credit the wallet
     *
     * @param  array<string,mixed>  $meta
     *
     * @throws WalletLockedException
     * @throws Throwable
     */
    public function credit(
        float|int|string $amount,
        array $meta = [],
        ?string $endpoint = null,
        bool $throw = false,
        ?Closure $after = null,
        ?Model $subject = null,
    ): bool {
        return $this
            ->operation(true)
            ->meta($meta)
            ->credit($amount)
            ->throw($throw)
            ->after($after)
            ->subject($subject)
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
    public function debit(
        float|int|string $amount,
        array $meta = [],
        ?string $endpoint = null,
        bool $throw = false,
        ?Closure $after = null,
        ?Model $subject = null,
    ): bool {
        return $this
            ->operation(false)
            ->meta($meta)
            ->debit($amount)
            ->throw($throw)
            ->after($after)
            ->subject($subject)
            ->endpoint($endpoint)
            ->dispatch()
            ->ok();
    }

    /**
     * Credit the user quietly without exceptions
     *
     * @param  array<string,mixed>  $meta
     */
    public function creditQuietly(float|int|string $amount, array $meta = [], ?string $endpoint = null): bool
    {
        try {
            return $this->credit(
                amount: $amount,
                meta: $meta,
                endpoint: $endpoint,
                throw: true
            );
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Debit the user quietly without exceptions
     *
     * @param  array<string,mixed>  $meta
     */
    public function debitQuietly(float|int|string $amount, array $meta = [], ?string $endpoint = null): bool
    {
        try {
            return $this->debit(
                amount: $amount,
                meta: $meta,
                endpoint: $endpoint,
                throw: true
            );
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Creates a new operation
     * This is the main entry point to create transaction
     * Wallet class is just an API for the actual underlying operation object
     */
    public function operation(bool $credit): OperationService
    {
        return new OperationService(
            $credit,
            $this->model,
            $this->cache,
            $this->configuration,
            $this->balance,
        );
    }

    /**
     * Checks if its currently locked
     */
    public function locked(): bool
    {
        return $this->cache->locked();
    }

    /**
     * Get the lock instance but without blocking ( yet )
     */
    public function lock(?int $lockFor = null): Lock
    {
        return $this->cache->lock($lockFor);
    }

    /**
     * Returns the cache service
     */
    public function cache(?int $lockFor = null): CacheService
    {
        return $this->cache;
    }

    /**
     * Returns the balance service
     */
    public function balance(): BalanceService
    {
        return $this->balance;
    }
}
