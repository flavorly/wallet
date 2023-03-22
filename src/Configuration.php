<?php

namespace Flavorly\Wallet;

use Flavorly\Wallet\Contracts\HasWallet;

class Configuration
{
    public function __construct(
        private readonly HasWallet $model,
    ) {
    }

    public function getDecimals(): int
    {
        return $this->model->getAttribute(config('laravel-wallet.columns.decimals', 'wallet_decimals')) ?? 10;
    }

    public function getPrimaryKey(): string
    {
        return $this->model->getKey();
    }

    public function getBalance(): float|int|string
    {
        return $this->model->getAttribute($this->getBalanceColumn());
    }

    public function getBalanceColumn(): string
    {
        return config('laravel-wallet.columns.balance', 'wallet_balance');
    }

    public function getClass(): string
    {
        return $this->model::class;
    }
}
