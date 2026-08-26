<?php

namespace Tests\Feature\Intelligence;

use App\Models\LoopRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dot.Memory's side of the ecosystem intelligence loop (ADR-0015).
 * Memory remembers; it never reasons and never executes.
 */
class LoopApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsService(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function envelope(array $overrides = []): array
    {
        return array_merge([
            'loop_id' => 'loop-1',
            'event_id' => 'evt-'.uniqid(),
            'platform' => 'dot-mines',
            'source' => 'telemetry',
            'subject_type' => 'customer',
            'subject_id' => '42',
            'subject_label' => 'Acme Mining',
            'signature' => 'engagement-decline',
            'occurred_at' => now()->toISOString(),
        ], $overrides);
    }

    public function test_every_loop_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/intelligence/events', [])->assertUnauthorized();
        $this->postJson('/api/intelligence/decisions', [])->assertUnauthorized();
        $this->postJson('/api/intelligence/actions', [])->assertUnauthorized();
        $this->postJson('/api/intelligence/outcomes', [])->assertUnauthorized();
        $this->getJson('/api/intelligence/context?subject_type=customer&subject_id=1')->assertUnauthorized();
    }

    public function test_an_observation_is_recorded(): void
    {
        $this->actingAsService();

        $this->postJson('/api/intelligence/events', $this->envelope([
            'event_id' => 'evt-obs-1',
            'detail' => ['note' => 'interactions down 40% over six weeks'],
        ]))->assertCreated()->assertJsonPath('data.stage', 'observation');

        $this->assertDatabaseHas('loop_records', ['event_id' => 'evt-obs-1', 'stage' => 'observation']);
    }

    public function test_recording_is_idempotent_on_event_id(): void
    {
        $this->actingAsService();

        $payload = $this->envelope(['event_id' => 'evt-dupe']);

        $this->postJson('/api/intelligence/events', $payload)->assertCreated();
        $this->postJson('/api/intelligence/events', $payload)->assertOk();

        $this->assertSame(1, LoopRecord::query()->where('event_id', 'evt-dupe')->count());
    }

    public function test_a_decision_requires_confidence_risk_and_an_autonomy_level(): void
    {
        $this->actingAsService();

        $this->postJson('/api/intelligence/decisions', $this->envelope([
            'confidence' => 1.7,
            'risk' => 0.2,
            'autonomy_level' => 'telepathy',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['confidence', 'autonomy_level']);
    }

    public function test_a_full_loop_is_recorded_and_linked_by_loop_id(): void
    {
        $this->actingAsService();

        $this->postJson('/api/intelligence/events', $this->envelope(['event_id' => 'e1']))->assertCreated();
        $this->postJson('/api/intelligence/decisions', $this->envelope([
            'event_id' => 'd1', 'platform' => 'dot-brain',
            'confidence' => 0.8, 'risk' => 0.2, 'autonomy_level' => 'recommend', 'requires_approval' => true,
        ]))->assertCreated();
        $this->postJson('/api/intelligence/actions', $this->envelope([
            'event_id' => 'a1', 'platform' => 'dot-dopemine',
            'action_kind' => 'retention_followup', 'executor_platform' => 'dot-dopemine',
            'mechanic_ref' => 'mech:milestone-recognition',
            'approval_status' => 'approved', 'execution_status' => 'succeeded',
        ]))->assertCreated();
        $this->postJson('/api/intelligence/outcomes', $this->envelope([
            'event_id' => 'o1', 'verdict' => 'improved', 'measure' => 'engagement',
        ]))->assertCreated();

        $this->assertSame(4, LoopRecord::query()->where('loop_id', 'loop-1')->count());
    }

    public function test_context_is_honest_when_nothing_is_known(): void
    {
        $this->actingAsService();

        $response = $this->getJson('/api/intelligence/context?subject_type=customer&subject_id=999');

        $response->assertOk()
            ->assertJsonPath('data.known', false)
            ->assertJsonPath('data.observations', 0);

        $this->assertContains(
            'Nothing has ever been recorded about this subject.',
            $response->json('data.gaps'),
        );
    }

    public function test_context_names_the_gap_when_nothing_has_been_tried(): void
    {
        $this->actingAsService();
        LoopRecord::factory()->create(['subject_type' => 'customer', 'subject_id' => '7']);

        $gaps = $this->getJson('/api/intelligence/context?subject_type=customer&subject_id=7')
            ->assertOk()->json('data.gaps');

        $this->assertContains(
            'No action has ever been taken for this subject, so nothing is known about what works.',
            $gaps,
        );
    }

    public function test_context_reports_what_was_tried_and_what_worked(): void
    {
        $this->actingAsService();

        foreach ([['l1', 'improved'], ['l2', 'worsened']] as [$loop, $verdict]) {
            LoopRecord::factory()->action()->create([
                'loop_id' => $loop, 'subject_type' => 'customer', 'subject_id' => '5',
                'action_kind' => 'retention_followup',
            ]);
            LoopRecord::factory()->outcome($verdict)->create([
                'loop_id' => $loop, 'subject_type' => 'customer', 'subject_id' => '5',
            ]);
        }

        $data = $this->getJson('/api/intelligence/context?subject_type=customer&subject_id=5')
            ->assertOk()->json('data');

        $this->assertCount(2, $data['what_was_tried']);
        $this->assertSame('retention_followup', $data['what_worked'][0]['action_kind']);
        $this->assertSame(2, $data['what_worked'][0]['graded']);
        $this->assertSame(0.5, $data['what_worked'][0]['success_rate']);
    }

    public function test_an_ungraded_action_counts_as_neither_success_nor_failure(): void
    {
        $this->actingAsService();

        LoopRecord::factory()->action()->create([
            'loop_id' => 'l-open', 'subject_type' => 'customer', 'subject_id' => '6',
            'action_kind' => 'retention_followup',
        ]);

        $worked = $this->getJson('/api/intelligence/context?subject_type=customer&subject_id=6')
            ->assertOk()->json('data.what_worked');

        $this->assertSame(1, $worked[0]['attempts']);
        $this->assertSame(0, $worked[0]['graded']);
        $this->assertNull($worked[0]['success_rate'], 'An action awaiting its outcome must not be graded either way.');
    }

    public function test_the_system_demonstrably_learns_from_a_recorded_outcome(): void
    {
        $this->actingAsService();

        $before = $this->getJson('/api/intelligence/context?subject_type=customer&subject_id=42')
            ->assertOk()->json('data');
        $this->assertFalse($before['known']);

        $this->postJson('/api/intelligence/events', $this->envelope(['event_id' => 'l-e']))->assertCreated();
        $this->postJson('/api/intelligence/actions', $this->envelope([
            'event_id' => 'l-a', 'action_kind' => 'retention_followup',
            'executor_platform' => 'dot-dopemine', 'execution_status' => 'succeeded',
        ]))->assertCreated();
        $this->postJson('/api/intelligence/outcomes', $this->envelope([
            'event_id' => 'l-o', 'verdict' => 'improved', 'measure' => 'engagement',
        ]))->assertCreated();

        $after = $this->getJson('/api/intelligence/context?subject_type=customer&subject_id=42')
            ->assertOk()->json('data');

        $this->assertTrue($after['known']);
        // JSON renders a whole-number rate as 1, so compare numerically.
        $this->assertEquals(1.0, $after['what_worked'][0]['success_rate']);
        $this->assertNotEquals($before['what_worked'], $after['what_worked']);
    }

    public function test_the_narrative_detail_is_encrypted_at_rest(): void
    {
        $this->actingAsService();

        $this->postJson('/api/intelligence/events', $this->envelope([
            'event_id' => 'evt-secret',
            'detail' => ['note' => 'commercially-sensitive-detail'],
        ]))->assertCreated();

        $raw = DB::table('loop_records')->where('event_id', 'evt-secret')->value('detail');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('commercially-sensitive-detail', $raw);
    }
}
