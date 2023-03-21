<?php

namespace Flavorly\Wallet\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Flavorly\Wallet\Wallet
 */
class Wallet extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Flavorly\Wallet\Wallet::class;
    }
}
