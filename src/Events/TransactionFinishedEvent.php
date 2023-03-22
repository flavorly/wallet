<?php

namespace Flavorly\Wallet\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;
}
