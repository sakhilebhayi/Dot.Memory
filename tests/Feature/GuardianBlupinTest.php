<?php

namespace Tests\Feature;

use App\Models\LoopRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuardianBlupinTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsService(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function record(string $platform, string $stage, string $day, ?string $signature = null): void
    {
        LoopRecord::query()->create([
            'loop_id' => "blupin-gold-{$day}",
            'event_id' => "blupin-gold-{$day}-{$stage}-{$platform}",
            'stage' => $stage,
            'platform' => $platform,
            'source' => 'test',
            'subject_type' => 'trading-signal',
            'subject_id' => "gold-{$day}",
            'occurred_at' => "{$day}T03:20:00Z",
            'signature' => $signature,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/guardian/blupin')->assertUnauthorized();
    }

    public function test_empty_pipeline_reports_unknown_not_critical(): void
    {
        $this->actingAsService();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 08:00', 'Africa/Johannesburg'));

        $this->getJson('/api/guardian/blupin')
            ->assertOk()
            ->assertJsonPath('contract', 'dot-guardian/v1')
            ->assertJsonPath('status', 'unknown')
            ->assertJsonPath('checks.blupin_signal_freshness.status', 'unknown');
    }

    public function test_fresh_records_report_healthy(): void
    {
        $this->actingAsService();
        // Tuesday 08:00 SAST: expects Tuesday's signal+context, Monday's outcome
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 08:00', 'Africa/Johannesburg'));
        $this->record('blupin', 'decision', '2026-09-01');
        $this->record('dot-brain', 'observation', '2026-09-01', 'usd-news-calendar');
        $this->record('blupin', 'outcome', '2026-08-31');

        $this->getJson('/api/guardian/blupin')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('checks.blupin_outcome_grading.status', 'healthy');
    }

    public function test_one_trading_day_behind_is_warning_two_is_critical(): void
    {
        $this->actingAsService();
        // Wednesday 08:00: newest decision is Tuesday -> 1 behind
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 08:00', 'Africa/Johannesburg'));
        $this->record('blupin', 'decision', '2026-09-01');

        $this->getJson('/api/guardian/blupin')
            ->assertOk()
            ->assertJsonPath('checks.blupin_signal_freshness.status', 'warning');

        // Thursday: same record is now 2 trading days behind -> critical
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 08:00', 'Africa/Johannesburg'));
        $this->getJson('/api/guardian/blupin')
            ->assertOk()
            ->assertJsonPath('checks.blupin_signal_freshness.status', 'critical')
            ->assertJsonPath('status', 'critical');
    }

    public function test_weekend_and_pre_grace_mornings_expect_friday(): void
    {
        $this->actingAsService();
        // Monday 05:00 SAST (before the 06:00 grace): Friday is still current
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 05:00', 'Africa/Johannesburg'));
        $this->record('blupin', 'decision', '2026-09-04');

        $this->getJson('/api/guardian/blupin')
            ->assertOk()
            ->assertJsonPath('checks.blupin_signal_freshness.status', 'healthy');
    }
}
