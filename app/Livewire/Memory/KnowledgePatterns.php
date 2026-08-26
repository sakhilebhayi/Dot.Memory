<?php

namespace App\Livewire\Memory;

use App\Models\OpsIncident;
use App\Services\Memory\KnowledgeTranslator;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Patterns, not incidents, are what this archive is actually for.
 *
 * A single incident is a fact; a pattern is knowledge -- it is the thing
 * that tells you whether the fix you are about to apply has ever worked.
 * So the pattern is the object on this page and its incidents are its
 * occurrences, listed underneath it as evidence.
 *
 * Occurrences expand IN PLACE rather than in a dialog. The whole reason to
 * open one is to compare it against the others, and a dialog covers up the
 * comparison you opened it from.
 */
class KnowledgePatterns extends Component
{
    /** Which pattern's evidence is open. Shareable, so a link lands on it. */
    #[Url(as: 'pattern', except: '')]
    public string $expanded = '';

    /** Show every pattern, or only those that have happened more than once. */
    #[Url(as: 'only', except: 'all')]
    public string $filter = 'all';

    public function toggle(string $key): void
    {
        $this->expanded = $this->expanded === $key ? '' : $key;
    }

    public function updatedFilter(): void
    {
        $this->expanded = '';
    }

    /**
     * Every distinct problem the ecosystem has seen, heaviest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function patterns(): Collection
    {
        $translator = app(KnowledgeTranslator::class);

        return OpsIncident::query()
            ->orderByDesc('detected_at')
            ->get()
            ->groupBy(fn (OpsIncident $incident): string => $incident->platform.'|'.$incident->signature)
            ->when(
                $this->filter === 'recurring',
                fn (Collection $groups): Collection => $groups->filter(
                    fn (Collection $group): bool => $group->count() > 1
                )
            )
            ->map(function (Collection $group, string $key) use ($translator): array {
                /** @var OpsIncident $latest */
                $latest = $group->sortByDesc('detected_at')->first();

                $history = [
                    'occurrences' => $group->count(),
                    'resolved' => $group->where('status', 'resolved')->count(),
                    'rolled_back' => $group->where('rollback_occurred', true)->count(),
                ];
                $human = $translator->translate($latest, $history);

                return [
                    'key' => $key,
                    'headline' => $human['headline'],
                    'platform' => $translator->platformLabel($latest->platform),
                    'learned' => $human['what_was_learned'],
                    'trust' => $human['trust'],
                    'occurrences' => $history['occurrences'],
                    'resolved' => $history['resolved'],
                    'rolled_back' => $history['rolled_back'],
                    'unresolved' => $history['occurrences'] - $history['resolved'],
                    'last_seen' => $latest->detected_at,
                    'first_seen' => $group->min('detected_at'),
                    'marks' => $this->marksFor($group),
                ];
            })
            ->sortByDesc(fn (array $pattern): int => $pattern['occurrences'])
            ->values();
    }

    /**
     * The evidence behind one pattern: every time it happened, how it ended
     * and how long it took.
     *
     * Deliberately NOT the narrative. Every occurrence of one pattern
     * translates to nearly the same sentence, so repeating it down the list
     * buries the things that actually differ -- the duration, the outcome,
     * whether the fix had to be undone. The narrative is pattern-level and
     * is stated once, above.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function occurrences(): Collection
    {
        if ($this->expanded === '') {
            return collect();
        }

        [$platform, $signature] = array_pad(explode('|', $this->expanded, 2), 2, '');
        $translator = app(KnowledgeTranslator::class);

        return OpsIncident::query()
            ->where('platform', $platform)
            ->where('signature', $signature)
            ->orderByDesc('detected_at')
            ->get()
            ->map(fn (OpsIncident $incident): array => [
                'uid' => $incident->incident_uid,
                'detected_at' => $incident->detected_at,
                'status_label' => $translator->translate($incident)['status_label'],
                'status' => $incident->status,
                'rolled_back' => $incident->rollback_occurred,
                'took' => $this->duration($incident),
            ])
            ->values();
    }

    /**
     * Open problems that have been waiting too long for a resolution.
     * Not a pattern -- a pattern is history, and these have no ending yet --
     * so they sit beside the ledger rather than in it.
     *
     * @return Collection<int, array{headline: string, since: string, uid: string}>
     */
    #[Computed]
    public function longWatches(): Collection
    {
        $translator = app(KnowledgeTranslator::class);

        return OpsIncident::query()
            ->whereIn('status', ['open', 'remediating'])
            ->where('detected_at', '<=', now()->subDay())
            ->orderBy('detected_at')
            ->get()
            ->map(fn (OpsIncident $incident): array => [
                'headline' => $translator->translate($incident)['headline'],
                'since' => $incident->detected_at->diffForHumans(),
                'uid' => $incident->incident_uid,
            ]);
    }

    /**
     * Honest coverage: which active platforms have contributed no
     * operational knowledge yet. An archive that hides its blind spots is
     * worse than one that admits them.
     *
     * @return array{covered: list<string>, uncovered: list<string>}
     */
    #[Computed]
    public function coverage(): array
    {
        $translator = app(KnowledgeTranslator::class);

        $covered = OpsIncident::query()
            ->distinct()
            ->pluck('platform')
            ->map(fn (string $platform): string => $translator->platformLabel($platform))
            ->sort()
            ->values();

        $platforms = config('ecosystem.platforms');
        $registry = collect(is_array($platforms) ? $platforms : [])
            ->filter(fn (mixed $entry): bool => is_array($entry) && ($entry['active'] ?? false) === true)
            ->map(fn (array $entry): string => (string) ($entry['name'] ?? ''))
            ->filter()
            ->sort()
            ->values();

        return [
            'covered' => $covered->all(),
            'uncovered' => $registry->reject(fn (string $name): bool => $covered->contains($name))->values()->all(),
        ];
    }

    /**
     * A pattern is a thing that keeps happening, so its shape over time is
     * shown as a tally of marks rather than a figure. Heights are relative
     * to the busiest week, and a week with nothing gets a baseline stub so
     * the gaps stay legible.
     *
     * @param  Collection<int, OpsIncident>  $group
     * @return list<array{height: int, week: string, count: int}>
     */
    private function marksFor(Collection $group): array
    {
        $weeks = [];

        for ($i = 11; $i >= 0; $i--) {
            $weeks[now()->subWeeks($i)->format('o-W')] = 0;
        }

        foreach ($group as $incident) {
            $bucket = $incident->detected_at->format('o-W');

            if (array_key_exists($bucket, $weeks)) {
                $weeks[$bucket]++;
            }
        }

        $peak = max(1, max($weeks));

        return array_values(array_map(
            fn (int $count, string $week): array => [
                'height' => $count === 0 ? 0 : (int) max(4, round(($count / $peak) * 22)),
                'week' => $week,
                'count' => $count,
            ],
            $weeks,
            array_keys($weeks),
        ));
    }

    private function duration(OpsIncident $incident): string
    {
        if ($incident->resolved_at === null) {
            return 'still open';
        }

        return $incident->detected_at->diffForHumans(
            $incident->resolved_at,
            syntax: CarbonInterface::DIFF_ABSOLUTE,
            short: true,
        );
    }

    public function render()
    {
        return view('livewire.memory.knowledge-patterns');
    }
}
