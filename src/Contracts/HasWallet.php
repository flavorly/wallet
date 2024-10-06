<?php

namespace Flavorly\Wallet\Contracts;

use Flavorly\LaravelHelpers\Helpers\Math\Math;
use Flavorly\Wallet\Models\Transaction;
use Flavorly\Wallet\Services\BalanceService;
use Flavorly\Wallet\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
interface HasWallet
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
     * Get the balance formatted as money Value
     */
    public function getBalanceFormattedAttribute(): string;

    /**
     * Get the balance service instance
     */
    public function balance(): BalanceService;
}
