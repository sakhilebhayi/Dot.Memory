<?php

namespace App\Livewire\Memory;

use App\Models\OpsIncident;
use App\Services\Memory\KnowledgeTranslator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Proactive intelligence rather than raw data: recurring problems (with
 * how often the fix actually worked), problems being watched too long,
 * and honest coverage gaps -- which platforms contribute no operational
 * knowledge yet. Every insight is a sentence, never a metric dump.
 */
class KnowledgeInsights extends Component
{
    /**
     * @return Collection<int, array{headline: string, occurrences: int, fixed: int, trust: array{label: string, explanation: string}, latest_uid: string}>
     */
    #[Computed]
    public function recurringProblems(): Collection
    {
        $translator = app(KnowledgeTranslator::class);

        return OpsIncident::query()
            ->get()
            ->groupBy(fn (OpsIncident $incident): string => $incident->platform.'|'.$incident->signature)
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(function (Collection $group) use ($translator): array {
                /** @var OpsIncident $latest */
                $latest = $group->sortByDesc('detected_at')->first();
                $history = [
                    'occurrences' => $group->count(),
                    'resolved' => $group->where('status', 'resolved')->count(),
                    'rolled_back' => $group->where('rollback_occurred', true)->count(),
                ];

                return [
                    'headline' => $translator->translate($latest)['headline'],
                    'occurrences' => $group->count(),
                    'fixed' => $history['resolved'],
                    'trust' => $translator->translate($latest, $history)['trust'],
                    'latest_uid' => $latest->incident_uid,
                ];
            })
            ->sortByDesc('occurrences')
            ->values();
    }

    /**
     * Open problems that have been waiting too long for a resolution.
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

    public function render()
    {
        return view('livewire.memory.knowledge-insights');
    }
}
