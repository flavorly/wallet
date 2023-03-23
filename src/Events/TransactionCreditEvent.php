<?php

namespace Flavorly\Wallet\Events;

use Flavorly\Wallet\Contracts\WalletContract;
use Flavorly\Wallet\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCreditEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WalletContract|Model $model,
        public Transaction $transaction,
    ) {
    }
}
