<?php

namespace Flavorly\Wallet\Contracts;

use Flavorly\Wallet\Data\WalletConfigurationData;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Wallet
{
    public function walletConfiguration(): WalletConfigurationData;
    public function transactions(): MorphMany;
}
