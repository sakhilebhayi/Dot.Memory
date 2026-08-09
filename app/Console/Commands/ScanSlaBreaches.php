<?php

namespace App\Console\Commands;

use App\Models\RetrievalClass;
use App\Models\RetrievalSlaEscalation;
use App\Services\RetrievalSlaEvaluator;
use Illuminate\Console\Command;

/**
 * This platform's first scheduled job (Dot.Brain audit's Level 1/2
 * candidate, wiki.md §5's Retrieval SLA Contract).
 *
 * Level 1 (no escalation): for every retrieval class, re-evaluate its
 * latest observation against the class's CURRENT contract and correct
 * sla_met/degraded_mode_triggered if they've drifted — genuinely useful
 * since wiki.md §7 says SLA targets are reviewed semi-annually.
 *
 * Level 2 (proposal only, never auto-executes): for the two
 * governance-tier classes (completeness_required or zero_loss_required),
 * a fresh breach raises or refreshes an open RetrievalSlaEscalation for
 * a canGovern() admin to acknowledge (see
 * App\Livewire\Memory\SlaDashboard::confirmAcknowledge()).
 */
class ScanSlaBreaches extends Command
{
    protected $signature = 'memory:scan-sla-breaches';

    protected $description = 'Re-evaluate the latest observation per retrieval class against its current contract and escalate governance-tier breaches.';

    public function handle(RetrievalSlaEvaluator $evaluator): int
    {
        RetrievalClass::all()->each(function (RetrievalClass $class) use ($evaluator): void {
            $observation = $class->latestObservation();

            if (! $observation) {
                return;
            }

            $met = $evaluator->meetsContract($class, [
                'p95_latency_ms' => $observation->p95_latency_ms,
                'p99_latency_ms' => $observation->p99_latency_ms,
                'failure_count' => $observation->failure_count,
            ]);

            if ($observation->sla_met !== $met || $observation->degraded_mode_triggered !== ! $met) {
                $observation->forceFill([
                    'sla_met' => $met,
                    'degraded_mode_triggered' => ! $met,
                ])->save();
            }

            $isGovernanceTier = $class->completeness_required || $class->zero_loss_required;

            if (! $isGovernanceTier || $met) {
                return;
            }

            $openEscalation = RetrievalSlaEscalation::where('retrieval_class_id', $class->id)
                ->where('status', 'open')
                ->first();

            if ($openEscalation) {
                $openEscalation->update([
                    'retrieval_observation_id' => $observation->id,
                    'breach_action' => $class->breach_action,
                ]);

                return;
            }

            RetrievalSlaEscalation::create([
                'retrieval_class_id' => $class->id,
                'retrieval_observation_id' => $observation->id,
                'breach_action' => $class->breach_action,
                'status' => 'open',
                'detected_at' => now(),
            ]);
        });

        $this->info('SLA breach scan complete.');

        return self::SUCCESS;
    }
}
