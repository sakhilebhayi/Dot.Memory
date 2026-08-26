<?php

namespace App\Services\Memory;

use App\Models\LoopRecord;
use Illuminate\Support\Collection;

/**
 * Builds the evidence pack Dot.Brain asks for before it decides.
 *
 * This is Memory answering "what do we know, what was tried, and what
 * worked?" -- and it is deliberately the ONLY place the loop's learning is
 * computed, because learning must come from recorded outcomes rather than
 * from anyone's assertion. Nothing here interprets or recommends; that is
 * Brain's job (ADR-0015).
 */
class LoopContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $subjectType, string $subjectId, ?string $signature = null, ?string $platform = null): array
    {
        $records = LoopRecord::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->when($platform !== null, fn ($query) => $query->where('platform', $platform))
            ->orderBy('occurred_at')
            ->get();

        $scoped = $signature === null
            ? $records
            : $records->where('signature', $signature);

        return [
            'subject' => [
                'type' => $subjectType,
                'id' => $subjectId,
                'label' => $records->last()?->subject_label,
            ],
            'known' => $records->isNotEmpty(),
            'observations' => $records->where('stage', 'observation')->count(),
            'first_seen' => $records->first()?->occurred_at?->toISOString(),
            'last_seen' => $records->last()?->occurred_at?->toISOString(),
            'timeline' => $this->timeline($records),
            'what_was_tried' => $this->whatWasTried($records),
            'what_worked' => $this->whatWorked($scoped),
            'recurrence' => $this->recurrence($records),
            'gaps' => $this->gaps($records),
        ];
    }

    /**
     * The plain sequence of what happened, oldest first.
     *
     * @param  Collection<int, LoopRecord>  $records
     * @return list<array<string, mixed>>
     */
    private function timeline(Collection $records): array
    {
        return $records->map(fn (LoopRecord $record): array => [
            'at' => $record->occurred_at?->toISOString(),
            'stage' => $record->stage,
            'platform' => $record->platform,
            'signature' => $record->signature,
            'summary' => $this->summarise($record),
        ])->values()->all();
    }

    /**
     * Past actions with the verdict that followed them, so Brain can see
     * not just what was attempted but how it landed.
     *
     * @param  Collection<int, LoopRecord>  $records
     * @return list<array<string, mixed>>
     */
    private function whatWasTried(Collection $records): array
    {
        $outcomesByLoop = $records->where('stage', 'outcome')->keyBy('loop_id');

        return $records->where('stage', 'action')->map(function (LoopRecord $action) use ($outcomesByLoop): array {
            $outcome = $outcomesByLoop->get($action->loop_id);

            return [
                'loop_id' => $action->loop_id,
                'action_kind' => $action->action_kind,
                'executor_platform' => $action->executor_platform,
                'mechanic_ref' => $action->mechanic_ref,
                'execution_status' => $action->execution_status,
                'at' => $action->occurred_at?->toISOString(),
                'verdict' => $outcome?->verdict,
                'measure' => $outcome?->measure,
            ];
        })->values()->all();
    }

    /**
     * What actually worked, per kind of action: the success rate Brain uses
     * to earn (or lose) confidence. Computed only from loops that reached a
     * verdict -- an action still awaiting its outcome is neither a success
     * nor a failure, and counting it either way would teach the system
     * something untrue.
     *
     * @param  Collection<int, LoopRecord>  $records
     * @return list<array<string, mixed>>
     */
    private function whatWorked(Collection $records): array
    {
        $outcomesByLoop = $records->where('stage', 'outcome')->keyBy('loop_id');

        return $records->where('stage', 'action')
            ->groupBy(fn (LoopRecord $action): string => (string) $action->action_kind)
            ->map(function (Collection $actions, string $kind) use ($outcomesByLoop): array {
                $graded = $actions->filter(fn (LoopRecord $a): bool => $outcomesByLoop->has($a->loop_id));
                $improved = $graded->filter(fn (LoopRecord $a): bool => $outcomesByLoop->get($a->loop_id)?->verdict === 'improved');
                $worsened = $graded->filter(fn (LoopRecord $a): bool => $outcomesByLoop->get($a->loop_id)?->verdict === 'worsened');

                return [
                    'action_kind' => $kind,
                    'attempts' => $actions->count(),
                    'graded' => $graded->count(),
                    'improved' => $improved->count(),
                    'worsened' => $worsened->count(),
                    'success_rate' => $graded->isEmpty()
                        ? null
                        : round($improved->count() / $graded->count(), 2),
                ];
            })->values()->all();
    }

    /**
     * @param  Collection<int, LoopRecord>  $records
     * @return list<array<string, mixed>>
     */
    private function recurrence(Collection $records): array
    {
        return $records->whereNotNull('signature')
            ->groupBy('signature')
            ->map(fn (Collection $group, string $signature): array => [
                'signature' => $signature,
                'occurrences' => $group->where('stage', 'observation')->count(),
                'last_seen' => $group->last()?->occurred_at?->toISOString(),
            ])
            ->sortByDesc('occurrences')
            ->values()
            ->all();
    }

    /**
     * Where the evidence is thin. Naming a gap is itself useful context:
     * Brain should weigh a recommendation differently when nothing has ever
     * been tried, or when everything tried is still ungraded.
     *
     * @param  Collection<int, LoopRecord>  $records
     * @return list<string>
     */
    private function gaps(Collection $records): array
    {
        $gaps = [];

        if ($records->isEmpty()) {
            return ['Nothing has ever been recorded about this subject.'];
        }

        if ($records->where('stage', 'action')->isEmpty()) {
            $gaps[] = 'No action has ever been taken for this subject, so nothing is known about what works.';
        }

        $actions = $records->where('stage', 'action');
        $outcomeLoops = $records->where('stage', 'outcome')->pluck('loop_id');
        $ungraded = $actions->reject(fn (LoopRecord $a): bool => $outcomeLoops->contains($a->loop_id));

        if ($actions->isNotEmpty() && $ungraded->count() === $actions->count()) {
            $gaps[] = 'Every action taken so far is still awaiting an outcome, so none of them can be graded yet.';
        } elseif ($ungraded->isNotEmpty()) {
            $gaps[] = "{$ungraded->count()} action(s) are still awaiting an outcome.";
        }

        return $gaps;
    }

    private function summarise(LoopRecord $record): string
    {
        return match ($record->stage) {
            'observation' => 'Observed by '.$record->platform.($record->signature !== null ? ": {$record->signature}" : ''),
            'decision' => 'Decision recorded (confidence '.($record->confidence ?? '?').', risk '.($record->risk ?? '?').', '.($record->autonomy_level ?? '?').')',
            'action' => ($record->action_kind ?? 'action').' by '.($record->executor_platform ?? '?').' -- '.($record->execution_status ?? '?'),
            'outcome' => 'Outcome: '.($record->verdict ?? '?').($record->measure !== null ? " ({$record->measure})" : ''),
            default => $record->stage,
        };
    }
}
