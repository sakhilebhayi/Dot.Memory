<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An aggregated latency/failure observation for one retrieval class over
 * one time window — wiki.md §4 & §2 ("store without reading").
 *
 * STRUCTURAL INVARIANT: this model must never gain a fillable attribute
 * that could hold arbitrary content — no query text, no result bodies,
 * no free-text notes. Every column is a count, a latency in
 * milliseconds, a boolean, or a timestamp. tests/Unit covers this with
 * an assertion over $fillable. If you are adding a field here, it must
 * be a number, boolean, enum, or timestamp — nothing else belongs on
 * this model.
 */
class RetrievalObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'retrieval_class_id',
        'window_start',
        'window_end',
        'request_count',
        'failure_count',
        'p50_latency_ms',
        'p95_latency_ms',
        'p99_latency_ms',
        'sla_met',
        'degraded_mode_triggered',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'request_count' => 'integer',
            'failure_count' => 'integer',
            'p50_latency_ms' => 'integer',
            'p95_latency_ms' => 'integer',
            'p99_latency_ms' => 'integer',
            'sla_met' => 'boolean',
            'degraded_mode_triggered' => 'boolean',
        ];
    }

    public function retrievalClass(): BelongsTo
    {
        return $this->belongsTo(RetrievalClass::class);
    }

    public function failureRate(): float
    {
        return $this->request_count > 0
            ? round($this->failure_count / $this->request_count, 4)
            : 0.0;
    }
}
