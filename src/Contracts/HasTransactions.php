<?php

namespace Flavorly\Wallet\Contracts;

use Flavorly\Wallet\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
interface HasTransactions
{
    /**
     * Returns all transactions
     *
     * @return MorphMany<Transaction>
     */
    public function transactions(): MorphMany;
}
