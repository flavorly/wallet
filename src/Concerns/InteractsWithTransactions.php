<?php

namespace Flavorly\Wallet\Concerns;

use Flavorly\Wallet\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
trait InteractsWithTransactions
{
    /**
     * Get all the transactions for the model.
     *
     * @return MorphMany<Transaction>
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'subject');
    }
}
