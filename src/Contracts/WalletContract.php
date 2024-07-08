<?php

namespace Flavorly\Wallet\Contracts;

use Brick\Money\Money;
use Flavorly\LaravelHelpers\Helpers\Math\Math;
use Flavorly\Wallet\Models\Transaction;
use Flavorly\Wallet\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
interface WalletContract
{
    /**
     * Returns the wallet instance
     */
    public function wallet(): Wallet;

    /**
     * Returns all transactions
     *
     * @return MorphMany<Transaction>
     */
    public function transactions(): MorphMany;

    /**
     * Gets the balance attribute cached
     */
    public function getBalanceAttribute(): Math;

    /**
     * Gets the balance attribute without cache
     */
    public function getBalanceWithoutCacheAttribute(): Math;

    /**
     * Get the balance formatted as money Value
     */
    public function getBalanceAsMoneyAttribute(): Money;

    /**
     * Credits the user or model with the given amount
     *
     * @param  array<string,mixed>  $meta
     */
    public function credit(float|int|string $amount, array $meta = [], ?string $endpoint = null, bool $throw = false): bool;

    /**
     * Credits the user or model with the given amount
     * but without any exceptions
     *
     * @param  array<string,mixed>  $meta
     */
    public function creditQuietly(float|int|string $amount, array $meta = [], ?string $endpoint = null): bool;

    /**
     * Debits the user or model with the given amount
     *
     * @param  array<string,mixed>  $meta
     */
    public function debit(float|int|string $amount, array $meta = [], ?string $endpoint = null, bool $throw = false): bool;

    /**
     * Debits the user or model with the given amount
     * but without any exceptions
     *
     * @param  array<string,mixed>  $meta
     */
    public function debitQuietly(float|int|string $amount, array $meta = [], ?string $endpoint = null): bool;

    /**
     * Checks if the user has balance for the given amount
     */
    public function hasBalanceFor(float|int|string $amount): bool;
}
