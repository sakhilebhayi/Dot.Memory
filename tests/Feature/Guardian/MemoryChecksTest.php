<?php

namespace Tests\Feature\Guardian;

use App\Models\LoopRecord;
use App\Models\OpsIncident;
use App\Services\Guardian\Checks\IntelligenceIngestCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The check that makes Dot.Memory's report more than "the app is up": are
 * the other platforms still writing to it. Ages and counts only, never
 * content (the store-without-reading doctrine).
 */
class MemoryChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_unknown_before_anything_was_ever_recorded(): void
    {
        $this->assertSame('unknown', app(IntelligenceIngestCheck::class)->run()->status());
    }

    public function test_it_grades_the_age_of_the_newest_record(): void
    {
        $record = LoopRecord::factory()->create();

        $this->assertSame('healthy', app(IntelligenceIngestCheck::class)->run()->status());

        $record->forceFill(['created_at' => now()->subHours(4)])->save();
        $this->assertSame('warning', app(IntelligenceIngestCheck::class)->run()->status());

        $record->forceFill(['created_at' => now()->subHours(13)])->save();
        $result = app(IntelligenceIngestCheck::class)->run();
        $this->assertSame('critical', $result->status());
        $this->assertGreaterThanOrEqual(13 * 3600, $result->toArray()['metrics']['newest_record_age_seconds']);
    }

    public function test_it_counts_open_incidents_without_reading_them(): void
    {
        LoopRecord::factory()->create();
        OpsIncident::factory()->count(2)->create(['status' => 'open', 'detected_at' => now()->subDays(10)]);
        OpsIncident::factory()->create(['status' => 'resolved']);

        $metrics = app(IntelligenceIngestCheck::class)->run()->toArray()['metrics'];

        $this->assertSame(2, $metrics['open_incidents']);
        $this->assertSame(2, $metrics['open_incidents_over_7d']);
        $this->assertArrayNotHasKey('record', $metrics);
    }
}
