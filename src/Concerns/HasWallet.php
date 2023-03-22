<?php

namespace Flavorly\Wallet\Concerns;

use Flavorly\Wallet\Models\Transaction;
use Flavorly\Wallet\Wallet;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasWallet
{
    protected ?Wallet $wallet = null;

    /**
     * Creates a new wallet instance
     * Ensures also that we only boot once
     *
     * @return Wallet
     */
    public function wallet(): Wallet
    {
        if($this->wallet){
            return $this->wallet;
        }
        $this->wallet = new Wallet($this);
        return $this->wallet;
    }

    /**
     * Get all the transactions for the model.
     *
     * @return MorphMany
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class,'transactionable');
    }
}
