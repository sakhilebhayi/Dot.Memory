<?php

namespace Tests\Feature\Knowledge;

use App\Models\KnowledgeOutcome;
use App\Models\LoopRecord;
use App\Models\User;
use App\Services\Participation\ParticipationStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnowledgeRecommendationListTest extends TestCase
{
    use RefreshDatabase;
    use SignsKnowledgeRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSecrets();
        $this->travelTo(now()->startOfSecond()); // a frozen clock: second-precision timestamps cannot flake
    }

    private function list(string $query = ''): TestResponse
    {
        return $this->signedAs('infodot', 'GET', '/api/knowledge-recommendations'.$query);
    }

    public function test_the_list_has_the_documented_shape(): void
    {
        $record = $this->decision('infodot:answer:123', ['event_id' => 'evt-shape', 'confidence' => 0.75]);

        $this->list()->assertOk()->assertExactJson([
            'items' => [[
                'decision_event_id' => 'evt-shape',
                'contribution_ref' => 'infodot:answer:123',
                'verdict' => 'recommend_for_library',
                'confidence' => 0.75,
                'rationale' => [
                    ['rule' => 'accepted-answer', 'effect' => '+0.4', 'detail' => 'The asker accepted this answer.'],
                    ['rule' => 'verified', 'effect' => '+0.3', 'detail' => 'Two moderators verified it.'],
                ],
                'rule_version' => 'kc-rules-1',
                'decided_at' => $record->occurred_at->toISOString(),
                'status' => 'open',
                'outcome_at' => null,
            ]],
            'next_cursor' => null,
        ]);
    }

    public function test_a_decision_written_through_the_sanctum_api_is_listed_and_idempotent_on_event_id(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $body = [
            'loop_id' => 'kc-1', 'event_id' => 'evt-brain-1', 'platform' => 'dot-brain', 'source' => 'knowledge-recommender',
            'subject_type' => 'infodot-contribution', 'subject_id' => 'infodot:answer:123', 'signature' => 'knowledge-candidate',
            'confidence' => 0.9, 'risk' => 0.05, 'autonomy_level' => 'recommend', 'occurred_at' => now()->toISOString(),
            'detail' => ['verdict' => 'watch', 'rule_version' => 'kc-1', 'rationale' => [['rule' => 'age', 'effect' => '-0.1', 'detail' => 'Posted yesterday.']]],
        ];

        $this->postJson('/api/intelligence/decisions', $body)->assertCreated();
        $this->postJson('/api/intelligence/decisions', $body)->assertOk(); // replay

        $items = $this->list('?status=all')->assertOk()->json('items');
        $this->assertCount(1, $items);
        $this->assertSame('watch', $items[0]['verdict']);
        $this->assertSame('infodot:answer:123', $items[0]['contribution_ref']);
        $this->assertSame('Posted yesterday.', $items[0]['rationale'][0]['detail']);
    }

    public function test_only_whitelisted_fields_leave_and_the_raw_detail_never_does(): void
    {
        $this->decision('infodot:answer:9', ['detail' => ['internal_note' => 'SECRET-NOTE', 'features' => ['x' => 1]]]);

        $body = $this->list()->assertOk()->getContent();

        $this->assertStringNotContainsString('SECRET-NOTE', $body);
        $this->assertStringNotContainsString('internal_note', $body);
        $this->assertStringNotContainsString('features', $body);
        $this->assertStringNotContainsString('actor_ref', $body);
    }

    public function test_a_plain_string_rationale_and_the_nested_decision_shape_are_understood(): void
    {
        $this->decision('infodot:answer:1', ['detail' => [
            'verdict' => null,
            'rationale' => null,
            'rule_version' => null,
            'decision' => ['verdict' => 'watch', 'rationale' => ['too new to judge'], 'rule_version' => 'v2'],
        ]]);

        $item = $this->list()->assertOk()->json('items.0');

        $this->assertSame('watch', $item['verdict']);
        $this->assertSame([['rule' => null, 'effect' => null, 'detail' => 'too new to judge']], $item['rationale']);
        $this->assertSame('v2', $item['rule_version']);
    }

    public function test_only_infodot_contribution_knowledge_candidate_decisions_ever_appear(): void
    {
        $mine = $this->decision('infodot:answer:1', ['event_id' => 'evt-mine']);

        // Everything below must be invisible, whatever else it looks like.
        $this->decision('machine-9', ['subject_type' => 'machine']);
        $this->decision('customer-1', ['subject_type' => 'customer']);
        $this->decision('4b8f1c52-7d0e-4a39-9f6b-2c1e8a7d5b30', ['subject_type' => 'person']);
        $this->decision('infodot:answer:2', ['signature' => 'engagement-decline']);
        $this->decision('infodot:answer:3', ['stage' => 'observation']);
        $this->decision('infodot:answer:4', ['stage' => 'outcome']);
        $this->decision('infodot:answer:5', ['subject_type' => 'infodot-contribution-x']);
        $this->decision('infodot:answer:6', ['event_id' => 'participation:evt-6']);
        $this->decision('infodot:answer:7', ['source' => 'participation:accepted']);
        $this->decision('infodot:answer:8', ['loop_id' => 'participation:loop-8']);
        $this->decision('infodot:answer:10', ['platform' => ParticipationStore::PLATFORM, 'subject_type' => 'person']);
        $this->decision('infodot:answer:11', ['detail' => ['verdict' => 'adopt_immediately']]); // not a recommendation verdict
        $this->decision('infodot:answer:12', ['detail' => ['verdict' => null]]);

        $items = $this->list('?status=all&limit=100')->assertOk()->json('items');

        $this->assertSame(['evt-mine'], array_column($items, 'decision_event_id'));
        $this->assertSame($mine->event_id, $items[0]['decision_event_id']);
    }

    public function test_an_overlong_database_holds_nothing_else_even_for_other_verdict_filters(): void
    {
        $this->decision('machine-9', ['subject_type' => 'machine']);
        $this->decision('p', ['subject_type' => 'person']);

        foreach (['open', 'decided', 'all'] as $status) {
            $this->list('?status='.$status)->assertOk()->assertExactJson(['items' => [], 'next_cursor' => null]);
        }
    }

    public function test_status_is_derived_from_the_latest_outcome_row(): void
    {
        $a = $this->decision('infodot:answer:1', ['event_id' => 'evt-a', 'occurred_at' => now()->subHours(3)]);
        $b = $this->decision('infodot:answer:2', ['event_id' => 'evt-b', 'occurred_at' => now()->subHours(2)]);
        $this->decision('infodot:answer:3', ['event_id' => 'evt-c', 'occurred_at' => now()->subHour()]);

        // a: adopted, then flipped to declined later. b: adopted.
        KnowledgeOutcome::query()->create(['decision_event_id' => 'evt-a', 'contribution_ref' => $a->subject_id, 'outcome' => 'adopted', 'actor_ref' => $this->moderator(), 'decided_at' => now()->subMinutes(30)]);
        KnowledgeOutcome::query()->create(['decision_event_id' => 'evt-a', 'contribution_ref' => $a->subject_id, 'outcome' => 'declined', 'actor_ref' => $this->moderator(), 'decided_at' => now()->subMinutes(10)]);
        KnowledgeOutcome::query()->create(['decision_event_id' => 'evt-b', 'contribution_ref' => $b->subject_id, 'outcome' => 'adopted', 'actor_ref' => $this->moderator(), 'decided_at' => now()->subMinutes(20)]);

        $all = collect($this->list('?status=all')->assertOk()->json('items'))->keyBy('decision_event_id');
        $this->assertSame('open', $all['evt-c']['status']);
        $this->assertNull($all['evt-c']['outcome_at']);
        $this->assertSame('declined', $all['evt-a']['status']);
        $this->assertSame(now()->subMinutes(10)->startOfSecond()->toISOString(), $all['evt-a']['outcome_at']);
        $this->assertSame('adopted', $all['evt-b']['status']);

        $this->assertSame(['evt-c'], array_column($this->list()->json('items'), 'decision_event_id'), 'status defaults to open');
        $this->assertSame(['evt-c'], array_column($this->list('?status=open')->json('items'), 'decision_event_id'));
        $this->assertEqualsCanonicalizing(['evt-a', 'evt-b'], array_column($this->list('?status=decided')->json('items'), 'decision_event_id'));
    }

    public function test_the_verdict_filter(): void
    {
        $this->decision('infodot:answer:1', ['event_id' => 'evt-r', 'detail' => ['verdict' => 'recommend_for_library']]);
        $this->decision('infodot:answer:2', ['event_id' => 'evt-w', 'detail' => ['verdict' => 'watch']]);
        $this->decision('infodot:answer:3', ['event_id' => 'evt-n', 'detail' => ['verdict' => 'do_not_recommend']]);

        $this->assertSame(['evt-r'], array_column($this->list('?verdict=recommend_for_library')->json('items'), 'decision_event_id'));
        $this->assertSame(['evt-w'], array_column($this->list('?verdict=watch')->json('items'), 'decision_event_id'));
        $this->assertCount(3, $this->list()->json('items'));
    }

    public function test_it_is_newest_first_and_paginates_with_a_cursor_with_a_verdict_filter_too(): void
    {
        foreach (range(1, 7) as $i) {
            $this->decision("infodot:answer:{$i}", [
                'event_id' => "evt-{$i}",
                'occurred_at' => now()->subHours(20 - $i),
                'detail' => ['verdict' => $i % 2 === 0 ? 'watch' : 'recommend_for_library'],
            ]);
        }
        // Three with the same timestamp straddle a page boundary: the (time, id) cursor must not skip or repeat.
        $tied = now()->subHours(12);
        foreach ([8, 9, 10] as $i) {
            $this->decision("infodot:answer:{$i}", ['event_id' => "evt-{$i}", 'occurred_at' => $tied]);
        }

        $seen = [];
        $cursor = null;
        do {
            $page = $this->list('?status=all&limit=2'.($cursor ? '&cursor='.$cursor : ''))->assertOk()->json();
            $this->assertLessThanOrEqual(2, count($page['items']));
            array_push($seen, ...array_column($page['items'], 'decision_event_id'));
            $cursor = $page['next_cursor'];
        } while ($cursor !== null);

        $this->assertSame(['evt-10', 'evt-9', 'evt-8', 'evt-7', 'evt-6', 'evt-5', 'evt-4', 'evt-3', 'evt-2', 'evt-1'], $seen);

        // Newest first overall.
        $all = $this->list('?status=all&limit=100')->json('items');
        $times = array_column($all, 'decided_at');
        $sorted = $times;
        rsort($sorted);
        $this->assertSame($sorted, $times);

        // Filtered pagination: only 'watch' rows (2, 4, 6), two per page.
        $first = $this->list('?verdict=watch&status=all&limit=2')->json();
        $this->assertSame(['evt-6', 'evt-4'], array_column($first['items'], 'decision_event_id'));
        $this->assertNotNull($first['next_cursor']);
        $second = $this->list('?verdict=watch&status=all&limit=2&cursor='.$first['next_cursor'])->json();
        $this->assertSame(['evt-2'], array_column($second['items'], 'decision_event_id'));
        $this->assertNull($second['next_cursor']);
    }

    public function test_a_bad_filter_limit_or_cursor_is_422(): void
    {
        $this->list('?status=banana')->assertStatus(422);
        $this->list('?verdict=adopt_immediately')->assertStatus(422);
        $this->list('?limit=0')->assertStatus(422);
        $this->list('?limit=101')->assertStatus(422);
        $this->list('?cursor=not-a-cursor')->assertStatus(422)->assertJsonPath('code', 'invalid_cursor');
        $this->list('?cursor='.rtrim(strtr(base64_encode('["x\' OR 1=1 --",1]'), '+/', '-_'), '='))->assertStatus(422)->assertJsonPath('code', 'invalid_cursor');
    }

    public function test_listing_changes_nothing(): void
    {
        $this->decision();
        $before = LoopRecord::query()->count();

        $this->list('?status=all')->assertOk();

        $this->assertSame($before, LoopRecord::query()->count());
        $this->assertDatabaseCount('knowledge_outcomes', 0);
    }
}
