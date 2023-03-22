<?php

namespace Flavorly\Wallet\Data;

use Spatie\LaravelData\Data;

class WalletConfigurationData extends Data
{
    public function __construct(
        public readonly string $balance_column,
        public readonly string $decimals_column,
        public readonly string $currency_column,
    ) {
    }
}
