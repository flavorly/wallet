<?php

namespace Flavorly\Wallet\Contracts;

use Brick\Money\Money;
use Flavorly\Wallet\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
interface WalletContract
{
    public function wallet(): Wallet;

    public function transactions(): MorphMany;

    public function getBalanceAttribute(): string;

    public function getBalanceWithoutCacheAttribute(): string;

    public function credit(float|int|string $amount, array $meta = [], null|string $endpoint = null, bool $throw = false): string;

    public function debit(float|int|string $amount, array $meta = [], null|string $endpoint = null, bool $throw = false): string;

    public function hasBalanceFor(float|int|string $amount): bool;

    public function getBalanceAsMoneyAttribute(): Money;
}
