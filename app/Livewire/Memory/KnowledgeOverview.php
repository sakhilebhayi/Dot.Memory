<?php

namespace App\Livewire\Memory;

use App\Models\OpsIncident;
use App\Services\Memory\KnowledgeTranslator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The landing view of the knowledge platform: what the ecosystem knows,
 * what it learned recently, and how that knowledge is being used --
 * answered from the guardian's real incident archive, translated to
 * human language. Never raw records, never internal vocabulary.
 */
class KnowledgeOverview extends Component
{
    /**
     * @return array{total: int, fixed: int, watching: int, platforms: int}
     */
    #[Computed]
    public function figures(): array
    {
        $incidents = OpsIncident::query()->get();

        return [
            'total' => $incidents->count(),
            'fixed' => $incidents->where('status', 'resolved')->count(),
            'watching' => $incidents->whereIn('status', ['open', 'remediating'])->count(),
            'platforms' => $incidents->pluck('platform')->unique()->count(),
        ];
    }

    /**
     * Newest knowledge first, already translated for display.
     *
     * Deduplicated by pattern. Listing raw incidents meant one noisy problem
     * could fill the panel with five copies of its own headline, which reads
     * as five things learned when it is one thing learned five times. The
     * repeat count says so explicitly instead.
     *
     * @return Collection<int, array{incident: OpsIncident, human: array<string, mixed>, occurrences: int}>
     */
    #[Computed]
    public function recentKnowledge(): Collection
    {
        $translator = app(KnowledgeTranslator::class);

        return OpsIncident::query()
            ->orderByDesc('detected_at')
            ->get()
            ->groupBy(fn (OpsIncident $incident): string => $incident->platform.'|'.$incident->signature)
            ->map(function (Collection $group) use ($translator): array {
                /** @var OpsIncident $latest */
                $latest = $group->first();

                return [
                    'incident' => $latest,
                    'human' => $translator->translate($latest, $this->historyFor($latest)),
                    'occurrences' => $group->count(),
                ];
            })
            ->sortByDesc(fn (array $entry): string => $entry['incident']->detected_at->toIso8601String())
            ->take(5)
            ->values();
    }

    /**
     * The single line the page opens with. A dashboard's first job is to
     * answer "do I need to do anything?" -- four equal metric tiles make a
     * reader do that arithmetic themselves, so the answer is computed here
     * and stated in words.
     *
     * @return array{tone: string, headline: string, detail: string, link: array{label: string, url: string}|null}
     */
    #[Computed]
    public function attention(): array
    {
        $watching = OpsIncident::query()->whereIn('status', ['open', 'remediating'])->get();
        $needsPerson = $watching->where('status', 'escalated')->count()
            + OpsIncident::query()->where('status', 'escalated')->count();
        $stale = $watching->filter(fn (OpsIncident $i): bool => $i->detected_at->lt(now()->subDay()));

        if (OpsIncident::query()->count() === 0) {
            return [
                'tone' => 'quiet',
                'headline' => 'Nothing to review yet',
                'detail' => 'As the Dot platforms run, what they learn will collect here.',
                'link' => null,
            ];
        }

        if ($needsPerson > 0) {
            return [
                'tone' => 'urgent',
                'headline' => $needsPerson === 1
                    ? 'One problem needs a person'
                    : "{$needsPerson} problems need a person",
                'detail' => 'Automatic remediation was not safe, so these were handed over for a human to investigate.',
                'link' => ['label' => 'Review them', 'url' => route('knowledge.index', ['status' => 'escalated'])],
            ];
        }

        if ($stale->isNotEmpty()) {
            return [
                'tone' => 'warning',
                'headline' => $stale->count() === 1
                    ? 'One problem has been unresolved for over a day'
                    : "{$stale->count()} problems have been unresolved for over a day",
                'detail' => 'These are still being watched, but they have not cleared on their own.',
                'link' => ['label' => 'See what is stuck', 'url' => route('knowledge.insights')],
            ];
        }

        if ($watching->isNotEmpty()) {
            return [
                'tone' => 'watching',
                'headline' => $watching->count() === 1
                    ? 'One problem is being watched'
                    : "{$watching->count()} problems are being watched",
                'detail' => 'Nothing needs you right now -- the guardian is tracking these and will act or escalate.',
                'link' => ['label' => 'Look anyway', 'url' => route('knowledge.index', ['status' => 'open'])],
            ];
        }

        return [
            'tone' => 'clear',
            'headline' => 'Everything is clear',
            'detail' => 'No open problems across the platforms being watched.',
            'link' => null,
        ];
    }

    /**
     * What the state bar reports alongside the state itself: an instrument
     * that says "all clear" is only trustworthy if it also says what it is
     * watching and when it last heard anything.
     *
     * @return array{platforms: int, last_seen: string}
     */
    #[Computed]
    public function coverage(): array
    {
        $lastSeen = OpsIncident::query()->max('detected_at');

        return [
            'platforms' => OpsIncident::query()->distinct()->count('platform'),
            'last_seen' => is_string($lastSeen)
                ? Carbon::parse($lastSeen)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE).' ago'
                : 'never',
        ];
    }

    /**
     * Proof of usefulness: how often Dot.Brain consulted this memory.
     *
     * @return array{lookups_week: int, last_lookup: string|null}
     */
    #[Computed]
    public function brainUsage(): array
    {
        $lastLookup = DB::table('ops_recall_events')->max('created_at');

        return [
            'lookups_week' => (int) DB::table('ops_recall_events')
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
            'last_lookup' => is_string($lastLookup) ? $lastLookup : null,
        ];
    }

    /**
     * @return array{occurrences: int, resolved: int, rolled_back: int}
     */
    private function historyFor(OpsIncident $incident): array
    {
        $siblings = OpsIncident::query()
            ->where('platform', $incident->platform)
            ->where('signature', $incident->signature)
            ->get();

        return [
            'occurrences' => $siblings->count(),
            'resolved' => $siblings->where('status', 'resolved')->count(),
            'rolled_back' => $siblings->where('rollback_occurred', true)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.memory.knowledge-overview');
    }
}
