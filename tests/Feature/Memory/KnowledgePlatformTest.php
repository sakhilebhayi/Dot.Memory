<?php

namespace Tests\Feature\Memory;

use App\Livewire\Memory\KnowledgeBrowser;
use App\Livewire\Memory\KnowledgeDetail;
use App\Livewire\Memory\KnowledgeOverview;
use App\Livewire\Memory\KnowledgePatterns;
use App\Livewire\Memory\KnowledgeTimeline;
use App\Models\OpsIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class KnowledgePlatformTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsVerifiedUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_every_knowledge_route_requires_authentication(): void
    {
        foreach (['/dashboard', '/knowledge', '/timeline', '/patterns', '/reliability'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_overview_shows_the_growing_empty_state_without_knowledge(): void
    {
        $this->actingAsVerifiedUser();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your knowledge base is still growing');
    }

    public function test_overview_translates_real_knowledge_and_shows_usage(): void
    {
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->create([
            'platform' => 'dot-mines',
            'component' => 'telemetry_ingestion',
            'status' => 'resolved',
            'record' => ['runbook' => 'redeploy'],
        ]);
        DB::table('ops_recall_events')->insert([
            'platform' => 'dot-mines', 'signature' => 'x', 'matches' => 1, 'created_at' => now(),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Machine data stopped arriving on Dot.Mines')
            ->assertSee('Re-running the deployment pipeline')
            ->assertDontSee('telemetry_ingestion')
            ->assertDontSee('sev2');
    }

    public function test_browser_searches_and_filters_in_human_language(): void
    {
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->create(['component' => 'telemetry_ingestion', 'platform' => 'dot-mines', 'status' => 'resolved']);
        OpsIncident::factory()->create(['component' => 'queue', 'platform' => 'dot-farms', 'status' => 'open']);

        Livewire::test(KnowledgeBrowser::class)
            ->assertSee('Machine data stopped arriving on Dot.Mines')
            ->assertSee('Background work started backing up on Dot.Farms')
            ->set('search', 'machine data')
            ->assertSee('Machine data stopped arriving on Dot.Mines')
            ->assertDontSee('Background work started backing up')
            ->set('search', '')
            ->set('status', 'open')
            ->assertSee('Background work started backing up')
            ->assertDontSee('Machine data stopped arriving');
    }

    public function test_detail_page_renders_the_memory_profile(): void
    {
        $this->actingAsVerifiedUser();

        $incident = OpsIncident::factory()->create([
            'incident_uid' => 'grd-profile-1',
            'platform' => 'dot-mines',
            'component' => 'production_freshness',
            'status' => 'resolved',
            'resolved_at' => now(),
            'record' => ['runbook' => 'redeploy'],
        ]);

        $this->get(route('knowledge.show', 'grd-profile-1'))
            ->assertOk()
            ->assertSee('Production figures stopped updating on Dot.Mines')
            ->assertSee('What was learned')
            ->assertSee('Why it matters')
            ->assertSee('How much to trust this')
            ->assertSee('Technical detail')
            ->assertSee('grd-profile-1'); // available, but inside the collapsed technical panel
    }

    public function test_detail_page_404s_for_unknown_knowledge(): void
    {
        $this->actingAsVerifiedUser();

        $this->get(route('knowledge.show', 'grd-nope'))->assertNotFound();
    }

    public function test_timeline_tells_the_story_chronologically(): void
    {
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->create([
            'component' => 'integration_sync',
            'platform' => 'dot-mines',
            'status' => 'resolved',
            'detected_at' => now()->subDay(),
            'resolved_at' => now(),
        ]);

        Livewire::test(KnowledgeTimeline::class)
            ->assertSee('Problem discovered: A manufacturer connection stopped syncing on Dot.Mines')
            ->assertSee('reusable knowledge');
    }

    public function test_patterns_surface_recurrence_and_coverage(): void
    {
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->count(3)->create([
            'platform' => 'dot-mines',
            'signature' => 'dot-mines:queue:critical',
            'component' => 'queue',
            'status' => 'resolved',
        ]);

        Livewire::test(KnowledgePatterns::class)
            ->assertSee('Background work started backing up on Dot.Mines')
            ->assertSee('Dot.Mines');
    }

    public function test_a_pattern_expands_in_place_rather_than_navigating_away(): void
    {
        // The reason to open a pattern is to compare it against the others,
        // so its evidence unfolds inside the ledger and the rows around it
        // stay on screen. A dialog would cover up the comparison.
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->count(2)->create([
            'platform' => 'dot-mines',
            'signature' => 'dot-mines:queue:critical',
            'component' => 'queue',
            'status' => 'resolved',
        ]);

        Livewire::test(KnowledgePatterns::class)
            ->call('toggle', 'dot-mines|dot-mines:queue:critical')
            ->assertSet('expanded', 'dot-mines|dot-mines:queue:critical')
            ->assertSee('Every time this happened')
            ->assertSee('Background work started backing up on Dot.Mines');
    }

    public function test_toggling_the_open_pattern_closes_it(): void
    {
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->create([
            'platform' => 'dot-mines',
            'signature' => 'dot-mines:queue:critical',
            'component' => 'queue',
        ]);

        Livewire::test(KnowledgePatterns::class)
            ->call('toggle', 'dot-mines|dot-mines:queue:critical')
            ->call('toggle', 'dot-mines|dot-mines:queue:critical')
            ->assertSet('expanded', '')
            ->assertDontSee('Every time this happened');
    }

    public function test_expanding_an_unknown_pattern_fails_gracefully(): void
    {
        $this->actingAsVerifiedUser();

        Livewire::test(KnowledgePatterns::class)
            ->call('toggle', 'nope|nope')
            ->assertOk();
    }

    public function test_the_recurring_filter_is_honest_when_nothing_recurs(): void
    {
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->create();

        Livewire::test(KnowledgePatterns::class)
            ->set('filter', 'recurring')
            ->assertSee('Nothing has happened twice yet');
    }

    public function test_insights_forwards_to_patterns(): void
    {
        // The old URL is in links and bookmarks; it must not 404.
        $this->actingAsVerifiedUser();

        $this->get('/insights')->assertRedirect(route('knowledge.patterns'));
    }

    public function test_overview_component_reports_honest_figures(): void
    {
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->create(['status' => 'resolved']);
        OpsIncident::factory()->create(['status' => 'open']);

        Livewire::test(KnowledgeOverview::class)
            ->assertSee('Experiences recorded')
            ->assertSee('Problems fixed');

        $component = new KnowledgeOverview;
        $this->assertSame(2, $component->figures()['total']);
        $this->assertSame(1, $component->figures()['fixed']);
        $this->assertSame(1, $component->figures()['watching']);
    }

    public function test_detail_component_counts_history_including_itself(): void
    {
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->create([
            'platform' => 'dot-mines', 'signature' => 'sig-r', 'status' => 'resolved',
        ]);
        $latest = OpsIncident::factory()->create([
            'platform' => 'dot-mines', 'signature' => 'sig-r', 'status' => 'resolved',
        ]);

        $test = Livewire::test(KnowledgeDetail::class, ['incidentUid' => $latest->incident_uid]);
        $test->assertSee('Same problem');
    }
}
