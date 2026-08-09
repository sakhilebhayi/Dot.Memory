<?php

namespace Tests\Feature\Memory;

use App\Livewire\Memory\SlaDashboard;
use App\Models\RetrievalClass;
use App\Models\RetrievalObservation;
use App\Models\StorageTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RetrievalObservationRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_record_a_compliant_observation(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $class = $this->makeClass(['p95_target_ms' => 800, 'p99_target_ms' => 2000, 'zero_loss_required' => false]);

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->call('startRecordingObservation', $class->id)
            ->set('observationWindowStart', now()->subWeek()->toDateTimeString())
            ->set('observationWindowEnd', now()->toDateTimeString())
            ->set('observationRequestCount', '10000')
            ->set('observationFailureCount', '0')
            ->set('observationP50', '200')
            ->set('observationP95', '620')
            ->set('observationP99', '1500')
            ->call('saveObservation');

        $this->assertDatabaseHas('retrieval_observations', [
            'retrieval_class_id' => $class->id,
            'sla_met' => true,
            'degraded_mode_triggered' => false,
        ]);
    }

    public function test_an_admin_can_record_a_breaching_observation(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $class = $this->makeClass(['p95_target_ms' => 800, 'p99_target_ms' => 2000, 'zero_loss_required' => false]);

        Livewire::actingAs($admin)
            ->test(SlaDashboard::class)
            ->call('startRecordingObservation', $class->id)
            ->set('observationWindowStart', now()->subWeek()->toDateTimeString())
            ->set('observationWindowEnd', now()->toDateTimeString())
            ->set('observationRequestCount', '10000')
            ->set('observationFailureCount', '0')
            ->set('observationP50', '900')
            ->set('observationP95', '1200')
            ->set('observationP99', '2500')
            ->call('saveObservation');

        $this->assertDatabaseHas('retrieval_observations', [
            'retrieval_class_id' => $class->id,
            'sla_met' => false,
            'degraded_mode_triggered' => true,
        ]);
    }

    public function test_a_non_admin_cannot_record_an_observation(): void
    {
        $member = User::factory()->create(['current_team_id' => null]);
        $class = $this->makeClass([]);

        Livewire::actingAs($member)
            ->test(SlaDashboard::class)
            ->call('startRecordingObservation', $class->id)
            ->assertForbidden();

        $this->assertSame(0, RetrievalObservation::count());
    }

    private function makeClass(array $overrides): RetrievalClass
    {
        $tier = StorageTier::create([
            'code' => 'hot-'.uniqid(),
            'name' => 'Hot',
            'latency_target_ms' => 50,
            'backing' => 'Graph store + cache projections',
        ]);

        return RetrievalClass::create(array_merge([
            'class_key' => 'retr:test:'.uniqid(),
            'name' => 'Test Class',
            'serves' => 'Test consumer',
            'storage_tier_id' => $tier->id,
            'p95_target_ms' => 800,
            'p99_target_ms' => 2000,
            'completeness_required' => false,
            'zero_loss_required' => false,
            'breach_action' => 'Test action',
        ], $overrides));
    }
}
