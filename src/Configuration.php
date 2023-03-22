<?php

namespace Flavorly\Wallet;

use Flavorly\Wallet\Contracts\WalletContract;

/**
 * Ensures the configuration is bootstrapped and available to the wallet.
 * We pass the current model to the configuration class
 * So we can grab an additional configuration from the model
 */
final class Configuration
{
    public function __construct(
        private readonly WalletContract $model,
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

    public function getMaximumCredit(): float|int|string
    {
        return $this->model->getAttribute(config('laravel-wallet.columns.credit', 'wallet_credit')) ?? 0;
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
