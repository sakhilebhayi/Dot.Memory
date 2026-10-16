<?php

namespace App\Services\Guardian\Checks;

use App\Models\LoopRecord;
use App\Models\OpsIncident;
use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use Illuminate\Support\Carbon;

/**
 * Is the ecosystem still writing to its memory? Dot.Mines and Dot.Central
 * flush their outboxes every minute and the Dot.Brain collector records
 * every live platform hourly, so more than a few hours of silence means
 * the writers (or this API) have stopped, not that nothing happened.
 * Never a claim about the content: only ages and counts.
 */
class IntelligenceIngestCheck implements GuardianCheck
{
    public function key(): string
    {
        return 'intelligence_ingest';
    }

    public function run(): CheckResult
    {
        $newest = LoopRecord::query()->max('created_at');

        if ($newest === null) {
            return CheckResult::unknown('No intelligence records stored yet.');
        }

        $age = max(0, (int) floor(Carbon::parse($newest)->diffInSeconds(now())));

        $metrics = [
            'newest_record_age_seconds' => $age,
            'records_last_24h' => LoopRecord::where('created_at', '>=', now()->subDay())->count(),
            'open_incidents' => OpsIncident::where('status', 'open')->count(),
            'open_incidents_over_7d' => OpsIncident::where('status', 'open')->where('detected_at', '<', now()->subDays(7))->count(),
        ];

        if ($age >= 12 * 3600) {
            return CheckResult::critical('No platform has written to Dot.Memory for twelve hours.', $metrics);
        }

        if ($age >= 3 * 3600) {
            return CheckResult::warning('No platform has written to Dot.Memory for three hours.', $metrics);
        }

        return CheckResult::healthy('Platforms are writing to Dot.Memory.', $metrics);
    }
}
