<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A storage tier (hot / warm / cold) — wiki.md §3.
 *
 * Telemetry only: latency target and backing description. Never models
 * the content that lives in the tier.
 */
class StorageTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'latency_target_ms',
        'backing',
    ];

    protected function casts(): array
    {
        return [
            'latency_target_ms' => 'integer',
        ];
    }

    public function indexes(): HasMany
    {
        return $this->hasMany(Index::class);
    }

    public function retrievalClasses(): HasMany
    {
        return $this->hasMany(RetrievalClass::class);
    }

    public function durabilityOutcomes(): HasMany
    {
        return $this->hasMany(DurabilityOutcome::class);
    }
}
