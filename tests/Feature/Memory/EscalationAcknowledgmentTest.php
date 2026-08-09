<?php

namespace Tests\Feature\Memory;

use App\Livewire\Memory\SlaDashboard;
use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\RetrievalSlaEscalation;
use App\Models\StorageTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EscalationAcknowledgmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_acknowledge_an_open_escalation(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $escalation = $this->makeOpenEscalation();

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->call('startAcknowledging', $escalation->id)
            ->set('resolutionDetail', 'Paged SRE, filed INFRA-4521.')
            ->call('confirmAcknowledge');

        $escalation->refresh();
        $this->assertSame('acknowledged', $escalation->status);
        $this->assertSame($admin->id, $escalation->acknowledged_by);
        $this->assertSame('Paged SRE, filed INFRA-4521.', $escalation->resolution_detail);
        $this->assertNotNull($escalation->acknowledged_at);
    }

    public function test_acknowledging_requires_resolution_detail(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $escalation = $this->makeOpenEscalation();

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->call('startAcknowledging', $escalation->id)
            ->set('resolutionDetail', '')
            ->call('confirmAcknowledge')
            ->assertHasErrors(['resolutionDetail']);

        $this->assertSame('open', $escalation->fresh()->status);
    }

    public function test_a_non_admin_cannot_acknowledge(): void
    {
        $member = User::factory()->create(['current_team_id' => null]);
        $escalation = $this->makeOpenEscalation();

        Livewire::actingAs($member)
            ->test(SlaDashboard::class)
            ->call('startAcknowledging', $escalation->id)
            ->assertForbidden();

        $this->assertSame('open', $escalation->fresh()->status);
    }

    public function test_the_dashboard_lists_open_escalations_for_an_admin(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $this->makeOpenEscalation();

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->assertSee('Escalations');
    }

    private function makeOpenEscalation(): RetrievalSlaEscalation
    {
        $tier = StorageTier::create([
            'code' => 'warm-'.uniqid(),
            'name' => 'Warm',
            'latency_target_ms' => null,
            'backing' => 'Warm store',
        ]);

        $class = RetrievalClass::create([
            'class_key' => 'retr:test:'.uniqid(),
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

        return RetrievalSlaEscalation::create([
            'retrieval_class_id' => $class->id,
            'retrieval_observation_id' => $observation->id,
            'breach_action' => $class->breach_action,
            'status' => 'open',
            'detected_at' => now(),
        ]);
    }
}
