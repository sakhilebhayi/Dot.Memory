<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A governance-tier SLA breach awaiting acknowledgment (wiki.md §5).
 * `resolution_detail` is named deliberately, not `notes`/`review_notes`
 * — see tests/Unit/StoreWithoutReadingInvariantTest.php, which forbids
 * content-shaped field names including "note"/"comment"/"description".
 */
class RetrievalSlaEscalation extends Model
{
    protected $fillable = [
        'retrieval_class_id',
        'retrieval_observation_id',
        'breach_action',
        'status',
        'detected_at',
        'acknowledged_by',
        'resolution_detail',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function retrievalClass(): BelongsTo
    {
        return $this->belongsTo(RetrievalClass::class);
    }

    public function retrievalObservation(): BelongsTo
    {
        return $this->belongsTo(RetrievalObservation::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
