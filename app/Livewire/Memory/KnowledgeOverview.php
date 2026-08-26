<?php

namespace App\Livewire\Memory;

use App\Models\OpsIncident;
use App\Services\Memory\KnowledgeTranslator;
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
     * @return Collection<int, array{incident: OpsIncident, human: array<string, mixed>}>
     */
    #[Computed]
    public function recentKnowledge(): Collection
    {
        $translator = app(KnowledgeTranslator::class);

        return OpsIncident::query()
            ->orderByDesc('detected_at')
            ->limit(5)
            ->get()
            ->map(fn (OpsIncident $incident): array => [
                'incident' => $incident,
                'human' => $translator->translate($incident, $this->historyFor($incident)),
            ]);
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
