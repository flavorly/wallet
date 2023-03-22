<?php

namespace Flavorly\Wallet\Services\Wallet;

use Carbon\CarbonImmutable;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;

class WalletCacheService
{
    public function __construct(
        protected WalletService $wallet,
    ) {
    }

    public function prefix(): string
    {
        return 'wallet:' . $this->wallet->getModel()->getKey();
    }

    public function blockPrefix(): string
    {
        return 'wallet-blocks:' . $this->wallet->getModel()->getKey();
    }

    public function ttlBalance(): CarbonImmutable
    {
        return now()->addDays(7)->toImmutable();
    }

    public function ttlLock(): int
    {
        return 10;
    }

    public function ttlLockWait(): int
    {
        return 1;
    }

    public function hasCachedBalance(): bool
    {
        return Cache::tags($this->tags())->has($this->prefix());
    }

    public function cachedBalance(): float|int|string
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
        ->block($this->ttlLockWait(), $callback);
    }

    public function isLocked(): bool
    {
        /** @var RedisStore $store */
        $store = Cache::store('redis');
        /** @var CacheManager $lockConnection */
        $lockConnection = $store->lockConnection();

        return null !== $lockConnection->get(Cache::getPrefix().$this->blockPrefix());
    }

    public function setBalanceCache(float|int|string $balance): void
    {
        Cache::tags($this->tags())->put(
            $this->prefix(),
            $balance,
            $this->ttlBalance(),
        );
    }

    protected function tags(): array
    {
        return ['wallets'];
    }
}
