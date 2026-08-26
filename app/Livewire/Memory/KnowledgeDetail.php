<?php

namespace App\Livewire\Memory;

use App\Models\OpsIncident;
use App\Services\Memory\KnowledgeTranslator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The memory profile: one piece of operational knowledge presented the way
 * a person would retell it -- what happened, what was learned, why it
 * matters, how much to trust it, what it's connected to, and who has used
 * it. The raw envelope and decrypted narrative stay available behind a
 * collapsed "Technical detail" panel (progressive disclosure).
 */
class KnowledgeDetail extends Component
{
    public OpsIncident $incident;

    public function mount(string $incidentUid): void
    {
        $this->incident = OpsIncident::query()
            ->where('incident_uid', $incidentUid)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function human(): array
    {
        $siblings = $this->siblings();

        return app(KnowledgeTranslator::class)->translate($this->incident, [
            'occurrences' => $siblings->count() + 1,
            'resolved' => $siblings->where('status', 'resolved')->count()
                + (int) ($this->incident->status === 'resolved'),
            'rolled_back' => $siblings->where('rollback_occurred', true)->count()
                + (int) $this->incident->rollback_occurred,
        ]);
    }

    /**
     * The same problem's earlier occurrences, translated.
     *
     * @return Collection<int, array{incident: OpsIncident, human: array<string, mixed>}>
     */
    #[Computed]
    public function relatedKnowledge(): Collection
    {
        $translator = app(KnowledgeTranslator::class);

        return $this->siblings()
            ->sortByDesc('detected_at')
            ->take(5)
            ->map(fn (OpsIncident $incident): array => [
                'incident' => $incident,
                'human' => $translator->translate($incident),
            ])
            ->values();
    }

    /**
     * Life of this record as plain events, oldest first.
     *
     * @return list<array{at: Carbon, event: string}>
     */
    #[Computed]
    public function history(): array
    {
        $events = [[
            'at' => $this->incident->detected_at,
            'event' => 'The guardian noticed the problem and opened this record.',
        ]];

        if ($this->incident->rollback_occurred) {
            $events[] = [
                'at' => $this->incident->updated_at,
                'event' => 'An attempted fix did not hold and was undone.',
            ];
        }

        if ($this->incident->resolved_at !== null) {
            $events[] = [
                'at' => $this->incident->resolved_at,
                'event' => match ($this->incident->status) {
                    'resolved' => 'The problem was confirmed fixed and this record became reusable knowledge.',
                    'escalated' => 'The guardian handed this to a person to investigate.',
                    default => 'This record was closed.',
                },
            ];
        }

        return $events;
    }

    /**
     * @return array{count: int, last: string|null}
     */
    #[Computed]
    public function usage(): array
    {
        $events = DB::table('ops_recall_events')
            ->where('platform', $this->incident->platform)
            ->where('signature', $this->incident->signature)
            ->orderByDesc('created_at');

        $last = $events->clone()->value('created_at');

        return [
            'count' => $events->count(),
            'last' => is_string($last) ? $last : null,
        ];
    }

    /**
     * @return Collection<int, OpsIncident>
     */
    private function siblings(): Collection
    {
        return OpsIncident::query()
            ->where('platform', $this->incident->platform)
            ->where('signature', $this->incident->signature)
            ->where('id', '!=', $this->incident->id)
            ->get();
    }

    public function render()
    {
        return view('livewire.memory.knowledge-detail');
    }
}
