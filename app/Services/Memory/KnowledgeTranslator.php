<?php

namespace App\Services\Memory;

use App\Models\OpsIncident;
use Illuminate\Support\Str;

/**
 * Translates archived operational knowledge (OpsIncident rows) into the
 * language a person actually thinks in: what happened, what was learned,
 * why it matters, and how much to trust it. This class IS the product
 * philosophy -- never show users how memory is stored; show what the
 * system remembers. No output string may contain component keys,
 * signatures, sev codes, or any other internal vocabulary.
 */
class KnowledgeTranslator
{
    /** Plain-language phrase per guardian check component. */
    private const COMPONENT_PHRASES = [
        'telemetry_ingestion' => 'Machine data stopped arriving',
        'production_freshness' => 'Production figures stopped updating',
        'integration_sync' => 'A manufacturer connection stopped syncing',
        'queue' => 'Background work started backing up',
        'scheduler' => 'Scheduled work stopped running',
        'database' => 'The database became unreachable',
        'availability' => 'The platform became unreachable',
        'error_rate' => 'The platform started throwing unusual errors',
        'cache' => 'The cache stopped responding',
    ];

    /** Why each problem class matters, in business language. */
    private const COMPONENT_STAKES = [
        'telemetry_ingestion' => 'Without fresh machine data, dashboards and production tracking silently fall behind reality.',
        'production_freshness' => 'Production decisions depend on current figures; stale numbers can mislead an entire shift.',
        'integration_sync' => 'A broken manufacturer connection cuts off the flow of fleet data the whole platform is built on.',
        'queue' => 'Backed-up background work delays alerts, reports, and syncs that people rely on.',
        'scheduler' => 'When scheduled work stops, every automatic process on the platform quietly stops with it.',
        'database' => 'Nothing on the platform works without its database; every user is affected immediately.',
        'availability' => 'Users could not reach the platform at all.',
        'error_rate' => 'A spike in errors usually means something just broke for real users.',
        'cache' => 'A failing cache slows every page and can cascade into wider failures.',
    ];

    /** Plain-language fix description per guardian runbook key. */
    private const RUNBOOK_PHRASES = [
        'redeploy' => 'Re-running the deployment pipeline restored normal operation.',
        'rollback_last_deploy' => 'Undoing the most recent deployment restored normal operation.',
    ];

    /**
     * @param  array{occurrences: int, resolved: int, rolled_back: int}|null  $history
     *   Same-signature history for the trust verdict; null renders neutral trust.
     * @return array{headline: string, what_happened: string, what_was_learned: string, why_it_matters: string, severity_label: string, status_label: string, platform_label: string, trust: array{label: string, explanation: string}}
     */
    public function translate(OpsIncident $incident, ?array $history = null): array
    {
        $platform = $this->platformLabel($incident->platform);
        $phrase = self::COMPONENT_PHRASES[$incident->component]
            ?? 'Something went wrong with '.str_replace('_', ' ', $incident->component);

        return [
            'headline' => "{$phrase} on {$platform}",
            'what_happened' => $this->whatHappened($incident, $phrase, $platform),
            'what_was_learned' => $this->whatWasLearned($incident),
            'why_it_matters' => self::COMPONENT_STAKES[$incident->component]
                ?? 'Knowing this happened helps the ecosystem recognise and respond to it faster next time.',
            'severity_label' => match ($incident->severity) {
                'sev1' => 'Critical',
                'sev2' => 'Serious',
                default => 'Minor',
            },
            'status_label' => match ($incident->status) {
                'open' => 'Being watched',
                'remediating' => 'Fix in progress',
                'resolved' => 'Fixed',
                'escalated' => 'Needs a person',
                'rolled_back' => 'Fix was undone',
                default => Str::headline($incident->status),
            },
            'platform_label' => $platform,
            'trust' => $this->trust($history),
        ];
    }

    public function platformLabel(string $platform): string
    {
        return collect(explode('-', $platform))
            ->map(fn (string $part): string => Str::ucfirst($part))
            ->implode('.');
    }

    private function whatHappened(OpsIncident $incident, string $phrase, string $platform): string
    {
        $when = $incident->detected_at->timezone(config('app.timezone'))->format('j F Y \a\t H:i');

        return "{$phrase}. The ecosystem's guardian noticed this on {$when} while watching {$platform}'s production health, and opened this record so the experience is never lost.";
    }

    private function whatWasLearned(OpsIncident $incident): string
    {
        if ($incident->status === 'escalated') {
            return 'Automatic remediation was not safe here, so a person was asked to investigate. Whatever they conclude will become part of this record.';
        }

        if ($incident->status === 'rolled_back') {
            return 'An attempted fix did not hold and was undone. This problem needs a different approach than the one tried.';
        }

        if ($incident->status === 'resolved') {
            $runbook = is_array($incident->record) ? ($incident->record['runbook'] ?? null) : null;

            if (is_string($runbook) && isset(self::RUNBOOK_PHRASES[$runbook])) {
                return self::RUNBOOK_PHRASES[$runbook].' If this problem returns, the same response is a proven starting point.';
            }

            return 'The problem cleared while being watched -- no intervention was needed. If it recurs frequently, it deserves a closer look.';
        }

        return 'Not yet resolved -- the guardian is still watching this and the record will grow as more is learned.';
    }

    /**
     * @param  array{occurrences: int, resolved: int, rolled_back: int}|null  $history
     * @return array{label: string, explanation: string}
     */
    private function trust(?array $history): array
    {
        if ($history === null || $history['occurrences'] <= 1) {
            return [
                'label' => 'New knowledge',
                'explanation' => 'This is the first time this has been seen. Treat conclusions as provisional until the pattern repeats.',
            ];
        }

        if ($history['rolled_back'] > 0) {
            return [
                'label' => 'Needs verification',
                'explanation' => 'A past fix for this problem had to be undone, so the recorded solution should be double-checked before being trusted.',
            ];
        }

        if ($history['occurrences'] >= 3 && $history['resolved'] / $history['occurrences'] >= 0.8) {
            return [
                'label' => 'Proven fix',
                'explanation' => "This problem has been resolved successfully {$history['resolved']} times -- the recorded response is well established.",
            ];
        }

        if ($history['occurrences'] >= 3) {
            return [
                'label' => 'Recurring problem',
                'explanation' => "Seen {$history['occurrences']} times but rarely resolved cleanly -- there is no reliable fix yet and it deserves attention.",
            ];
        }

        return [
            'label' => 'Emerging pattern',
            'explanation' => 'Seen more than once; not yet enough history to call the response proven.',
        ];
    }
}
