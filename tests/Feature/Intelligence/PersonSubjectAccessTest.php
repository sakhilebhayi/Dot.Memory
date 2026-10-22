<?php

namespace Tests\Feature\Intelligence;

use App\Models\LoopRecord;
use App\Models\ParticipationConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Participation\SignsParticipationRequests;
use Tests\TestCase;

/**
 * The generic Sanctum intelligence API and person subjects (LoopRecordAccess).
 *
 * Rule under test: a person subject is readable only with that person's current participation consent
 * (403 consent_required otherwise); participation rows are never returned by the generic API; person
 * subjects and the participation namespace cannot be written through it (403 person_subject_reserved);
 * every other subject type behaves exactly as before.
 */
class PersonSubjectAccessTest extends TestCase
{
    use RefreshDatabase;
    use SignsParticipationRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSecret();
        Sanctum::actingAs(User::factory()->create());
    }

    private function consent(string $uuid, bool $granted = true): void
    {
        ParticipationConsent::query()->create([
            'user_uuid' => $uuid, 'granted' => $granted, 'granted_at' => $granted ? now() : null,
            'revoked_at' => $granted ? null : now(), 'source' => 'infodot',
        ]);
    }

    /** A participation row, made through the real signed endpoint. */
    private function participate(string $uuid, string $topic = 'laravel-queues'): void
    {
        $this->signed('POST', '/api/participation/events', [
            'event_id' => 'e-'.Str::random(8), 'user_uuid' => $uuid, 'occurred_at' => now()->subHour()->toIso8601String(),
            'topic' => $topic, 'kind' => 'accepted', 'contribution_ref' => 'answer:7',
        ])->assertCreated();
    }

    /** A person row some other platform wrote before this rule existed. */
    private function legacyPersonRow(string $uuid, array $overrides = []): LoopRecord
    {
        return LoopRecord::factory()->create(array_merge([
            'platform' => 'dot-mines', 'subject_type' => 'person', 'subject_id' => $uuid, 'signature' => 'shift-pattern',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function envelope(array $overrides = []): array
    {
        return array_merge([
            'loop_id' => 'loop-'.Str::random(6), 'event_id' => 'evt-'.Str::random(8), 'platform' => 'dot-mines',
            'source' => 'telemetry', 'subject_type' => 'customer', 'subject_id' => '42',
            'signature' => 'engagement-decline', 'occurred_at' => now()->toISOString(),
        ], $overrides);
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function writeEndpoints(): array
    {
        return [
            'events' => ['events', []],
            'decisions' => ['decisions', ['confidence' => 0.5, 'risk' => 0.2, 'autonomy_level' => 'recommend']],
            'actions' => ['actions', ['action_kind' => 'nudge', 'executor_platform' => 'dot-mines', 'execution_status' => 'pending']],
            'outcomes' => ['outcomes', ['verdict' => 'improved']],
        ];
    }

    // ---- reads ----

    public function test_a_person_subject_without_consent_is_refused_not_answered(): void
    {
        $uuid = $this->uuid();
        $this->legacyPersonRow($uuid);
        $this->signed('PUT', '/api/participation/'.($other = $this->uuid()).'/consent', ['granted' => true])->assertOk();
        $this->participate($other);

        $this->getJson("/api/intelligence/context?subject_type=person&subject_id={$uuid}")
            ->assertForbidden()
            ->assertExactJson(['code' => 'consent_required', 'message' => 'This person has not opted in to participation records.']);

        // The same answer for someone nothing is held about: the refusal reveals nothing.
        $this->getJson('/api/intelligence/context?subject_type=person&subject_id='.$this->uuid())
            ->assertForbidden()->assertJsonPath('code', 'consent_required');
    }

    public function test_the_person_type_cannot_be_dodged_by_case_or_padding(): void
    {
        $uuid = $this->uuid();
        $this->legacyPersonRow($uuid);

        foreach (['PERSON', 'Person', ' person', 'person '] as $type) {
            $this->getJson('/api/intelligence/context?'.http_build_query(['subject_type' => $type, 'subject_id' => $uuid]))
                ->assertForbidden();
        }
    }

    public function test_an_upper_case_uuid_resolves_to_the_same_consent(): void
    {
        $uuid = $this->uuid();
        $this->consent($uuid);
        $this->legacyPersonRow($uuid);

        $this->getJson('/api/intelligence/context?subject_type=person&subject_id='.strtoupper($uuid))->assertOk();
    }

    public function test_consent_for_one_person_does_not_open_another(): void
    {
        $alice = $this->uuid();
        $bob = $this->uuid();
        $this->consent($alice);
        $this->legacyPersonRow($bob);

        $this->getJson("/api/intelligence/context?subject_type=person&subject_id={$bob}")->assertForbidden();
    }

    public function test_a_revoked_consent_is_refused(): void
    {
        $uuid = $this->uuid();
        $this->consent($uuid, false);
        $this->legacyPersonRow($uuid);

        $this->getJson("/api/intelligence/context?subject_type=person&subject_id={$uuid}")->assertForbidden();
    }

    public function test_with_current_consent_the_generic_api_answers_for_that_person(): void
    {
        $uuid = $this->uuid();
        $this->consent($uuid);
        $this->legacyPersonRow($uuid);

        $this->getJson("/api/intelligence/context?subject_type=person&subject_id={$uuid}")
            ->assertOk()
            ->assertJsonPath('data.known', true)
            ->assertJsonPath('data.observations', 1)
            ->assertJsonPath('data.timeline.0.signature', 'shift-pattern');
    }

    public function test_participation_rows_never_leak_through_the_generic_api_even_with_consent(): void
    {
        $uuid = $this->uuid();
        $this->consent($uuid);
        $this->participate($uuid, 'secret-topic');
        $this->participate($uuid, 'another-topic');
        // An infodot person row that is not shaped like a participation row is still the participation namespace.
        $this->legacyPersonRow($uuid, ['platform' => 'infodot', 'signature' => 'something-else']);

        foreach (["/api/intelligence/context?subject_type=person&subject_id={$uuid}",
            "/api/intelligence/context?subject_type=person&subject_id={$uuid}&platform=infodot",
            "/api/intelligence/context?subject_type=person&subject_id={$uuid}&signature=expertise:secret-topic"] as $url) {
            $response = $this->getJson($url)->assertOk();

            $this->assertFalse($response->json('data.known'), $url);
            $this->assertSame([], $response->json('data.timeline'), $url);
            $this->assertStringNotContainsString('expertise', $response->getContent());
            $this->assertStringNotContainsString('secret-topic', $response->getContent());
        }

        // ...while the signed participation endpoint still reads them.
        $this->signed('GET', "/api/participation/{$uuid}/context")->assertOk()->assertJsonCount(2, 'data.topics');
    }

    public function test_a_participation_row_is_not_returned_even_if_filed_under_another_subject_type(): void
    {
        $uuid = $this->uuid();
        LoopRecord::factory()->create([
            'platform' => 'dot-mines', 'subject_type' => 'customer', 'subject_id' => $uuid,
            'source' => 'participation:accepted', 'signature' => 'expertise:sql',
        ]);

        $this->getJson("/api/intelligence/context?subject_type=customer&subject_id={$uuid}")
            ->assertOk()->assertJsonPath('data.known', false);
    }

    public function test_other_subject_types_behave_exactly_as_before(): void
    {
        $this->consent($uuid = $this->uuid(), false);
        LoopRecord::factory()->create(['subject_type' => 'customer', 'subject_id' => '7', 'signature' => 'engagement-decline']);
        LoopRecord::factory()->create(['subject_type' => 'platform', 'subject_id' => $uuid, 'signature' => 'latency']);

        $this->getJson('/api/intelligence/context?subject_type=customer&subject_id=7')
            ->assertOk()->assertJsonPath('data.known', true)->assertJsonPath('data.observations', 1);
        // No consent needed for a non-person subject, even one whose id is a person's UUID.
        $this->getJson("/api/intelligence/context?subject_type=platform&subject_id={$uuid}")
            ->assertOk()->assertJsonPath('data.known', true);
    }

    // ---- writes ----

    /**
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('writeEndpoints')]
    public function test_person_subjects_cannot_be_written_through_the_generic_api(string $endpoint, array $extra): void
    {
        $withoutConsent = $this->uuid();
        $withConsent = $this->uuid();
        $this->consent($withConsent);

        foreach ([$withoutConsent, $withConsent] as $uuid) {
            $this->postJson("/api/intelligence/{$endpoint}", $this->envelope([...$extra, 'subject_type' => 'person', 'subject_id' => $uuid]))
                ->assertForbidden()->assertJsonPath('code', 'person_subject_reserved');
            $this->postJson("/api/intelligence/{$endpoint}", $this->envelope([...$extra, 'subject_type' => ' PERSON', 'subject_id' => $uuid]))
                ->assertForbidden();
        }

        $this->assertSame(0, LoopRecord::query()->count());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('writeEndpoints')]
    public function test_the_participation_namespace_cannot_be_written_through_the_generic_api(string $endpoint, array $extra): void
    {
        foreach (['source' => 'participation:accepted', 'event_id' => 'participation:infodot:x', 'loop_id' => 'Participation:infodot:x'] as $key => $value) {
            $this->postJson("/api/intelligence/{$endpoint}", $this->envelope([...$extra, $key => $value]))
                ->assertForbidden()->assertJsonPath('code', 'person_subject_reserved');
        }

        $this->assertSame(0, LoopRecord::query()->count());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('writeEndpoints')]
    public function test_other_subject_types_can_still_be_written(string $endpoint, array $extra): void
    {
        $this->postJson("/api/intelligence/{$endpoint}", $this->envelope($extra))->assertCreated();

        $this->assertSame(1, LoopRecord::query()->count());
    }

    public function test_an_existing_participation_or_person_row_cannot_be_overwritten_by_replaying_its_event_id(): void
    {
        $uuid = $this->uuid();
        $this->consent($uuid);
        $this->participate($uuid);
        $this->legacyPersonRow($uuid, ['event_id' => 'legacy-1']);
        $participationId = LoopRecord::query()->where('source', 'like', 'participation:%')->value('event_id');
        $before = LoopRecord::query()->orderBy('id')->get()->map->getAttributes()->all();

        // Neither replay may re-label a row as a harmless customer record, which would make it readable.
        $this->postJson('/api/intelligence/events', $this->envelope(['event_id' => $participationId]))->assertForbidden();
        $this->postJson('/api/intelligence/events', $this->envelope(['event_id' => 'legacy-1']))->assertForbidden();

        $this->assertSame($before, LoopRecord::query()->orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_revoking_consent_purges_person_rows_from_every_platform(): void
    {
        $uuid = $this->uuid();
        $other = $this->uuid();
        $this->signed('PUT', "/api/participation/{$uuid}/consent", ['granted' => true])->assertOk();
        $this->participate($uuid);
        $this->legacyPersonRow($uuid);
        $this->legacyPersonRow($other);

        $this->signed('PUT', "/api/participation/{$uuid}/consent", ['granted' => false])
            ->assertOk()->assertJsonPath('data.purged', 2);

        $this->assertSame([$other], DB::table('loop_records')->pluck('subject_id')->all());
    }

    public function test_erase_removes_person_rows_from_every_platform(): void
    {
        $uuid = $this->uuid();
        $this->legacyPersonRow($uuid);

        $this->signed('DELETE', "/api/participation/{$uuid}")->assertOk()->assertJsonPath('data.records_deleted', 1);
        $this->assertSame(0, LoopRecord::query()->count());
    }
}
