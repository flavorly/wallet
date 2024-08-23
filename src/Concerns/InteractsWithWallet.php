<?php

namespace Flavorly\Wallet\Concerns;

use Exception;
use Flavorly\LaravelHelpers\Helpers\Math\Math;
use Flavorly\Wallet\Models\Transaction;
use Flavorly\Wallet\Services\BalanceService;
use Flavorly\Wallet\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
trait InteractsWithWallet
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
        return $this->morphMany(Transaction::class, 'owner');
    }

    /**
     * Laravel get Balance Attribute
     */
    public function getBalanceAttribute(): Math
    {
        return $this->wallet()->balance()->get();
    }

    /**
     * Get the balance formatted as money Value
     */
    public function getBalanceFormattedAttribute(): string
    {
        try {
            return $this
                ->wallet()
                ->balance()
                ->toFormatter()
                ->toMoney()
                ->formatTo($this->locale ?? config('app.locale'));
        } catch (Exception $e) {
            report($e);

            return '0';
        }
    }

    /**
     * Get the balance service instance
     */
    public function balance(): BalanceService
    {
        return $this->wallet()->balance();
    }
}
