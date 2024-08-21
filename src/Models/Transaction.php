<?php

namespace Flavorly\Wallet\Models;

use Exception;
use Flavorly\Wallet\Helpers\MoneyValueFormatter;
use Flavorly\Wallet\Services\ConfigurationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property bool $credit
 * @property int|string|float $amount
 * @property string|null $endpoint
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class Transaction extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'subject_type',
        'subject_id',
        'credit',
        'amount',
        'endpoint',
        'meta',
        'created_at',
        'updated_at',
    ];

    /**
     * @return MorphTo<Model, Transaction>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the value of the transaction as a formatter
     */
    public function amount(): MoneyValueFormatter
    {
        return new MoneyValueFormatter(
            $this->amount ?? 0,
            ConfigurationService::getDecimals(),
            ConfigurationService::getCurrency(),
        );
    }

    /**
     * Get the balance formatted as money Value
     */
    public function getAmountFormattedAttribute(): string
    {
        try {
            return $this
                ->amount()
                ->toMoney()
                ->formatTo('en');
        } catch (Exception $e) {
            report($e);

            return $this->amount()->toString();
        }
    }

    /**
     * @return MorphTo<Model, Transaction>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'credit' => 'boolean',
        ];
    }
}
