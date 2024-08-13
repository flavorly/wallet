<?php

namespace Flavorly\Wallet;

use Carbon\CarbonImmutable;
use Flavorly\Wallet\Exceptions\WalletDatabaseTransactionException;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The cache service is responsible for caching the wallet balance and
 * also perform locks based on the given prefix
 * The prefix usually is the Eloquent Model Primary key or UUID
 * that is being used as the wallet, the cache service will always prefix with wallet::
 * and also ensures we have a safe way to interact with cache & locks
 */
final class CacheService
{
    public function __construct(
        protected string $prefix,
        protected bool $isWithin = false,
    ) {}

    /**
     * Get the cache prefix
     */
    protected function prefix(): string
    {
        return sprintf('wallet:balance:%s', $this->prefix);
    }

    /**
     * Get the prefix used for Locks
     */
    protected function blockPrefix(): string
    {
        return sprintf('wallet-locks:%s', $this->prefix);
    }

    /**
     * The time to save the balance on Redis/Cache
     */
    protected function ttl(): CarbonImmutable
    {
        return now()->addDays(7)->toImmutable();
    }

    /**
     * The time to lock the transaction operation
     * This will ensure that no other transaction is being processed at same time
     * We assume 30 seconds is a good window for processing a transaction
     * After 30 seconds or the callback is done, the lock will be released automatically
     */
    protected function ttlLock(): int
    {
        return 30;
    }

    /**
     * How much seconds to wait in case another lock is in place
     * After the time is over, an exception will be thrown and the transaction will be aborted
     */
    protected function waitForLockTime(): int
    {
        return 1;
    }

    /**
     * Check if there is currently Balance cache in place
     */
    public function hasCache(): bool
    {
        return Cache::has($this->prefix());
    }

    /**
     * Get the current Balance in cache
     *
     * @throws WalletDatabaseTransactionException
     */
    public function balance(): float|int|string
    {
        $balance = Cache::get($this->prefix());
        if (is_string($balance) || is_int($balance) || is_float($balance)) {
            return $balance;
        }
        throw new WalletDatabaseTransactionException('Invalid balance type');
    }

    /**
     * Get the lock instance but without blocking ( yet )
     */
    public function lock(?int $lockFor = null): Lock
    {
        return Cache::lock(
            $this->blockPrefix(),
            $lockFor ?? $this->ttlLock(),
            $this->blockPrefix()
        );
    }

    /**
     * Blocks the current wallet/model, takes a callback and executes it
     * This is the main entry point to perform safe operations without
     * worry about race condition
     *
     * Please do note that this will only ensure a Redis Lock
     * but will not ensure database transaction
     *
     * While i dislike nested callbacks
     * we nest the callbacks to ensure we place the set the isWithin flag
     * So we know we are currently about to process a block of code
     * Try finally will ensure that the flag is set to false always when its ended
     * no matter if an exception is thrown or not
     */
    public function block(callable $callback): mixed
    {
        return $this
            ->lock()
            ->block($this->waitForLockTime(), function () use ($callback) {
                $this->isWithin = true;
                try {
                    return $callback();
                } finally {
                    $this->isWithin = false;
                }
            });
    }

    /**
     * Same as the block but this also wraps the callback in a database transaction
     *
     * @throws LockTimeoutException
     * @throws WalletDatabaseTransactionException
     */
    public function blockAndWrapInTransaction(callable $callback): mixed
    {
        return $this->block(function () use ($callback): mixed {
            // Means another transaction is already on the way
            if (DB::transactionLevel() > 0) {
                return $callback();
            }

            // Otherwise create a new one
            return DB::transaction(function () use ($callback): mixed {
                $result = $callback();
                throw_if(
                    $result === false || (is_countable($result) && count($result) === 0),
                    WalletDatabaseTransactionException::class,
                    sprintf('Wallet Datatable transaction failed with message: %s', $result)
                );

                return $result;
            });
        });
    }

    /**
     * Check if there is a current lock in place
     * This is only available when the cache driver is Redis
     */
    public function locked(): bool
    {
        /** @var RedisStore $store */
        $store = Cache::store('redis');
        /** @var CacheManager $lockConnection */
        $lockConnection = $store->lockConnection();

        return $lockConnection->get(Cache::getPrefix().$this->blockPrefix()) !== null;
    }

    /**
     * Check if we are currently within a block and already have a safe lock in place
     */
    public function isWithin(): bool
    {
        return $this->isWithin;
    }

    /**
     * Put some value in the cache
     * In this case only for balance but could be used for another scenario in the future
     */
    public function put(mixed $balance): void
    {
        Cache::put(
            $this->prefix(),
            $balance,
            $this->ttl(),
        );
    }
}
