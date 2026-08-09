<?php

namespace Tests\Feature\Memory;

use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\RetrievalSlaEscalation;
use App\Models\StorageTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanSlaBreachesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_class_with_no_observations_is_skipped_without_error(): void
    {
        $this->makeClass(['p95_target_ms' => 800, 'zero_loss_required' => false]);

        $this->artisan('memory:scan-sla-breaches')->assertExitCode(0);

        $this->assertSame(0, RetrievalSlaEscalation::count());
    }

    public function test_a_stale_observation_is_corrected_against_a_changed_contract(): void
    {
        $class = $this->makeClass(['p95_target_ms' => 800, 'zero_loss_required' => false]);
        $observation = $this->makeObservation($class, ['p95_latency_ms' => 700, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        // Contract tightened after the observation was recorded.
        $class->update(['p95_target_ms' => 500]);

        $this->artisan('memory:scan-sla-breaches')->assertExitCode(0);

        $observation->refresh();
        $this->assertFalse($observation->sla_met);
        $this->assertTrue($observation->degraded_mode_triggered);
    }

    public function test_a_breach_on_a_non_governance_class_updates_flags_but_raises_no_escalation(): void
    {
        $class = $this->makeClass(['class_key' => RetrievalClass::AGENT_CONTEXT, 'p95_target_ms' => 800, 'completeness_required' => false, 'zero_loss_required' => false]);
        $this->makeObservation($class, ['p95_latency_ms' => 900, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');

        $this->assertSame(0, RetrievalSlaEscalation::count());
    }

    public function test_a_breach_on_a_governance_class_raises_an_escalation(): void
    {
        $class = $this->makeClass(['class_key' => RetrievalClass::AUDIT, 'p95_target_ms' => 30_000, 'completeness_required' => true, 'zero_loss_required' => false, 'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event']);
        $observation = $this->makeObservation($class, ['p95_latency_ms' => 45_000, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');

        $this->assertDatabaseHas('retrieval_sla_escalations', [
            'retrieval_class_id' => $class->id,
            'retrieval_observation_id' => $observation->id,
            'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event',
            'status' => 'open',
        ]);
    }

    public function test_rescanning_refreshes_the_linked_observation_but_preserves_detected_at(): void
    {
        $class = $this->makeClass(['class_key' => RetrievalClass::ARCHIVE, 'p95_target_ms' => 86_400_000, 'zero_loss_required' => true, 'breach_action' => 'Integrity incident, mandatory pack']);
        $firstObservation = $this->makeObservation($class, ['p95_latency_ms' => 100, 'failure_count' => 1, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');
        $firstDetectedAt = RetrievalSlaEscalation::first()->detected_at;

        $this->travel(1)->day();

        $secondObservation = $this->makeObservation($class, ['window_start' => now()->subDay(), 'window_end' => now(), 'p95_latency_ms' => 100, 'failure_count' => 2, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');

        $this->assertSame(1, RetrievalSlaEscalation::count());
        $escalation = RetrievalSlaEscalation::first();
        $this->assertSame($secondObservation->id, $escalation->retrieval_observation_id);
        $this->assertEquals($firstDetectedAt->timestamp, $escalation->detected_at->timestamp);
    }

    public function test_a_recovered_class_does_not_auto_clear_its_open_escalation(): void
    {
        $class = $this->makeClass(['class_key' => RetrievalClass::AUDIT, 'p95_target_ms' => 30_000, 'completeness_required' => true, 'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event']);
        $this->makeObservation($class, ['p95_latency_ms' => 45_000, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');
        $this->assertDatabaseHas('retrieval_sla_escalations', ['retrieval_class_id' => $class->id, 'status' => 'open']);

        $this->makeObservation($class, ['window_start' => now(), 'window_end' => now()->addWeek(), 'p95_latency_ms' => 5_000, 'sla_met' => true, 'degraded_mode_triggered' => false]);

        $this->artisan('memory:scan-sla-breaches');

        $this->assertSame(1, RetrievalSlaEscalation::count());
        $this->assertDatabaseHas('retrieval_sla_escalations', ['retrieval_class_id' => $class->id, 'status' => 'open']);
    }

    private function makeClass(array $overrides): RetrievalClass
    {
        $tier = StorageTier::create([
            'code' => 'tier-'.uniqid(),
            'name' => 'Tier',
            'latency_target_ms' => 50,
            'backing' => 'Test backing',
        ]);

        return RetrievalClass::create(array_merge([
            'class_key' => 'retr:test:'.uniqid(),
            'name' => 'Test Class',
            'serves' => 'Test consumer',
            'storage_tier_id' => $tier->id,
            'p95_target_ms' => 800,
            'p99_target_ms' => null,
            'completeness_required' => false,
            'zero_loss_required' => false,
            'breach_action' => 'Test action',
        ], $overrides));
    }

    private function makeObservation(RetrievalClass $class, array $overrides): RetrievalObservation
    {
        return RetrievalObservation::create(array_merge([
            'retrieval_class_id' => $class->id,
            'window_start' => now()->subWeek(),
            'window_end' => now(),
            'request_count' => 1000,
            'failure_count' => 0,
            'p50_latency_ms' => 100,
            'p95_latency_ms' => 200,
            'p99_latency_ms' => null,
            'sla_met' => true,
            'degraded_mode_triggered' => false,
        ], $overrides));
    }
}
