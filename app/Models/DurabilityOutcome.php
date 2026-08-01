<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A verified restore test or integrity check result — wiki.md §4 & §7.
 *
 * Counts and a pass/fail/degraded result only. Never the content that
 * was checked.
 */
class DurabilityOutcome extends Model
{
    use HasFactory;

    public const CHECK_RESTORE_TEST = 'restore_test';
    public const CHECK_INTEGRITY = 'integrity_check';

    public const RESULT_PASS = 'pass';
    public const RESULT_FAIL = 'fail';
    public const RESULT_DEGRADED = 'degraded';

    protected $fillable = [
        'storage_tier_id',
        'check_type',
        'audit_period_start',
        'audit_period_end',
        'items_checked',
        'items_passed',
        'items_failed',
        'integrity_score',
        'result',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'audit_period_start' => 'datetime',
            'audit_period_end' => 'datetime',
            'items_checked' => 'integer',
            'items_passed' => 'integer',
            'items_failed' => 'integer',
            'integrity_score' => 'decimal:4',
            'verified_at' => 'datetime',
        ];
    }

    public function storageTier(): BelongsTo
    {
        return $this->belongsTo(StorageTier::class);
    }
}
