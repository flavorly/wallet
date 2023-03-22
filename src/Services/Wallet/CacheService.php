<?php

namespace Flavorly\Wallet\Services\Wallet;

use Carbon\CarbonImmutable;
use Flavorly\Wallet\Exceptions\WalletDatabaseTransactionException;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CacheService
{
    public function __construct(
        protected string $prefix,
        protected bool $isWithin = false,
    )
    {
    }

    protected function prefix(): string
    {
        return sprintf('wallet:%s',$this->prefix);
    }

    protected function blockPrefix(): string
    {
        return sprintf('wallet-blocks:%s',$this->prefix);
    }

    protected function ttl(): CarbonImmutable
    {
        return now()->addDays(7)->toImmutable();
    }

    protected function ttlLock(): int
    {
        return 10;
    }

    protected function waitForLockTime(): int
    {
        return 1;
    }

    protected function tags(): array
    {
        return ['wallets'];
    }

    public function hasCache(): bool
    {
        return Cache::tags($this->tags())->has($this->prefix());
    }

    public function balance(): float|int|string
    {
        return Cache::tags($this->tags())->get($this->prefix());
    }

    public function block(callable $callback): mixed
    {
        return Cache::lock(
            $this->blockPrefix(),
            $this->ttlLock(),
            $this->blockPrefix()
        )
        ->block($this->waitForLockTime(), function() use($callback){
            $this->isWithin = true;
            try{
                return $callback();
            } finally {
                $this->isWithin = false;
            }
        });
    }

    public function blockAndWrapInTransaction(callable $callback): mixed
    {
        return $this->block(function() use ($callback): mixed {
            // Means another transaction is already on the way
            if(DB::transactionLevel() > 0){
                return $callback();
            }

            // Otherwise create a new one
            return DB::transaction(function() use ($callback): mixed {
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

    public function locked(): bool
    {
        /** @var RedisStore $store */
        $store = Cache::store('redis');
        /** @var CacheManager $lockConnection */
        $lockConnection = $store->lockConnection();

        return null !== $lockConnection->get(Cache::getPrefix().$this->blockPrefix());
    }

    public function isWithin(): bool
    {
        return $this->isWithin;
    }

    public function put(float|int|string $balance): void
    {
        Cache::tags($this->tags())->put(
            $this->prefix(),
            $balance,
            $this->ttl(),
        );
    }
}
