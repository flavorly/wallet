<?php

namespace Flavorly\Wallet\Data;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class WalletOperationData extends Data
{
    public function __construct(
        public bool $credit,
        public float|int|string $amount,
        public ?Model $owner = null,
        public ?Model $subject = null,
        public ?string $endpoint = null,
        /** @var array<string,mixed> */
        public array $meta = [],
    ) {}
}
