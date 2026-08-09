<?php

namespace Tests\Unit;

use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\RetrievalSlaEscalation;
use App\Models\StorageTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetrievalSlaEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_escalation_belongs_to_its_class_observation_and_acknowledger(): void
    {
        $tier = StorageTier::create([
            'code' => 'warm',
            'name' => 'Warm',
            'latency_target_ms' => null,
            'backing' => 'Warm store',
        ]);

        $class = RetrievalClass::create([
            'class_key' => RetrievalClass::AUDIT,
            'name' => 'Audit Access',
            'serves' => 'Governance/audit-log access',
            'storage_tier_id' => $tier->id,
            'p95_target_ms' => 30_000,
            'p99_target_ms' => null,
            'completeness_required' => true,
            'zero_loss_required' => false,
            'breach_action' => 'Escalate to SRE Lead + Security Agent — governance event',
        ]);

        $observation = RetrievalObservation::create([
            'retrieval_class_id' => $class->id,
            'window_start' => now()->subWeek(),
            'window_end' => now(),
            'request_count' => 1000,
            'failure_count' => 0,
            'p50_latency_ms' => 20_000,
            'p95_latency_ms' => 45_000,
            'p99_latency_ms' => null,
            'sla_met' => false,
            'degraded_mode_triggered' => true,
        ]);

        $admin = User::factory()->withPersonalTeam()->create();

        $escalation = RetrievalSlaEscalation::create([
            'retrieval_class_id' => $class->id,
            'retrieval_observation_id' => $observation->id,
            'breach_action' => $class->breach_action,
            'status' => 'acknowledged',
            'detected_at' => now(),
            'acknowledged_by' => $admin->id,
            'resolution_detail' => 'Paged SRE, filed INFRA-4521.',
            'acknowledged_at' => now(),
        ]);

        $this->assertSame($class->id, $escalation->retrievalClass->id);
        $this->assertSame($observation->id, $escalation->retrievalObservation->id);
        $this->assertSame($admin->id, $escalation->acknowledgedBy->id);
    }
}
