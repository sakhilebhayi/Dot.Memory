<?php

namespace App\Services;

use App\Models\RetrievalClass;

/**
 * The single place wiki.md §5's Retrieval SLA Contract is evaluated
 * against a window's raw metrics — used identically by the recording
 * form (app/Livewire/Memory/SlaDashboard.php) and the scheduled scan
 * (app/Console/Commands/ScanSlaBreaches.php), see
 * docs/superpowers/specs/2026-08-09-sla-breach-detection-design.md.
 *
 * `completeness_required` has no corresponding measured field on
 * RetrievalObservation today, so it deliberately does not factor into
 * this computation — only what is genuinely measurable does.
 */
class RetrievalSlaEvaluator
{
    /**
     * @param  array{p95_latency_ms: int, p99_latency_ms: ?int, failure_count: int}  $metrics
     */
    public function meetsContract(RetrievalClass $class, array $metrics): bool
    {
        if ($metrics['p95_latency_ms'] > $class->p95_target_ms) {
            return false;
        }

        if ($class->p99_target_ms !== null
            && $metrics['p99_latency_ms'] !== null
            && $metrics['p99_latency_ms'] > $class->p99_target_ms) {
            return false;
        }

        if ($class->zero_loss_required && $metrics['failure_count'] > 0) {
            return false;
        }

        return true;
    }
}
