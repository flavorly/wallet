<?php

namespace Flavorly\Wallet\Events;

use Flavorly\Wallet\Contracts\WalletContract;
use Flavorly\Wallet\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionStartedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WalletContract|Model $model,
        public TransactionType $type,
        public int|float|string $amount,
    ) {}
}
