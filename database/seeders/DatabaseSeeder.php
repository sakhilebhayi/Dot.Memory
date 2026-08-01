<?php

namespace Database\Seeders;

use App\Models\DurabilityOutcome;
use App\Models\Index;
use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\StorageTier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Domain data below mirrors wiki.md's storage tiers (§3) and the
     * four-class retrieval SLA contract (§5) with plausible telemetry —
     * numbers only, no content, per the "store without reading"
     * principle (§2).
     */
    public function run(): void
    {
        User::factory()->withPersonalTeam()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $hot = StorageTier::create([
            'code' => 'hot',
            'name' => 'Hot',
            'latency_target_ms' => 50,
            'backing' => 'Graph store + cache projections',
        ]);

        $warm = StorageTier::create([
            'code' => 'warm',
            'name' => 'Warm',
            'latency_target_ms' => 2000,
            'backing' => 'Warm store',
        ]);

        $cold = StorageTier::create([
            'code' => 'cold',
            'name' => 'Cold',
            'latency_target_ms' => null, // ≤ 5 min async — no fixed per-op target
            'backing' => 'Immutable cold archive',
        ]);

        // Indexes (wiki.md §4)
        Index::create([
            'index_key' => 'graph-provenance',
            'version' => 3,
            'type' => Index::TYPE_GRAPH,
            'storage_tier_id' => $hot->id,
            'status' => 'active',
            'entry_count' => 4_821_930,
            'last_rebuilt_at' => now()->subDays(2),
        ]);

        Index::create([
            'index_key' => 'graph-chain-walk-precomputed',
            'version' => 1,
            'type' => Index::TYPE_GRAPH,
            'storage_tier_id' => $hot->id,
            'status' => 'active',
            'entry_count' => 612_004,
            'last_rebuilt_at' => now()->subHours(6),
        ]);

        Index::create([
            'index_key' => 'vector-semantic-search',
            'version' => 5,
            'type' => Index::TYPE_VECTOR,
            'storage_tier_id' => $hot->id,
            'status' => 'active',
            'entry_count' => 9_204_112,
            'last_rebuilt_at' => now()->subDay(),
        ]);

        Index::create([
            'index_key' => 'vector-semantic-search',
            'version' => 4,
            'type' => Index::TYPE_VECTOR,
            'storage_tier_id' => $warm->id,
            'status' => 'deprecated',
            'entry_count' => 8_910_442,
            'last_rebuilt_at' => now()->subDays(30),
        ]);

        Index::create([
            'index_key' => 'audit-log-governance',
            'version' => 2,
            'type' => Index::TYPE_AUDIT_LOG,
            'storage_tier_id' => $warm->id,
            'status' => 'active',
            'entry_count' => 1_337_500,
            'last_rebuilt_at' => now()->subDays(7),
        ]);

        // Retrieval SLA classes (wiki.md §5)
        $agentContext = RetrievalClass::create([
            'class_key' => RetrievalClass::AGENT_CONTEXT,
            'name' => 'Agent Context',
            'serves' => 'Colony agents assembling working context',
            'storage_tier_id' => $hot->id,
            'p95_target_ms' => 800,
            'p99_target_ms' => 2000,
            'completeness_required' => false,
            'zero_loss_required' => false,
            'breach_action' => 'Degraded-mode flag: agents disclose stale-context risk',
        ]);

        $surface = RetrievalClass::create([
            'class_key' => RetrievalClass::SURFACE,
            'name' => 'Human Surface',
            'serves' => 'Human-facing surfaces (Why blocks, dashboards)',
            'storage_tier_id' => $hot->id,
            'p95_target_ms' => 1500,
            'p99_target_ms' => null,
            'completeness_required' => false,
            'zero_loss_required' => false,
            'breach_action' => 'Serve cached-with-timestamp instead of blocking',
        ]);

        $audit = RetrievalClass::create([
            'class_key' => RetrievalClass::AUDIT,
            'name' => 'Audit Access',
            'serves' => 'Governance/audit-log access',
            'storage_tier_id' => $warm->id,
            'p95_target_ms' => 30000,
            'p99_target_ms' => null,
            'completeness_required' => true,
            'zero_loss_required' => false,
            'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event',
        ]);

        $archive = RetrievalClass::create([
            'class_key' => RetrievalClass::ARCHIVE,
            'name' => 'Compliance Archive',
            'serves' => 'Compliance retrieval, historical re-verification',
            'storage_tier_id' => $cold->id,
            'p95_target_ms' => 86_400_000, // 24h ceiling, expressed in ms
            'p99_target_ms' => null,
            'completeness_required' => false,
            'zero_loss_required' => true,
            'breach_action' => 'Integrity incident, mandatory pack',
        ]);

        // Retrieval observations — 8 rolling weekly windows per class,
        // mostly meeting contract with one deliberate breach window on
        // agent-context (mirrors the 2026-01 incident in Dot.Brain's
        // ingested view of this platform).
        $windows = collect(range(7, 0))->map(fn ($weeksAgo) => [
            'start' => now()->subWeeks($weeksAgo + 1),
            'end' => now()->subWeeks($weeksAgo),
        ]);

        $windows->each(function ($window, $i) use ($agentContext) {
            $breach = $i === 5; // one historical breach window
            RetrievalObservation::create([
                'retrieval_class_id' => $agentContext->id,
                'window_start' => $window['start'],
                'window_end' => $window['end'],
                'request_count' => 182_000 + $i * 3_100,
                'failure_count' => $breach ? 640 : random_int(2, 40),
                'p50_latency_ms' => $breach ? 1900 : random_int(180, 320),
                'p95_latency_ms' => $breach ? 4100 : random_int(520, 780),
                'p99_latency_ms' => $breach ? 5800 : random_int(1200, 1950),
                'sla_met' => ! $breach,
                'degraded_mode_triggered' => $breach,
            ]);
        });

        $windows->each(function ($window, $i) use ($surface) {
            RetrievalObservation::create([
                'retrieval_class_id' => $surface->id,
                'window_start' => $window['start'],
                'window_end' => $window['end'],
                'request_count' => 64_000 + $i * 900,
                'failure_count' => random_int(0, 15),
                'p50_latency_ms' => random_int(300, 500),
                'p95_latency_ms' => random_int(900, 1420),
                'p99_latency_ms' => null,
                'sla_met' => true,
                'degraded_mode_triggered' => false,
            ]);
        });

        $windows->each(function ($window, $i) use ($audit) {
            RetrievalObservation::create([
                'retrieval_class_id' => $audit->id,
                'window_start' => $window['start'],
                'window_end' => $window['end'],
                'request_count' => 1_200 + $i * 20,
                'failure_count' => 0,
                'p50_latency_ms' => random_int(4_000, 9_000),
                'p95_latency_ms' => random_int(18_000, 27_500),
                'p99_latency_ms' => null,
                'sla_met' => true,
                'degraded_mode_triggered' => false,
            ]);
        });

        $windows->each(function ($window, $i) use ($archive) {
            RetrievalObservation::create([
                'retrieval_class_id' => $archive->id,
                'window_start' => $window['start'],
                'window_end' => $window['end'],
                'request_count' => 40 + $i,
                'failure_count' => 0,
                'p50_latency_ms' => null,
                'p95_latency_ms' => random_int(1_800_000, 6_000_000), // 30–100 min, well under 24h
                'p99_latency_ms' => null,
                'sla_met' => true,
                'degraded_mode_triggered' => false,
            ]);
        });

        // Durability outcomes (wiki.md §4, §6)
        foreach ([$hot, $warm, $cold] as $tier) {
            for ($i = 3; $i >= 0; $i--) {
                $checked = random_int(500, 5000);
                $failed = $i === 2 && $tier->code === 'cold' ? random_int(1, 3) : 0;
                $passed = $checked - $failed;

                DurabilityOutcome::create([
                    'storage_tier_id' => $tier->id,
                    'check_type' => $i % 2 === 0 ? DurabilityOutcome::CHECK_INTEGRITY : DurabilityOutcome::CHECK_RESTORE_TEST,
                    'audit_period_start' => now()->subWeeks($i + 1),
                    'audit_period_end' => now()->subWeeks($i),
                    'items_checked' => $checked,
                    'items_passed' => $passed,
                    'items_failed' => $failed,
                    'integrity_score' => round($passed / $checked, 4),
                    'result' => $failed === 0 ? DurabilityOutcome::RESULT_PASS : ($failed / $checked > 0.01 ? DurabilityOutcome::RESULT_FAIL : DurabilityOutcome::RESULT_DEGRADED),
                    'verified_at' => now()->subWeeks($i)->subHours(random_int(1, 12)),
                ]);
            }
        }
    }
}
