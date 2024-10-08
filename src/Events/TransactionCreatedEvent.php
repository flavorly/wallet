<?php

namespace Flavorly\Wallet\Events;

use Flavorly\Wallet\Contracts\HasWallet;
use Flavorly\Wallet\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCreatedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public HasWallet|Model $model,
        public Transaction $transaction,
    ) {}
}
