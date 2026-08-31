<?php

namespace App\Services\Guardian;

use App\Models\LoopRecord;
use Carbon\CarbonImmutable;

/**
 * Guardian health for the BluPin trading-signal pipeline (dot-guardian/v1).
 *
 * Memory holds the ground truth of whether the pipeline delivered: the
 * BluPinJS-EA daily engine writes decision/outcome records and Dot.Brain's
 * blupin-context service writes the news backdrop, all on the
 * trading-signal/gold-<date> subject. These checks ask one question each:
 * did the expected record for the last expected trading day arrive?
 *
 * Statuses: the expected day present = healthy; one trading day behind =
 * warning; two or more = critical; no records at all = unknown (a brand-new
 * enrollment must not page anyone).
 */
final class BlupinLoopReport
{
    private const TZ = 'Africa/Johannesburg';

    /** The daily runs land 05:05-05:25 SAST; expect them from 06:00. */
    private const GRACE_HOUR = 6;

    public function toArray(): array
    {
        $expected = $this->lastExpectedTradingDay(CarbonImmutable::now(self::TZ));

        $checks = [
            'blupin_signal_freshness' => $this->freshness(
                'blupin', 'decision', null, $expected,
                'daily signal decision (BluPinJS-EA engine)'),
            'blupin_context_freshness' => $this->freshness(
                'dot-brain', 'observation', 'usd-news-calendar', $expected,
                'news backdrop (Dot.Brain blupin-context)'),
            'blupin_outcome_grading' => $this->freshness(
                'blupin', 'outcome', null, $this->previousTradingDay($expected),
                'graded outcome (one trading day behind by design)'),
        ];

        $rank = ['healthy' => 0, 'unknown' => 1, 'warning' => 2, 'critical' => 3];
        $worst = 'healthy';
        foreach ($checks as $check) {
            if ($rank[$check['status']] > $rank[$worst]) {
                $worst = $check['status'];
            }
        }

        return [
            'platform' => 'blupin',
            'contract' => 'dot-guardian/v1',
            'generated_at' => (string) now()->toISOString(),
            'status' => $worst,
            'checks' => $checks,
        ];
    }

    /** Most recent weekday whose records are already due (06:00 SAST grace). */
    private function lastExpectedTradingDay(CarbonImmutable $now): CarbonImmutable
    {
        $day = $now->hour < self::GRACE_HOUR ? $now->subDay() : $now;
        while ($day->isWeekend()) {
            $day = $day->subDay();
        }

        return $day->startOfDay();
    }

    private function previousTradingDay(CarbonImmutable $day): CarbonImmutable
    {
        $prev = $day->subDay();
        while ($prev->isWeekend()) {
            $prev = $prev->subDay();
        }

        return $prev;
    }

    private function freshness(
        string $platform,
        string $stage,
        ?string $signature,
        CarbonImmutable $expected,
        string $what,
    ): array {
        $query = LoopRecord::query()
            ->where('platform', $platform)
            ->where('stage', $stage)
            ->where('subject_type', 'trading-signal');
        if ($signature !== null) {
            $query->where('signature', $signature);
        }

        // subject_id is "gold-YYYY-MM-DD", so lexical max = newest day.
        $latest = $query->orderByDesc('subject_id')->first();

        if ($latest === null) {
            return [
                'status' => 'unknown',
                'summary' => "No {$what} records yet - the pipeline has not been observed.",
            ];
        }

        $recordDay = CarbonImmutable::createFromFormat(
            'Y-m-d', substr((string) $latest->subject_id, 5), self::TZ)->startOfDay();

        $lag = 0;
        for ($day = $recordDay; $day->lessThan($expected); $day = $day->addDay()) {
            if (! $day->addDay()->isWeekend()) {
                $lag++;
            }
        }

        $status = $lag <= 0 ? 'healthy' : ($lag === 1 ? 'warning' : 'critical');

        return [
            'status' => $status,
            'summary' => sprintf(
                '%s: newest record is for %s, expected %s (%d trading day(s) behind).',
                $what, $recordDay->toDateString(), $expected->toDateString(), max(0, $lag)),
        ];
    }
}
