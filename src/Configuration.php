<?php

namespace Flavorly\Wallet;

use Brick\Money\Currency;
use Brick\Money\Money;
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

    /**
     * Get the number of decimals to use for the wallet
     */
    public function getDecimals(): int
    {
        return $this->model->getAttribute(config('laravel-wallet.columns.decimals', 'wallet_decimals')) ?? 10;
    }

    /**
     * Get the model primary key
     */
    public function getPrimaryKey(): string
    {
        return $this->model->getKey();
    }

    /**
     * Get the Database/Model Balance Attribute
     */
    public function getBalance(): float|int|string
    {
        return $this->model->getAttribute($this->getBalanceColumn());
    }

    /**
     * Get the maximum allowed credit ( negative ) Balance allowed for the wallet/model
     */
    public function getMaximumCredit(): float|int|string
    {
        return $this->model->getAttribute(config('laravel-wallet.columns.credit', 'wallet_credit')) ?? 0;
    }

    /**
     * Get the wallet currency, defaults to USD if none provided
     */
    public function getCurrency(): string
    {
        return $this->model->getAttribute(config('laravel-wallet.columns.currency', 'wallet_currency')) ?? config('laravel-wallet.currency', 'USD');
    }


    /**
     * Get the column name for the balance
     */
    public function getBalanceColumn(): string
    {
        return config('laravel-wallet.columns.balance', 'wallet_balance');
    }

    /**
     * Get the called class for the wallet
     */
    public function getClass(): string
    {
        return $this->model::class;
    }
}
