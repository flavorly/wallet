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
     * @return Wallet
     */
    public function wallet(): Wallet;

    /**
     * Returns all transactions
     * @return MorphMany<Transaction>
     */
    public function transactions(): MorphMany;

    /**
     * Gets the balance attribute cached
     * @return string
     */
    public function getBalanceAttribute(): string;

    /**
     * Gets the balance attribute without cache
     * @return string
     */
    public function getBalanceWithoutCacheAttribute(): string;

    /**
     * Credits the user or model with the given amount
     *
     * @param  float|int|string  $amount
     * @param  array<string,mixed>  $meta
     * @param  string|null  $endpoint
     * @param  bool  $throw
     * @return string
     */
    public function credit(float|int|string $amount, array $meta = [], null|string $endpoint = null, bool $throw = false): string;

    /**
     * Debits the user or model with the given amount
     * @param  float|int|string  $amount
     * @param  array<string,mixed>  $meta
     * @param  string|null  $endpoint
     * @param  bool  $throw
     * @return string
     */
    public function debit(float|int|string $amount, array $meta = [], null|string $endpoint = null, bool $throw = false): string;

    /**
     * Checks if the user has balance for the given amount
     * @param  float|int|string  $amount
     * @return bool
     */
    public function hasBalanceFor(float|int|string $amount): bool;

    /**
     * Get the balance formatted as money Value
     * @return Money
     */
    public function getBalanceAsMoneyAttribute(): Money;
}
