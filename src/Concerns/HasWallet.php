<?php

namespace Flavorly\Wallet\Concerns;

use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Flavorly\LaravelHelpers\Helpers\Math\Math;
use Flavorly\Wallet\Exceptions\WalletLockedException;
use Flavorly\Wallet\Models\Transaction;
use Flavorly\Wallet\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Throwable;

/**
 * @mixin Model
 */
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
     *
     * @return MorphMany<Transaction>
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Laravel get Balance Attribute
     */
    public function getBalanceAttribute(): Math
    {
        return $this->wallet()->balance();
    }

    /**
     * Laravel get Balance Attribute with instance of money
     *
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
     */
    public function getBalanceWithoutCacheAttribute(): Math
    {
        return $this->wallet()->balance(cached: false);
    }

    /**
     * Alias for Credit
     *
     *
     * @throws WalletLockedException
     * @throws Throwable
     */
    public function credit(float|int|string $amount, array $meta = [], ?string $endpoint = null, bool $throw = false): bool
    {
        return $this->wallet()->credit(
            amount: $amount,
            meta: $meta,
            endpoint: $endpoint,
            throw: $throw
        );
    }

    /**
     * Credit the user quietly without exceptions
     */
    public function creditQuietly(float|int|string $amount, array $meta = [], ?string $endpoint = null): bool
    {
        try {
            return $this->wallet()->credit(
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
     * Alias for debit
     *
     *
     * @throws WalletLockedException
     * @throws Throwable
     */
    public function debit(float|int|string $amount, array $meta = [], ?string $endpoint = null, bool $throw = false): bool
    {
        return $this->wallet()->debit(
            amount: $amount,
            meta: $meta,
            endpoint: $endpoint,
            throw: $throw
        );
    }

    /**
     * Attempts to Debit the user quietly without exceptions
     */
    public function debitQuietly(float|int|string $amount, array $meta = [], ?string $endpoint = null): bool
    {
        try {
            return $this->wallet()->debit(
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
     * Alias for HasBalanceFor
     */
    public function hasBalanceFor(float|int|string $amount): bool
    {
        return $this->wallet()->hasBalanceFor($amount);
    }
}
