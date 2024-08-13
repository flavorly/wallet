<?php

namespace Flavorly\Wallet\Events;

use Flavorly\Wallet\Contracts\WalletContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionStartedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WalletContract|Model $model,
        public bool $credit,
        public int|float|string $amount,
    ) {}
}
