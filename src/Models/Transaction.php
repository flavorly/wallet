<?php

namespace Flavorly\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    protected $fillable = [
        'transactionable_type',
        'transactionable_id',
        'uuid',
        'type',
        'amount',
        'endpoint',
        'meta',
        'created_at',
        'updated_at',
    ];

    /**
     * @return MorphTo<Model, Transaction>
     */
    public function transactionable(): MorphTo
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
        ];
    }
}
