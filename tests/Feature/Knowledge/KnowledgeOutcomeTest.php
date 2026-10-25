<?php

namespace Tests\Feature\Knowledge;

use App\Models\KnowledgeOutcome;
use App\Models\LoopRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\TestCase;

class KnowledgeOutcomeTest extends TestCase
{
    use RefreshDatabase;
    use SignsKnowledgeRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSecrets();
        $this->travelTo(now()->startOfSecond()); // a frozen clock: second-precision timestamps cannot flake
    }

    private function report(string $eventId, string $outcome, ?string $actor = null): TestResponse
    {
        return $this->signedAs('infodot', 'POST', "/api/knowledge-recommendations/{$eventId}/outcome", [
            'outcome' => $outcome,
            'actor_ref' => $actor ?? $this->moderator(),
        ]);
    }

    public function test_the_first_outcome_is_recorded(): void
    {
        $this->decision('infodot:answer:123', ['event_id' => 'evt-1']);
        $actor = $this->moderator();

        $response = $this->report('evt-1', 'adopted', $actor)->assertCreated();

        $response->assertJsonPath('data.decision_event_id', 'evt-1')
            ->assertJsonPath('data.contribution_ref', 'infodot:answer:123')
            ->assertJsonPath('data.status', 'adopted');
        $this->assertNotNull($response->json('data.outcome_at'));
        $this->assertStringNotContainsString($actor, $response->getContent(), 'actor_ref is never echoed');

        $this->assertDatabaseHas('knowledge_outcomes', [
            'decision_event_id' => 'evt-1', 'contribution_ref' => 'infodot:answer:123', 'outcome' => 'adopted', 'actor_ref' => $actor,
        ]);

        $listed = $this->signedAs('infodot', 'GET', '/api/knowledge-recommendations?status=decided')->json('items.0');
        $this->assertSame('adopted', $listed['status']);
        $this->assertNotNull($listed['outcome_at']);
    }

    public function test_actor_ref_is_stored_as_given_and_not_checked_against_any_user_table(): void
    {
        $this->decision(overrides: ['event_id' => 'evt-1']);
        $this->assertDatabaseCount('users', 0);

        $actor = strtoupper($this->moderator());
        $this->report('evt-1', 'declined', $actor)->assertCreated();

        $this->assertSame($actor, KnowledgeOutcome::query()->value('actor_ref'));
    }

    public function test_the_same_outcome_again_is_a_no_op_200(): void
    {
        $this->decision(overrides: ['event_id' => 'evt-1']);

        $this->report('evt-1', 'adopted')->assertCreated();
        $this->report('evt-1', 'adopted')->assertOk()->assertJsonPath('data.status', 'adopted');
        $this->report('evt-1', 'adopted', $this->moderator())->assertOk();

        $this->assertDatabaseCount('knowledge_outcomes', 1);
    }

    public function test_a_flip_within_30_days_is_a_new_row_and_history_is_kept(): void
    {
        $this->decision(overrides: ['event_id' => 'evt-1']);

        $this->report('evt-1', 'adopted')->assertCreated();
        $this->travel(29)->days();
        $this->report('evt-1', 'declined')->assertCreated()->assertJsonPath('data.status', 'declined');
        $this->report('evt-1', 'adopted')->assertCreated()->assertJsonPath('data.status', 'adopted');
        $this->report('evt-1', 'adopted')->assertOk();

        $this->assertSame(['adopted', 'declined', 'adopted'], KnowledgeOutcome::query()->orderBy('id')->pluck('outcome')->all());
        $this->assertSame('adopted', $this->signedAs('infodot', 'GET', '/api/knowledge-recommendations?status=all')->json('items.0.status'));
    }

    public function test_a_flip_after_30_days_is_refused_409_and_the_no_op_still_works(): void
    {
        $this->decision(overrides: ['event_id' => 'evt-1']);
        $this->report('evt-1', 'adopted')->assertCreated();

        $this->travel(31)->days();

        $this->report('evt-1', 'declined')->assertStatus(409)->assertJsonPath('code', 'outcome_locked');
        $this->report('evt-1', 'adopted')->assertOk();
        $this->assertDatabaseCount('knowledge_outcomes', 1);
    }

    public function test_the_window_runs_from_the_first_outcome_not_the_latest_flip(): void
    {
        $this->decision(overrides: ['event_id' => 'evt-1']);
        $this->report('evt-1', 'adopted')->assertCreated();
        $this->travel(20)->days();
        $this->report('evt-1', 'declined')->assertCreated();
        $this->travel(20)->days();

        $this->report('evt-1', 'adopted')->assertStatus(409)->assertJsonPath('code', 'outcome_locked');
    }

    public function test_an_unknown_decision_is_404(): void
    {
        $this->report('nope', 'adopted')->assertStatus(404)->assertJsonPath('code', 'decision_not_found');
        $this->assertDatabaseCount('knowledge_outcomes', 0);
    }

    public function test_only_knowledge_decisions_can_receive_an_outcome(): void
    {
        $this->decision('machine-1', ['event_id' => 'evt-machine', 'subject_type' => 'machine']);
        $this->decision('infodot:answer:1', ['event_id' => 'evt-obs', 'stage' => 'observation']);
        $this->decision('infodot:answer:2', ['event_id' => 'evt-sig', 'signature' => 'engagement-decline']);
        $this->decision('infodot:answer:3', ['event_id' => 'participation:evt-p']);
        $this->decision('p', ['event_id' => 'evt-person', 'subject_type' => 'person']);
        $this->decision('infodot:answer:4', ['event_id' => 'evt-noverdict', 'detail' => ['verdict' => 'bogus']]);

        foreach (['evt-machine', 'evt-obs', 'evt-sig', 'participation:evt-p', 'evt-person', 'evt-noverdict'] as $eventId) {
            $this->report($eventId, 'adopted')->assertStatus(404)->assertJsonPath('code', 'decision_not_found');
        }

        $this->assertDatabaseCount('knowledge_outcomes', 0);
    }

    public function test_bad_bodies_are_422(): void
    {
        $this->decision(overrides: ['event_id' => 'evt-1']);
        $path = '/api/knowledge-recommendations/evt-1/outcome';

        $this->signedAs('infodot', 'POST', $path, ['outcome' => 'maybe', 'actor_ref' => $this->moderator()])->assertStatus(422)->assertJsonValidationErrors('outcome');
        $this->signedAs('infodot', 'POST', $path, ['actor_ref' => $this->moderator()])->assertStatus(422)->assertJsonValidationErrors('outcome');
        $this->signedAs('infodot', 'POST', $path, ['outcome' => 'adopted'])->assertStatus(422)->assertJsonValidationErrors('actor_ref');
        $this->signedAs('infodot', 'POST', $path, ['outcome' => 'adopted', 'actor_ref' => 'jane@example.com'])->assertStatus(422)->assertJsonValidationErrors('actor_ref');
        $this->signedAs('infodot', 'POST', $path, ['outcome' => 'adopted', 'actor_ref' => ['x']])->assertStatus(422);

        $this->assertDatabaseCount('knowledge_outcomes', 0);
    }

    public function test_an_outcome_leaves_the_decision_record_untouched(): void
    {
        $record = $this->decision(overrides: ['event_id' => 'evt-1']);
        $before = LoopRecord::query()->find($record->id)->getRawOriginal();

        $this->report('evt-1', 'adopted')->assertCreated();

        $this->assertEquals($before, LoopRecord::query()->find($record->id)->getRawOriginal());
        $this->assertSame(1, LoopRecord::query()->count());
    }

    public function test_outcomes_are_append_only(): void
    {
        $this->decision(overrides: ['event_id' => 'evt-1']);
        $this->report('evt-1', 'adopted')->assertCreated();
        $row = KnowledgeOutcome::query()->firstOrFail();

        try {
            $row->update(['outcome' => 'declined']);
            $this->fail('update must be refused');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        try {
            $row->delete();
            $this->fail('delete must be refused');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('adopted', $row->fresh()->outcome);
        $this->assertDatabaseCount('knowledge_outcomes', 1);
    }

    public function test_application_code_never_updates_or_deletes_outcome_rows(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)) as $file) {
            $source = (string) file_get_contents($file->getPathname());

            if ($file->getFilename() === 'KnowledgeOutcome.php' || ! str_contains($source, 'KnowledgeOutcome')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression('/KnowledgeOutcome::query\(\)(?:(?!;).)*->(?:update|delete|truncate|upsert|increment|decrement)\(/s', $source, $file->getFilename());
            $this->assertDoesNotMatchRegularExpression('/DB::table\(\s*[\'"]knowledge_outcomes/', $source, $file->getFilename());
        }
    }

    public function test_the_table_is_shaped_as_specified_and_the_migration_is_rerunnable(): void
    {
        $this->assertSame(
            ['id', 'decision_event_id', 'contribution_ref', 'outcome', 'actor_ref', 'decided_at', 'created_at'],
            Schema::getColumnListing('knowledge_outcomes'),
        );

        $indexed = collect(Schema::getIndexes('knowledge_outcomes'))->pluck('columns')->flatten()->all();
        $this->assertContains('decision_event_id', $indexed);
        $this->assertContains('decided_at', $indexed);

        $this->artisan('migrate')->assertSuccessful(); // guarded: nothing to do, no error
        $migration = require database_path('migrations/2026_10_23_100000_create_knowledge_outcomes_table.php');
        $migration->up(); // a second run is a no-op rather than "table exists"
        $this->assertTrue(Schema::hasTable('knowledge_outcomes'));
    }
}
