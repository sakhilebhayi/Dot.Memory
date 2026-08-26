<?php

namespace Tests\Unit;

use App\Models\OpsIncident;
use App\Services\Memory\KnowledgeTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The translator is the product philosophy in code: never show users how
 * memory is stored -- show what the system remembers and why it matters.
 * Every branch here is a sentence a non-technical user must understand.
 */
class KnowledgeTranslatorTest extends TestCase
{
    use RefreshDatabase;

    private function translate(array $attributes = [], ?array $history = null): array
    {
        $incident = OpsIncident::factory()->create($attributes);

        return app(KnowledgeTranslator::class)->translate($incident, $history);
    }

    public function test_headline_speaks_plainly_about_the_component_and_platform(): void
    {
        $t = $this->translate(['component' => 'telemetry_ingestion', 'platform' => 'dot-mines']);

        $this->assertSame('Machine data stopped arriving on Dot.Mines', $t['headline']);
        $this->assertStringNotContainsString('telemetry_ingestion', $t['headline']);
    }

    public function test_unknown_components_still_read_as_language_not_keys(): void
    {
        $t = $this->translate(['component' => 'some_new_check', 'platform' => 'dot-farms']);

        $this->assertStringContainsString('Dot.Farms', $t['headline']);
        $this->assertStringNotContainsString('some_new_check', $t['headline']);
        $this->assertStringContainsString('some new check', $t['headline']);
    }

    public function test_severity_and_status_are_human_labels(): void
    {
        $t = $this->translate(['severity' => 'sev1', 'status' => 'open']);
        $this->assertSame('Critical', $t['severity_label']);
        $this->assertSame('Being watched', $t['status_label']);

        $t = $this->translate(['severity' => 'sev3', 'status' => 'resolved']);
        $this->assertSame('Minor', $t['severity_label']);
        $this->assertSame('Fixed', $t['status_label']);

        $t = $this->translate(['status' => 'escalated']);
        $this->assertSame('Needs a person', $t['status_label']);
    }

    public function test_an_open_incident_is_honest_about_not_being_resolved(): void
    {
        $t = $this->translate(['status' => 'open']);

        $this->assertStringContainsString('Not yet resolved', $t['what_was_learned']);
    }

    public function test_a_resolved_incident_with_a_runbook_names_the_fix_in_plain_language(): void
    {
        $t = $this->translate([
            'status' => 'resolved',
            'record' => ['runbook' => 'redeploy'],
        ]);

        $this->assertStringContainsString('Re-running the deployment pipeline', $t['what_was_learned']);
        $this->assertStringNotContainsString('runbook', $t['what_was_learned']);
    }

    public function test_a_self_resolved_incident_says_no_intervention_was_needed(): void
    {
        $t = $this->translate(['status' => 'resolved', 'record' => null]);

        $this->assertStringContainsString('no intervention was needed', $t['what_was_learned']);
    }

    public function test_an_escalated_incident_explains_a_person_was_asked(): void
    {
        $t = $this->translate(['status' => 'escalated']);

        $this->assertStringContainsString('a person was asked to investigate', $t['what_was_learned']);
    }

    public function test_trust_is_proven_after_repeated_successful_fixes(): void
    {
        $t = $this->translate([], ['occurrences' => 4, 'resolved' => 4, 'rolled_back' => 0]);

        $this->assertSame('Proven fix', $t['trust']['label']);
        $this->assertStringContainsString('4 times', $t['trust']['explanation']);
    }

    public function test_trust_needs_verification_after_a_rollback(): void
    {
        $t = $this->translate([], ['occurrences' => 3, 'resolved' => 2, 'rolled_back' => 1]);

        $this->assertSame('Needs verification', $t['trust']['label']);
    }

    public function test_trust_flags_recurring_problems_without_a_reliable_fix(): void
    {
        $t = $this->translate([], ['occurrences' => 5, 'resolved' => 1, 'rolled_back' => 0]);

        $this->assertSame('Recurring problem', $t['trust']['label']);
    }

    public function test_first_sighting_is_provisional_knowledge(): void
    {
        $t = $this->translate([], ['occurrences' => 1, 'resolved' => 0, 'rolled_back' => 0]);

        $this->assertSame('New knowledge', $t['trust']['label']);
    }

    public function test_no_output_field_leaks_technical_vocabulary(): void
    {
        $t = $this->translate([
            'component' => 'integration_sync',
            'platform' => 'dot-mines',
            'severity' => 'sev2',
            'status' => 'rolled_back',
            'signature' => 'dot-mines:integration_sync:critical',
        ]);

        foreach (['headline', 'what_happened', 'what_was_learned', 'why_it_matters', 'severity_label', 'status_label'] as $field) {
            $this->assertStringNotContainsString('sev2', $t[$field], $field);
            $this->assertStringNotContainsString('dot-mines:', $t[$field], $field);
            $this->assertStringNotContainsString('_', $t[$field], $field);
        }
    }
}
