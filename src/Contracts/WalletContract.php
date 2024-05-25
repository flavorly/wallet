<?php

namespace Flavorly\Wallet\Contracts;

use Brick\Money\Money;
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
    public function getBalanceAttribute(): string;

    /**
     * Gets the balance attribute without cache
     */
    public function getBalanceWithoutCacheAttribute(): string;

    /**
     * Credits the user or model with the given amount
     *
     * @param  array<string,mixed>  $meta
     */
    public function credit(float|int|string $amount, array $meta = [], ?string $endpoint = null, bool $throw = false): string;

    /**
     * Debits the user or model with the given amount
     *
     * @param  array<string,mixed>  $meta
     */
    public function debit(float|int|string $amount, array $meta = [], ?string $endpoint = null, bool $throw = false): string;

    /**
     * Checks if the user has balance for the given amount
     */
    public function hasBalanceFor(float|int|string $amount): bool;

    /**
     * Get the balance formatted as money Value
     */
    public function getBalanceAsMoneyAttribute(): Money;
}
