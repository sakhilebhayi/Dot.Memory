<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the four named retrieval SLA contracts — wiki.md §5.
 *
 * retr:agent-context:hot, retr:surface:hot, retr:audit:warm,
 * retr:archive:cold. Defines the contract only (targets, breach
 * behavior) — never anything about what is retrieved.
 */
class RetrievalClass extends Model
{
    use HasFactory;

    public const AGENT_CONTEXT = 'retr:agent-context:hot';
    public const SURFACE = 'retr:surface:hot';
    public const AUDIT = 'retr:audit:warm';
    public const ARCHIVE = 'retr:archive:cold';

    protected $fillable = [
        'class_key',
        'name',
        'serves',
        'storage_tier_id',
        'p95_target_ms',
        'p99_target_ms',
        'completeness_required',
        'zero_loss_required',
        'breach_action',
    ];

    protected function casts(): array
    {
        return [
            'p95_target_ms' => 'integer',
            'p99_target_ms' => 'integer',
            'completeness_required' => 'boolean',
            'zero_loss_required' => 'boolean',
        ];
    }

    public function storageTier(): BelongsTo
    {
        return $this->belongsTo(StorageTier::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(RetrievalObservation::class);
    }

    public function latestObservation(): ?RetrievalObservation
    {
        return $this->observations()->orderByDesc('window_end')->first();
    }
}
