<?php

namespace Tests\Feature;

use App\Models\OpsIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpsIncidentApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsService(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_rejects_unauthenticated_requests(): void
    {
        $this->postJson('/api/ops/incidents', [])->assertUnauthorized();
        $this->getJson('/api/ops/incidents')->assertUnauthorized();
        $this->getJson('/api/ops/recall?platform=dot-mines&signature=x')->assertUnauthorized();
    }

    public function test_creates_an_incident_record(): void
    {
        $this->actingAsService();

        $response = $this->postJson('/api/ops/incidents', [
            'incident_uid' => 'grd-2026-0001',
            'platform' => 'dot-mines',
            'environment' => 'production',
            'detection_source' => 'guardian-health-poll',
            'signature' => 'dot-mines:integration_sync:critical',
            'component' => 'integration_sync',
            'severity' => 'sev2',
            'status' => 'open',
            'detected_at' => now()->toISOString(),
            'record' => [
                'diagnosis' => 'Bell API sync stopped; token refresh failing.',
                'health_excerpt' => ['integration_sync' => ['status' => 'critical']],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.incident_uid', 'grd-2026-0001')
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('ops_incidents', [
            'incident_uid' => 'grd-2026-0001',
            'platform' => 'dot-mines',
            'severity' => 'sev2',
        ]);
    }

    public function test_create_is_idempotent_on_incident_uid(): void
    {
        $this->actingAsService();

        $payload = [
            'incident_uid' => 'grd-2026-0002',
            'platform' => 'dot-mines',
            'environment' => 'production',
            'detection_source' => 'guardian-health-poll',
            'signature' => 'dot-mines:queue:critical',
            'component' => 'queue',
            'severity' => 'sev2',
            'status' => 'open',
            'detected_at' => now()->toISOString(),
        ];

        $this->postJson('/api/ops/incidents', $payload)->assertCreated();
        $this->postJson('/api/ops/incidents', [...$payload, 'status' => 'remediating'])->assertOk();

        $this->assertSame(1, OpsIncident::query()->where('incident_uid', 'grd-2026-0002')->count());
        $this->assertSame('remediating', OpsIncident::query()->where('incident_uid', 'grd-2026-0002')->firstOrFail()->status);
    }

    public function test_validates_severity_and_status_vocabulary(): void
    {
        $this->actingAsService();

        $this->postJson('/api/ops/incidents', [
            'incident_uid' => 'grd-bad',
            'platform' => 'dot-mines',
            'environment' => 'production',
            'detection_source' => 'x',
            'signature' => 'sig',
            'component' => 'queue',
            'severity' => 'catastrophic',
            'status' => 'weird',
            'detected_at' => now()->toISOString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['severity', 'status']);
    }

    public function test_updates_an_incident_by_uid(): void
    {
        $this->actingAsService();

        OpsIncident::factory()->create(['incident_uid' => 'grd-2026-0003', 'status' => 'open']);

        $this->patchJson('/api/ops/incidents/grd-2026-0003', [
            'status' => 'resolved',
            'deploy_result' => 'success',
            'validation_result' => 'healthy',
            'resolved_at' => now()->toISOString(),
        ])->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->assertSame('resolved', OpsIncident::query()->where('incident_uid', 'grd-2026-0003')->firstOrFail()->status);
    }

    public function test_lists_incidents_filtered_by_platform_and_signature(): void
    {
        $this->actingAsService();

        OpsIncident::factory()->create(['platform' => 'dot-mines', 'signature' => 'sig-a']);
        OpsIncident::factory()->create(['platform' => 'dot-mines', 'signature' => 'sig-b']);
        OpsIncident::factory()->create(['platform' => 'dot-farms', 'signature' => 'sig-a']);

        $response = $this->getJson('/api/ops/incidents?platform=dot-mines&signature=sig-a');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_recall_summarises_history_for_a_signature(): void
    {
        $this->actingAsService();

        OpsIncident::factory()->create([
            'platform' => 'dot-mines',
            'signature' => 'sig-recur',
            'status' => 'resolved',
            'rollback_occurred' => false,
            'resolved_at' => now()->subDays(3),
            'record' => ['fix' => 'retriggered sync workflow'],
        ]);
        OpsIncident::factory()->create([
            'platform' => 'dot-mines',
            'signature' => 'sig-recur',
            'status' => 'rolled_back',
            'rollback_occurred' => true,
            'resolved_at' => now()->subDays(2),
        ]);
        OpsIncident::factory()->create([
            'platform' => 'dot-mines',
            'signature' => 'sig-recur',
            'status' => 'open',
        ]);
        OpsIncident::factory()->create([
            'platform' => 'dot-mines',
            'signature' => 'sig-other',
            'status' => 'resolved',
        ]);

        $response = $this->getJson('/api/ops/recall?platform=dot-mines&signature=sig-recur');

        $response->assertOk()
            ->assertJsonPath('data.matches', 3)
            ->assertJsonPath('data.resolved', 1)
            ->assertJsonPath('data.rolled_back', 1)
            ->assertJsonPath('data.success_rate', 0.5)
            ->assertJsonPath('data.last_successful_fix.signature', 'sig-recur');

        $this->assertSame(
            'retriggered sync workflow',
            $response->json('data.last_successful_fix.record.fix'),
        );
    }

    public function test_recall_records_a_usage_event(): void
    {
        $this->actingAsService();

        OpsIncident::factory()->create(['platform' => 'dot-mines', 'signature' => 'sig-used']);

        $this->getJson('/api/ops/recall?platform=dot-mines&signature=sig-used')->assertOk();
        $this->getJson('/api/ops/recall?platform=dot-mines&signature=sig-used')->assertOk();

        $this->assertSame(2, DB::table('ops_recall_events')->where('signature', 'sig-used')->count());
        $this->assertSame(1, (int) DB::table('ops_recall_events')->where('signature', 'sig-used')->value('matches'));
    }

    public function test_recall_with_no_history_returns_zero_matches(): void
    {
        $this->actingAsService();

        $this->getJson('/api/ops/recall?platform=dot-mines&signature=never-seen')
            ->assertOk()
            ->assertJsonPath('data.matches', 0)
            ->assertJsonPath('data.success_rate', null)
            ->assertJsonPath('data.last_successful_fix', null);
    }

    public function test_record_blob_is_encrypted_at_rest(): void
    {
        $this->actingAsService();

        OpsIncident::factory()->create([
            'incident_uid' => 'grd-2026-0009',
            'record' => ['diagnosis' => 'plainly-readable-secret-detail'],
        ]);

        $raw = DB::table('ops_incidents')
            ->where('incident_uid', 'grd-2026-0009')
            ->value('record');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('plainly-readable-secret-detail', $raw);
    }
}
