<?php

namespace Flavorly\Wallet\Events;

use Flavorly\Wallet\Contracts\HasWallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionStartedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public HasWallet|Model $model,
        public bool $credit,
        public int|float|string $amount,
    ) {}
}
