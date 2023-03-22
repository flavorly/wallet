<?php

namespace Flavorly\Wallet\Services\Wallet;

use Flavorly\Wallet\Configuration;
use Flavorly\Wallet\Contracts\HasWallet;
use Flavorly\Wallet\Operation;
use Flavorly\Wallet\Services\Math\WalletMathService;

class WalletService implements WalletInterface
{
    public ?Configuration $configuration;

    public ?CacheService $cache;

    public WalletMathService $math;

    public function __construct(public readonly HasWallet $model)
    {
        $this->configuration = app(Configuration::class, ['model' => $model]);
        $this->cache = app(CacheService::class, ['prefix' => $model->getKey()]);
        $this->math = app(WalletMathService::class, ['decimalPlaces' => $this->configuration->getDecimals()]);
    }

    public function operation(): Operation
    {
        return new Operation($this);
    }

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

    public function balance(bool $cached = true): float|int|string
    {
        if (! $cached) {
            $this->refreshBalance();
        }

        if ($this->cache->hasCache()) {
            return $this->math->toFloat($this->cache->balance());
        }

        return $this->math->toFloat($this->configuration->getBalance());
    }

    public function locked(): bool
    {
        return $this->cache->locked();
    }
}
