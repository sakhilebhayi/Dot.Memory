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
 * How the ecosystem's knowledge has evolved: problems discovered, fixed,
 * escalated, undone, and consulted -- one chronological stream grouped by
 * day, so memory reads as a living system rather than a static table.
 */
class KnowledgeTimeline extends Component
{
    /**
     * @return Collection<string, Collection<int, array{at: Carbon, icon: string, text: string, link: string|null}>>
     *                                                                                                               Keyed by human day label, newest day first.
     */
    #[Computed]
    public function days(): Collection
    {
        $translator = app(KnowledgeTranslator::class);
        $events = collect();

        foreach (OpsIncident::query()->get() as $incident) {
            $human = $translator->translate($incident);
            $link = route('knowledge.show', $incident->incident_uid);

            $events->push([
                'at' => $incident->detected_at,
                'icon' => 'visibility',
                'text' => "Problem discovered: {$human['headline']}.",
                'link' => $link,
            ]);

            if ($incident->rollback_occurred) {
                $events->push([
                    'at' => $incident->updated_at,
                    'icon' => 'undo',
                    'text' => "A fix for \"{$human['headline']}\" did not hold and was undone.",
                    'link' => $link,
                ]);
            }

            if ($incident->resolved_at !== null && in_array($incident->status, ['resolved', 'escalated'], true)) {
                $events->push([
                    'at' => $incident->resolved_at,
                    'icon' => $incident->status === 'resolved' ? 'task_alt' : 'person_alert',
                    'text' => $incident->status === 'resolved'
                        ? "Fixed: {$human['headline']} -- the response is now reusable knowledge."
                        : "Handed to a person: {$human['headline']}.",
                    'link' => $link,
                ]);
            }
        }

        foreach (DB::table('ops_recall_events')->orderByDesc('created_at')->limit(50)->get() as $event) {
            $platform = $translator->platformLabel((string) $event->platform);
            $events->push([
                'at' => Carbon::parse((string) $event->created_at),
                'icon' => 'psychology',
                'text' => "Dot.Brain consulted this memory before responding to a problem on {$platform}.",
                'link' => null,
            ]);
        }

        return $events
            ->sortByDesc('at')
            ->groupBy(fn (array $event): string => $event['at']->timezone(config('app.timezone'))->format('l, j F Y'));
    }

    public function render()
    {
        return view('livewire.memory.knowledge-timeline');
    }
}
