<?php

namespace Tests\Feature\Knowledge;

use App\Models\KnowledgeOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KnowledgeOutcomeFeedTest extends TestCase
{
    use RefreshDatabase;
    use SignsKnowledgeRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSecrets();
        $this->travelTo(now()->startOfSecond()); // a frozen clock: second-precision timestamps cannot flake
    }

    private function feed(string $query = ''): TestResponse
    {
        return $this->signedAs('brain', 'GET', '/api/knowledge-recommendations/outcomes'.$query);
    }

    private function outcome(string $eventId, string $ref, string $outcome, \DateTimeInterface $at): void
    {
        KnowledgeOutcome::query()->create([
            'decision_event_id' => $eventId, 'contribution_ref' => $ref, 'outcome' => $outcome,
            'actor_ref' => 'ffffffff-0000-4000-8000-aaaaaaaaaaaa', 'decided_at' => $at,
        ]);
    }

    public function test_the_feed_has_the_documented_shape_and_never_carries_actor_ref(): void
    {
        $this->outcome('evt-1', 'infodot:answer:1', 'adopted', now()->subHour());

        $response = $this->feed()->assertOk();

        $response->assertExactJson(['items' => [[
            'contribution_ref' => 'infodot:answer:1',
            'decision_event_id' => 'evt-1',
            'outcome' => 'adopted',
            'decided_at' => now()->subHour()->startOfSecond()->toISOString(),
        ]]]);
        $this->assertStringNotContainsString('actor', $response->getContent());
        $this->assertStringNotContainsString('ffffffff-0000-4000-8000-aaaaaaaaaaaa', $response->getContent());
    }

    public function test_it_returns_every_outcome_row_oldest_first_so_a_change_of_mind_is_visible(): void
    {
        $this->outcome('evt-1', 'r1', 'adopted', now()->subHours(3));
        $this->outcome('evt-2', 'r2', 'declined', now()->subHours(2));
        $this->outcome('evt-1', 'r1', 'declined', now()->subHour());

        $items = $this->feed()->assertOk()->json('items');

        $this->assertSame(
            [['evt-1', 'adopted'], ['evt-2', 'declined'], ['evt-1', 'declined']],
            array_map(fn ($i) => [$i['decision_event_id'], $i['outcome']], $items),
        );
    }

    public function test_since_filters_inclusively_and_understands_offsets(): void
    {
        $this->outcome('evt-old', 'r1', 'adopted', now()->subDays(5));
        $this->outcome('evt-new', 'r2', 'declined', now()->subHours(2));

        $this->assertSame(['evt-new'], array_column($this->feed('?since='.urlencode(now()->subDay()->toISOString()))->json('items'), 'decision_event_id'));
        $this->assertSame(['evt-old', 'evt-new'], array_column($this->feed('?since='.urlencode(now()->subDays(6)->toISOString()))->json('items'), 'decision_event_id'));
        // The same instant written with a +02:00 offset selects the same rows.
        $this->assertSame(['evt-new'], array_column($this->feed('?since='.urlencode(now()->subDay()->setTimezone('Africa/Johannesburg')->toIso8601String()))->json('items'), 'decision_event_id'));
        $this->assertSame([], $this->feed('?since='.urlencode(now()->addMinute()->toISOString()))->json('items'));
    }

    public function test_limit_is_applied_and_validated(): void
    {
        foreach (range(1, 5) as $i) {
            $this->outcome("evt-{$i}", "r{$i}", 'adopted', now()->subHours(10 - $i));
        }

        $this->assertSame(['evt-1', 'evt-2'], array_column($this->feed('?limit=2')->json('items'), 'decision_event_id'));
        $this->feed('?limit=0')->assertStatus(422);
        $this->feed('?limit=501')->assertStatus(422);
        $this->feed('?since=not-a-date')->assertStatus(422);
    }

    public function test_an_empty_feed_is_an_empty_list(): void
    {
        $this->feed()->assertOk()->assertExactJson(['items' => []]);
    }
}
