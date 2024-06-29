<?php

namespace Flavorly\Wallet;

use Brick\Money\Currency;
use Flavorly\Wallet\Contracts\WalletContract;
use InvalidArgumentException;

/**
 * Ensures the configuration is bootstrapped and available to the wallet.
 * We pass the current model to the configuration class
 * So we can grab an additional configuration from the model
 */
final readonly class Configuration
{
    public function __construct(
        private WalletContract $model,
    ) {}

    /**
     * Get the number of decimals to use for the wallet
     */
    public function getDecimals(): int
    {
        //@phpstan-ignore-next-line
        $decimals = $this->model->getAttribute(config('laravel-wallet.columns.decimals', 'wallet_decimals')) ?? 10;
        if (is_string($decimals)) {
            return (int) $decimals;
        }

        if (is_int($decimals) || is_float($decimals)) {
            return (int) $decimals;
        }

        return 10;
    }

    /**
     * Get the model primary key
     */
    public function getPrimaryKey(): string
    {
        if (! is_string($this->model->getKey()) && ! is_int($this->model->getKey())) {
            throw new InvalidArgumentException('Primary key must be a string or an integer');
        }

        return (string) $this->model->getKey();
    }

    /**
     * Get the Database/Model Balance Attribute
     */
    public function getBalance(): mixed
    {
        return $this->model->getAttribute($this->getBalanceColumn());
    }

    /**
     * Get the maximum allowed credit ( negative ) Balance allowed for the wallet/model
     */
    public function getMaximumCredit(): float|int|string
    {
        //@phpstan-ignore-next-line
        return $this->model->getAttribute(config('laravel-wallet.columns.credit', 'wallet_credit')) ?? 0;
    }

    /**
     * Get the wallet currency, defaults to USD if none provided
     */
    public function getCurrency(): string
    {
        //@phpstan-ignore-next-line
        return $this->model->getAttribute(config('laravel-wallet.columns.currency', 'wallet_currency')) ?? config('laravel-wallet.currency', 'USD');
    }

    /**
     * Get the column name for the balance
     */
    public function getBalanceColumn(): string
    {
        //@phpstan-ignore-next-line
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
