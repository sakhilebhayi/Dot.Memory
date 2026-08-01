<?php

namespace Tests\Feature\Memory;

use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\StorageTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_sla_dashboard_with_attainment(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $hot = StorageTier::create([
            'code' => 'hot',
            'name' => 'Hot',
            'latency_target_ms' => 50,
            'backing' => 'Graph store + cache projections',
        ]);

        $agentContext = RetrievalClass::create([
            'class_key' => RetrievalClass::AGENT_CONTEXT,
            'name' => 'Agent Context',
            'serves' => 'Colony agents assembling working context',
            'storage_tier_id' => $hot->id,
            'p95_target_ms' => 800,
            'p99_target_ms' => 2000,
            'completeness_required' => false,
            'zero_loss_required' => false,
            'breach_action' => 'Degraded-mode flag',
        ]);

        RetrievalObservation::create([
            'retrieval_class_id' => $agentContext->id,
            'window_start' => now()->subWeek(),
            'window_end' => now(),
            'request_count' => 100_000,
            'failure_count' => 5,
            'p50_latency_ms' => 250,
            'p95_latency_ms' => 620,
            'p99_latency_ms' => 1500,
            'sla_met' => true,
            'degraded_mode_triggered' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertSee(RetrievalClass::AGENT_CONTEXT)
            ->assertSee('620ms');
    }

    public function test_index_inventory_page_renders(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('indexes.index'))
            ->assertOk()
            ->assertViewIs('indexes.index');
    }

    public function test_durability_outcomes_page_renders(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('durability.index'))
            ->assertOk()
            ->assertViewIs('durability.index');
    }
}
