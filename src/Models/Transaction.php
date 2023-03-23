<?php

namespace Flavorly\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    /**
     * @var string[]
     */
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
     * @var array<string, string>
     */
    protected $casts = [
        'meta' => 'array',
    ];

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }
}
