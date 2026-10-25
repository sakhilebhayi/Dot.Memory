<?php

namespace Tests\Feature\Participation;

use App\Models\LoopRecord;
use App\Models\ParticipationConsent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneParticipationTest extends TestCase
{
    use RefreshDatabase;
    use SignsParticipationRequests;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function row(string $uuid, array $overrides = []): LoopRecord
    {
        return LoopRecord::factory()->create(array_merge([
            'loop_id' => 'participation:infodot:'.uniqid(),
            'event_id' => 'participation:infodot:'.uniqid(),
            'stage' => 'observation',
            'platform' => 'infodot',
            'source' => 'participation:accepted',
            'subject_type' => 'person',
            'subject_id' => $uuid,
            'signature' => 'expertise:sql',
            'occurred_at' => now()->subDay(),
            'detail' => ['contribution_ref' => 'answer:1'],
        ], $overrides));
    }

    private function consent(string $uuid, bool $granted, ?\DateTimeInterface $at = null): void
    {
        $consent = ParticipationConsent::query()->create([
            'user_uuid' => $uuid, 'granted' => $granted, 'granted_at' => $granted ? now() : null,
            'revoked_at' => $granted ? null : ($at ?? now()), 'source' => 'infodot',
        ]);

        if ($at !== null) {
            DB::table('participation_consents')->where('id', $consent->id)->update(['updated_at' => $at]);
        }
    }

    public function test_it_deletes_rows_past_the_retention_window_and_keeps_fresh_ones(): void
    {
        config(['participation.retention_days' => 30]);
        $uuid = $this->uuid();
        $this->consent($uuid, true);
        $this->row($uuid, ['occurred_at' => now()->subDays(31)]);
        $this->row($uuid, ['occurred_at' => now()->subDays(29)]);

        $this->artisan('participation:prune')
            ->expectsOutputToContain('1 expired, 0 without consent, 0 stale consent record(s)')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('loop_records')->count());
    }

    public function test_it_deletes_rows_of_people_without_a_current_grant(): void
    {
        $revoked = $this->uuid();
        $never = $this->uuid();
        $granted = $this->uuid();
        $this->consent($revoked, false);
        $this->consent($granted, true);
        $this->row($revoked);
        $this->row($never);
        $this->row($granted);

        $this->artisan('participation:prune')->expectsOutputToContain('2 without consent')->assertSuccessful();

        $this->assertSame([$granted], DB::table('loop_records')->pluck('subject_id')->all());
    }

    public function test_it_drops_old_revoked_consent_records_but_not_recent_ones_or_grants(): void
    {
        config(['participation.retention_days' => 30]);
        $old = $this->uuid();
        $recent = $this->uuid();
        $granted = $this->uuid();
        $this->consent($old, false, now()->subDays(40));
        $this->consent($recent, false, now()->subDays(5));
        $this->consent($granted, true);

        $this->artisan('participation:prune')->expectsOutputToContain('1 stale consent record(s)')->assertSuccessful();

        $this->assertEqualsCanonicalizing([$recent, $granted], DB::table('participation_consents')->pluck('user_uuid')->all());
    }

    public function test_it_is_idempotent_and_prints_counts_only(): void
    {
        config(['participation.retention_days' => 30]);
        $uuid = $this->uuid();
        $this->consent($uuid, true);
        $this->row($uuid, ['occurred_at' => now()->subDays(40)]);

        $this->artisan('participation:prune')->expectsOutputToContain('1 expired')->assertSuccessful();
        $this->artisan('participation:prune')
            ->expectsOutput('Pruned participation: 0 expired, 0 without consent, 0 stale consent record(s).')
            ->doesntExpectOutputToContain($uuid)
            ->assertSuccessful();
    }

    public function test_it_leaves_loop_records_that_are_not_participation_rows_alone(): void
    {
        config(['participation.retention_days' => 30]);
        LoopRecord::factory()->create(['platform' => 'dot-mines', 'subject_type' => 'customer', 'occurred_at' => now()->subDays(900)]);
        $this->row($this->uuid(), ['platform' => 'infodot', 'source' => 'guardian', 'signature' => 'engagement-decline', 'occurred_at' => now()->subDays(900)]);

        $this->artisan('participation:prune')->assertSuccessful();

        $this->assertSame(2, DB::table('loop_records')->count());
    }

    public function test_it_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'participation:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('10 3 * * *', $events->first()->expression);
    }
}
