<?php

namespace Flavorly\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
