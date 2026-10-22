<?php

namespace Tests\Feature\Participation;

use App\Models\LoopRecord;
use App\Models\ParticipationConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParticipationApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsParticipationRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSecret();
    }

    private function grant(string $uuid): void
    {
        $this->signed('PUT', "/api/participation/{$uuid}/consent", ['granted' => true])->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function event(string $uuid, array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt-'.uniqid(),
            'user_uuid' => $uuid,
            'occurred_at' => now()->subHour()->toIso8601String(),
            'topic' => 'laravel-queues',
            'kind' => 'accepted',
            'contribution_ref' => 'answer:1042',
        ], $overrides);
    }

    // ---- consent ----

    public function test_consent_is_off_by_default_and_can_be_granted(): void
    {
        $uuid = $this->uuid();

        $this->signed('GET', "/api/participation/{$uuid}/context")->assertStatus(403)->assertExactJson([
            'code' => 'consent_required',
            'message' => 'This person has not opted in to participation records.',
        ]);

        $this->signed('PUT', "/api/participation/{$uuid}/consent", ['granted' => true])
            ->assertOk()
            ->assertJsonPath('data.consent.granted', true)
            ->assertJsonPath('data.consent.source', 'infodot')
            ->assertJsonPath('data.purged', 0);

        $this->assertDatabaseHas('participation_consents', ['user_uuid' => $uuid, 'granted' => true]);
        $this->signed('GET', "/api/participation/{$uuid}/context")->assertOk()->assertJsonPath('data.consent.granted', true);
    }

    public function test_granting_twice_keeps_the_first_grant_time(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);
        $first = ParticipationConsent::query()->where('user_uuid', $uuid)->value('granted_at');

        $this->travel(2)->hours();
        $this->grant($uuid);

        $this->assertSame(1, ParticipationConsent::query()->count());
        $this->assertEquals($first, ParticipationConsent::query()->where('user_uuid', $uuid)->value('granted_at'));
    }

    public function test_consent_body_must_be_a_strict_boolean_and_nothing_else(): void
    {
        $uuid = $this->uuid();

        $this->signed('PUT', "/api/participation/{$uuid}/consent", [])->assertStatus(422)->assertJsonPath('code', 'invalid_request');
        $this->signed('PUT', "/api/participation/{$uuid}/consent", ['granted' => 'yes'])->assertStatus(422);
        $this->signed('PUT', "/api/participation/{$uuid}/consent", ['granted' => 1])->assertStatus(422);
        $this->signed('PUT', "/api/participation/{$uuid}/consent", ['granted' => true, 'email' => 'a@b.c'])->assertStatus(422);
        $this->signed('PUT', '/api/participation/not-a-uuid/consent', ['granted' => true])->assertStatus(422)->assertJsonPath('code', 'invalid_user_uuid');

        $this->assertSame(0, ParticipationConsent::query()->count());
    }

    public function test_revoking_consent_purges_that_persons_records_immediately_and_only_theirs(): void
    {
        $alice = $this->uuid();
        $bob = $this->uuid();
        $this->grant($alice);
        $this->grant($bob);
        $this->signed('POST', '/api/participation/events', $this->event($alice))->assertCreated();
        $this->signed('POST', '/api/participation/events', $this->event($alice, ['topic' => 'sql']))->assertCreated();
        $this->signed('POST', '/api/participation/events', $this->event($bob))->assertCreated();

        $this->signed('PUT', "/api/participation/{$alice}/consent", ['granted' => false])
            ->assertOk()
            ->assertJsonPath('data.consent.granted', false)
            ->assertJsonPath('data.purged', 2);

        $this->assertSame(0, LoopRecord::query()->where('subject_id', $alice)->count());
        $this->assertSame(1, LoopRecord::query()->where('subject_id', $bob)->count());
        $this->assertNotNull(ParticipationConsent::query()->where('user_uuid', $alice)->value('revoked_at'));
        $this->signed('GET', "/api/participation/{$alice}/context")->assertStatus(403);
        $this->signed('POST', '/api/participation/events', $this->event($alice))->assertStatus(403)->assertJsonPath('code', 'consent_required');
    }

    public function test_revoking_for_an_unknown_person_records_the_refusal_and_purges_nothing(): void
    {
        $uuid = $this->uuid();

        $this->signed('PUT', "/api/participation/{$uuid}/consent", ['granted' => false])->assertOk()->assertJsonPath('data.purged', 0);
        $this->signed('POST', '/api/participation/events', $this->event($uuid))->assertStatus(403);
    }

    public function test_a_person_can_opt_back_in_after_revoking_and_starts_from_zero(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);
        $this->signed('POST', '/api/participation/events', $this->event($uuid))->assertCreated();
        $this->signed('PUT', "/api/participation/{$uuid}/consent", ['granted' => false])->assertOk();
        $this->grant($uuid);

        $this->assertNull(ParticipationConsent::query()->where('user_uuid', $uuid)->value('revoked_at'));
        $this->signed('GET', "/api/participation/{$uuid}/context")->assertOk()->assertJsonPath('data.topics', []);
    }

    // ---- events ----

    public function test_an_event_without_consent_is_refused_and_stores_nothing(): void
    {
        $uuid = $this->uuid();

        $this->signed('POST', '/api/participation/events', $this->event($uuid))
            ->assertStatus(403)
            ->assertJsonPath('code', 'consent_required');

        $this->assertSame(0, LoopRecord::query()->count());
    }

    public function test_an_event_is_stored_as_a_minimal_loop_record(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);
        $event = $this->event($uuid, ['event_id' => 'answer.accepted:77', 'contribution_ref' => 'answer:77', 'kind' => 'verified']);

        $this->signed('POST', '/api/participation/events', $event)
            ->assertCreated()
            ->assertJsonPath('data.event_id', 'answer.accepted:77')
            ->assertJsonPath('data.topic', 'laravel-queues')
            ->assertJsonPath('data.kind', 'verified');

        $record = LoopRecord::query()->sole();
        $this->assertSame('infodot', $record->platform);
        $this->assertSame('person', $record->subject_type);
        $this->assertSame($uuid, $record->subject_id);
        $this->assertSame('expertise:laravel-queues', $record->signature);
        $this->assertSame('observation', $record->stage);
        $this->assertSame('participation:verified', $record->source);
        $this->assertSame(['contribution_ref' => 'answer:77'], $record->detail);
    }

    public function test_stored_rows_contain_no_content_no_labels_and_no_plaintext_detail(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);
        $this->signed('POST', '/api/participation/events', $this->event($uuid, ['contribution_ref' => 'answer:555']))->assertCreated();

        $raw = (array) DB::table('loop_records')->first();

        // Nothing that could name or describe the person or the contribution.
        foreach (['subject_label', 'team_id', 'user_id', 'confidence', 'risk', 'autonomy_level', 'action_kind', 'executor_platform', 'mechanic_ref', 'verdict', 'measure'] as $column) {
            $this->assertNull($raw[$column], "{$column} must stay empty on a participation row.");
        }

        // The only thing in detail is the opaque ref, and it is encrypted at rest.
        $this->assertStringNotContainsString('answer:555', (string) $raw['detail']);
        $this->assertSame(['contribution_ref' => 'answer:555'], LoopRecord::query()->sole()->detail);

        // The whole row, serialised, carries no free text beyond the slug, the kind and the UUID.
        $flat = json_encode(array_diff_key($raw, ['detail' => 1]));
        $this->assertStringNotContainsString('@', (string) $flat);
    }

    public function test_events_are_idempotent_on_event_id(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);
        $event = $this->event($uuid, ['event_id' => 'same-event']);

        $first = $this->signed('POST', '/api/participation/events', $event)->assertCreated();
        $replay = $this->signed('POST', '/api/participation/events', $event)->assertOk();

        $this->assertSame($first->json('data'), $replay->json('data'));
        $this->assertSame(1, LoopRecord::query()->count());
        $this->signed('GET', "/api/participation/{$uuid}/context")->assertJsonPath('data.topics.0.count', 1);
    }

    public function test_reusing_an_event_id_for_a_different_event_is_a_conflict(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);
        $this->signed('POST', '/api/participation/events', $this->event($uuid, ['event_id' => 'dup']))->assertCreated();

        $this->signed('POST', '/api/participation/events', $this->event($uuid, ['event_id' => 'dup', 'topic' => 'sql']))
            ->assertStatus(409)
            ->assertJsonPath('code', 'event_conflict');

        $this->assertSame(1, LoopRecord::query()->count());
    }

    public function test_a_participation_event_id_cannot_collide_with_the_intelligence_api(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);
        LoopRecord::factory()->create(['event_id' => 'shared-id']);

        $this->signed('POST', '/api/participation/events', $this->event($uuid, ['event_id' => 'shared-id']))->assertCreated();

        $this->assertSame(2, LoopRecord::query()->count());
    }

    public function test_invalid_topic_kind_and_shape_are_422_and_store_nothing(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);

        foreach ([
            ['topic' => 'Laravel Queues'],
            ['topic' => 'laravel_queues'],
            ['topic' => '-leading'],
            ['topic' => 'trailing-'],
            ['topic' => 'double--dash'],
            ['topic' => str_repeat('a', 65)],
            ['topic' => 'how do i fix my invoice for jane@example.com'],
            ['kind' => 'upvoted'],
            ['kind' => 'ACCEPTED'],
            ['user_uuid' => 'not-a-uuid'],
            ['occurred_at' => 'yesterday-ish'],
            ['occurred_at' => now()->addDays(3)->toIso8601String()],
            ['occurred_at' => now()->subDays(400)->toIso8601String()],
            ['contribution_ref' => 'my private question about salary'],
            ['contribution_ref' => 'jane@example.com'],
            ['event_id' => 'has spaces'],
        ] as $override) {
            $this->signed('POST', '/api/participation/events', $this->event($uuid, $override))
                ->assertStatus(422)
                ->assertJsonPath('code', 'invalid_request');
        }

        $this->assertSame(0, LoopRecord::query()->count());
    }

    public function test_missing_fields_and_non_object_bodies_are_422(): void
    {
        $this->grant($this->uuid());

        $this->signed('POST', '/api/participation/events', [])->assertStatus(422);
        $this->signed('POST', '/api/participation/events', [1, 2])->assertStatus(422);
        $this->signed('POST', '/api/participation/events', ['event_id' => 'only'])->assertStatus(422)->assertJsonPath('code', 'invalid_request');
    }

    public function test_content_fields_are_rejected_not_silently_dropped(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);

        foreach (['title' => 'How to fix X', 'body' => 'long text', 'email' => 'a@b.co', 'name' => 'Jane', 'detail' => ['note' => 'x'], 'team_id' => 3, 'subject_label' => 'Jane'] as $field => $value) {
            $response = $this->signed('POST', '/api/participation/events', $this->event($uuid, [$field => $value]))
                ->assertStatus(422)
                ->assertJsonPath('code', 'invalid_request');

            $this->assertStringNotContainsString('a@b.co', $response->getContent());
        }

        $this->assertSame(0, LoopRecord::query()->count());
    }

    public function test_a_batch_is_recorded_atomically_and_limited(): void
    {
        $alice = $this->uuid();
        $bob = $this->uuid();
        $this->grant($alice);

        // Bob has not opted in: nothing in the batch is stored.
        $this->signed('POST', '/api/participation/events', ['events' => [$this->event($alice), $this->event($bob)]])
            ->assertStatus(403)->assertJsonPath('code', 'consent_required');
        $this->assertSame(0, LoopRecord::query()->count());

        $this->signed('POST', '/api/participation/events', ['events' => [$this->event($alice), $this->event($alice, ['topic' => 'sql'])]])
            ->assertCreated()
            ->assertJsonCount(2, 'data');
        $this->assertSame(2, LoopRecord::query()->count());

        // One invalid event rejects the whole batch.
        $this->signed('POST', '/api/participation/events', ['events' => [$this->event($alice), $this->event($alice, ['kind' => 'nope'])]])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['events.1']]);
        $this->assertSame(2, LoopRecord::query()->count());

        config(['participation.max_events_per_request' => 2]);
        $this->signed('POST', '/api/participation/events', ['events' => [$this->event($alice), $this->event($alice), $this->event($alice)]])
            ->assertStatus(422)->assertJsonPath('code', 'too_many_events');

        // A batch cannot mix a single event's fields in, and the same id twice with different content conflicts.
        $this->signed('POST', '/api/participation/events', ['events' => [$this->event($alice)], 'topic' => 'x'])->assertStatus(422);
        $this->signed('POST', '/api/participation/events', ['events' => [$this->event($alice, ['event_id' => 'twin']), $this->event($alice, ['event_id' => 'twin', 'topic' => 'sql'])]])
            ->assertStatus(409);
    }

    // ---- context ----

    public function test_context_returns_topic_counts_and_nothing_else(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);

        foreach ([['laravel-queues', 'accepted'], ['laravel-queues', 'accepted'], ['laravel-queues', 'verified'], ['sql', 'accepted']] as [$topic, $kind]) {
            $this->signed('POST', '/api/participation/events', $this->event($uuid, ['topic' => $topic, 'kind' => $kind]))->assertCreated();
        }

        $response = $this->signed('GET', "/api/participation/{$uuid}/context")->assertOk();

        $response->assertJsonPath('data.user_uuid', $uuid)
            ->assertJsonPath('data.consent.granted', true)
            ->assertJsonPath('data.topics.0', ['topic' => 'laravel-queues', 'count' => 3, 'kinds' => ['accepted' => 2, 'verified' => 1]])
            ->assertJsonPath('data.topics.1', ['topic' => 'sql', 'count' => 1, 'kinds' => ['accepted' => 1, 'verified' => 0]]);

        $this->assertSame(['user_uuid', 'consent', 'topics'], array_keys($response->json('data')));
        $this->assertSame(['topic', 'count', 'kinds'], array_keys($response->json('data.topics.0')));

        // No contribution refs, no timestamps of individual events, no signatures leak.
        $this->assertStringNotContainsString('answer:1042', $response->getContent());
        $this->assertStringNotContainsString('expertise:', $response->getContent());
        $this->assertStringNotContainsString('evt-', $response->getContent());
    }

    public function test_context_is_scoped_to_the_named_person(): void
    {
        $alice = $this->uuid();
        $bob = $this->uuid();
        $this->grant($alice);
        $this->grant($bob);
        $this->signed('POST', '/api/participation/events', $this->event($alice, ['topic' => 'sql']))->assertCreated();

        $this->signed('GET', "/api/participation/{$bob}/context")->assertOk()->assertJsonPath('data.topics', []);
    }

    public function test_there_is_no_endpoint_that_looks_a_topic_up_across_people(): void
    {
        $this->grant($this->uuid());

        foreach (['/api/participation', '/api/participation/topics', '/api/participation/topics/sql', '/api/participation/search?topic=sql', '/api/participation/events'] as $uri) {
            $this->assertContains($this->signed('GET', $uri)->getStatusCode(), [404, 405], $uri);
        }
    }

    // ---- erase ----

    public function test_erase_hard_deletes_records_and_the_consent_record_and_is_idempotent(): void
    {
        $alice = $this->uuid();
        $bob = $this->uuid();
        $this->grant($alice);
        $this->grant($bob);
        $this->signed('POST', '/api/participation/events', $this->event($alice))->assertCreated();
        $this->signed('POST', '/api/participation/events', $this->event($alice, ['topic' => 'sql']))->assertCreated();
        $this->signed('POST', '/api/participation/events', $this->event($bob))->assertCreated();

        $this->signed('DELETE', "/api/participation/{$alice}")
            ->assertOk()
            ->assertExactJson(['data' => ['user_uuid' => $alice, 'records_deleted' => 2, 'consent_deleted' => true]]);

        $this->assertSame(0, DB::table('loop_records')->where('subject_id', $alice)->count());
        $this->assertSame(0, DB::table('participation_consents')->where('user_uuid', $alice)->count());
        $this->assertSame(1, DB::table('loop_records')->where('subject_id', $bob)->count());
        $this->assertSame(1, DB::table('participation_consents')->where('user_uuid', $bob)->count());

        $this->signed('DELETE', "/api/participation/{$alice}")
            ->assertOk()
            ->assertExactJson(['data' => ['user_uuid' => $alice, 'records_deleted' => 0, 'consent_deleted' => false]]);

        $this->signed('GET', "/api/participation/{$alice}/context")->assertStatus(403);
    }

    public function test_erase_also_removes_any_other_infodot_person_row_for_that_person(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);
        LoopRecord::factory()->create(['platform' => 'infodot', 'subject_type' => 'person', 'subject_id' => $uuid, 'event_id' => 'sanctum-made']);

        $this->signed('DELETE', "/api/participation/{$uuid}")->assertOk()->assertJsonPath('data.records_deleted', 1);
        $this->assertSame(0, LoopRecord::query()->count());
    }

    public function test_nothing_is_soft_deleted_or_cached(): void
    {
        $uuid = $this->uuid();
        $this->grant($uuid);
        $this->signed('POST', '/api/participation/events', $this->event($uuid))->assertCreated();
        $this->signed('GET', "/api/participation/{$uuid}/context")->assertOk();
        $this->signed('DELETE', "/api/participation/{$uuid}")->assertOk();

        $this->assertFalse(in_array('deleted_at', Schema::getColumnListing('loop_records'), true));
        $this->assertFalse(in_array('deleted_at', Schema::getColumnListing('participation_consents'), true));
        $this->assertSame(0, DB::table('loop_records')->count());
        $this->assertSame(0, DB::table('participation_consents')->count());
        $this->assertSame(0, DB::table('cache')->count());
    }
}
