<?php

namespace Tests\Feature\Memory;

use App\Livewire\Memory\KnowledgeBrowser;
use App\Livewire\Memory\KnowledgeDetail;
use App\Livewire\Memory\KnowledgeInsights;
use App\Livewire\Memory\KnowledgeOverview;
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
        foreach (['/dashboard', '/knowledge', '/timeline', '/insights', '/reliability'] as $path) {
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

    public function test_insights_surface_recurrence_and_coverage(): void
    {
        $this->actingAsVerifiedUser();

        OpsIncident::factory()->count(3)->create([
            'platform' => 'dot-mines',
            'signature' => 'dot-mines:queue:critical',
            'component' => 'queue',
            'status' => 'resolved',
        ]);

        Livewire::test(KnowledgeInsights::class)
            ->assertSee('Background work started backing up on Dot.Mines')
            ->assertSee('Seen 3 times')
            ->assertSee('Dot.Mines'); // covered platform chip
    }

    public function test_an_insight_opens_in_place_rather_than_navigating_away(): void
    {
        // Inspecting a pattern should not cost the reader their place in
        // the list, so the detail arrives in a dialog.
        $this->actingAsVerifiedUser();

        $incidents = OpsIncident::factory()->count(2)->create([
            'platform' => 'dot-mines',
            'signature' => 'dot-mines:queue:critical',
            'component' => 'queue',
            'status' => 'resolved',
        ]);

        Livewire::test(KnowledgeInsights::class)
            ->call('inspect', $incidents->first()->incident_uid)
            ->assertDispatched('open-modal', name: 'insight-detail')
            ->assertSee('What was detected')
            ->assertSee('Why it matters')
            ->assertSee('How much to trust this');
    }

    public function test_inspecting_an_unknown_record_fails_gracefully(): void
    {
        $this->actingAsVerifiedUser();

        Livewire::test(KnowledgeInsights::class)
            ->call('inspect', 'grd-nope')
            ->assertSee('could not be loaded')
            ->assertOk();
    }

    public function test_insights_are_honest_when_nothing_recurs(): void
    {
        $this->actingAsVerifiedUser();

        Livewire::test(KnowledgeInsights::class)
            ->assertSee('No problem has repeated itself yet');
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
