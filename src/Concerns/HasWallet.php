<?php

namespace Flavorly\Wallet\Concerns;

use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Flavorly\Wallet\Exceptions\WalletLockedException;
use Flavorly\Wallet\Models\Transaction;
use Flavorly\Wallet\Wallet;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasWallet
{
    protected ?Wallet $wallet = null;

    /**
     * Creates a new wallet instance
     * Ensures also that we only boot once
     */
    public function wallet(): Wallet
    {
        if ($this->wallet) {
            return $this->wallet;
        }
        $this->wallet = new Wallet($this);

        return $this->wallet;
    }

    /**
     * Get all the transactions for the model.
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Laravel get Balance Attribute
     *
     * @return string
     */
    public function getBalanceAttribute(): string
    {
        return $this->wallet()->balance();
    }

    /**
     * Laravel get Balance Attribute with instance of money
     *
     * @return Money
     * @throws NumberFormatException
     * @throws RoundingNecessaryException
     * @throws UnknownCurrencyException
     */
    public function getBalanceAsMoneyAttribute(): Money
    {
        return $this->wallet()->balanceAsMoney();
    }

    /**
     * Laravel get Balance Attribute but without any cache
     *
     * @return string
     */
    public function getBalanceWithoutCacheAttribute(): string
    {
        return $this->wallet()->balance(cached: false);
    }

    /**
     * Alias for Credit
     *
     * @param  float|int|string  $amount
     * @param  array  $meta
     * @param  string|null  $endpoint
     * @param  bool  $throw
     * @return string
     * @throws WalletLockedException
     * @throws \Throwable
     */
    public function credit(float|int|string $amount, array $meta = [], null|string $endpoint = null, bool $throw = false): string
    {
        return $this->wallet()->credit(
            amount: $amount,
            meta: $meta,
            endpoint: $endpoint,
            throw: $throw
        );
    }

    /**
     * Alias for debit
     *
     * @param  float|int|string  $amount
     * @param  array  $meta
     * @param  string|null  $endpoint
     * @param  bool  $throw
     * @return string
     * @throws WalletLockedException
     * @throws \Throwable
     */
    public function debit(float|int|string $amount, array $meta = [], null|string $endpoint = null, bool $throw = false): string
    {
        return $this->wallet()->debit(
            amount: $amount,
            meta: $meta,
            endpoint: $endpoint,
            throw: $throw
        );
    }

    /**
     * Alias for HasBalanceFor
     *
     * @param  float|int|string  $amount
     * @return bool
     */
    public function hasBalanceFor(float|int|string $amount): bool
    {
        return $this->wallet()->hasBalanceFor($amount);
    }
}
