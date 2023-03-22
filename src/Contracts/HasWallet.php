<?php

namespace Flavorly\Wallet\Contracts;

use Flavorly\Wallet\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
interface HasWallet
{
    public function wallet(): Wallet;

    public function transactions(): MorphMany;
}
