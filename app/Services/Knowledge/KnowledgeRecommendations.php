<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeOutcome;
use App\Models\LoopRecord;
use App\Services\Memory\LoopRecordAccess;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Knowledge recommendations (R-9 sections 5 and 6): the decisions Dot.Brain recorded about InfoDot
 * contributions, the moderator outcome for each, and the outcome feed Brain grades itself with.
 *
 * Memory is not the ledger and holds no person data here: every record is about a contribution
 * (an opaque ref), never a person. The decision rows are read ONLY through LoopRecordAccess::readable()
 * (so participation rows and person subjects can never appear), narrowed to the one subject type and
 * signature this API serves, and only the whitelisted fields below ever leave this class: never the raw
 * `detail` blob of any record.
 */
class KnowledgeRecommendations
{
    /** Most decision rows examined for one list request, however selective the verdict filter. */
    private const SCAN_CAP = 2000;

    public function __construct(private readonly LoopRecordAccess $access) {}

    /**
     * @param  'open'|'decided'|'all'  $status
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function list(string $status, ?string $verdict, int $limit, ?string $cursor): array
    {
        $position = $cursor === null ? null : $this->decodeCursor($cursor);
        $batchSize = max(100, $limit * 2);

        $base = $this->decisions();
        if ($status === 'open') {
            $base->whereNotIn('event_id', KnowledgeOutcome::query()->select('decision_event_id'));
        } elseif ($status === 'decided') {
            $base->whereIn('event_id', KnowledgeOutcome::query()->select('decision_event_id'));
        }

        $kept = [];   // list of [LoopRecord, verdict]
        $scanned = 0;
        $last = null; // last row examined
        $exhausted = false;
        $overflow = false;

        while (true) {
            $query = clone $base;
            if ($position !== null) {
                [$at, $id] = $position;
                $query->where(fn (Builder $q) => $q->where('occurred_at', '<', $at)
                    ->orWhere(fn (Builder $q2) => $q2->where('occurred_at', '=', $at)->where('id', '<', $id)));
            }

            $rows = $query->orderByDesc('occurred_at')->orderByDesc('id')->limit($batchSize)->get();

            foreach ($rows as $row) {
                $scanned++;
                $last = $row;
                $rowVerdict = $this->verdictOf($row);

                if ($rowVerdict === null || ($verdict !== null && $rowVerdict !== $verdict)) {
                    continue;
                }

                if (count($kept) === $limit) {
                    $overflow = true;
                    break 2;
                }

                $kept[] = [$row, $rowVerdict];
            }

            if ($rows->count() < $batchSize) {
                $exhausted = true;
                break;
            }

            if ($scanned >= self::SCAN_CAP) {
                break;
            }

            $position = [$last->getRawOriginal('occurred_at'), (int) $last->getKey()];
        }

        $latest = $this->latestOutcomes(array_map(fn (array $pair) => $pair[0]->event_id, $kept));

        $items = array_map(fn (array $pair) => $this->item($pair[0], $pair[1], $latest[$pair[0]->event_id] ?? null), $kept);

        $nextFrom = null;
        if ($overflow && $kept !== []) {
            $nextFrom = $kept[count($kept) - 1][0];
        } elseif (! $exhausted && $last !== null) {
            $nextFrom = $last; // scan cap reached before the page filled: continue from where we stopped
        }

        return [
            'items' => $items,
            'next_cursor' => $nextFrom === null ? null : $this->encodeCursor($nextFrom),
        ];
    }

    /**
     * Record a moderator outcome. Append-only; idempotent on (decision, outcome).
     *
     * @return array{state: array<string, mixed>, recorded: bool}
     */
    public function recordOutcome(string $decisionEventId, string $outcome, string $actorRef): array
    {
        return DB::transaction(function () use ($decisionEventId, $outcome, $actorRef): array {
            $decision = $this->decisions()->where('event_id', $decisionEventId)->lockForUpdate()->first();

            if ($decision === null || $this->verdictOf($decision) === null) {
                throw new HttpResponseException(response()->json([
                    'code' => 'decision_not_found',
                    'message' => 'No knowledge recommendation has that decision_event_id.',
                ], 404));
            }

            $history = KnowledgeOutcome::query()
                ->where('decision_event_id', $decision->event_id)
                ->orderBy('decided_at')->orderBy('id')
                ->get();
            $first = $history->first();
            $latest = $history->last();

            if ($latest !== null && $latest->outcome === $outcome) {
                return ['state' => $this->state($decision, $latest), 'recorded' => false];
            }

            if ($first !== null && now()->greaterThan($first->decided_at->copy()->addDays((int) config('knowledge.change_window_days', 30)))) {
                throw new HttpResponseException(response()->json([
                    'code' => 'outcome_locked',
                    'message' => 'An outcome can be changed only within '.(int) config('knowledge.change_window_days', 30).' days of the first one.',
                ], 409));
            }

            $row = KnowledgeOutcome::query()->create([
                'decision_event_id' => $decision->event_id,
                'contribution_ref' => $decision->subject_id,
                'outcome' => $outcome,
                'actor_ref' => $actorRef,
                'decided_at' => now(),
            ]);

            return ['state' => $this->state($decision, $row), 'recorded' => true];
        });
    }

    /**
     * Outcome rows for Brain, oldest first, at or after $since. Reads knowledge_outcomes only: no decision
     * detail, no actor_ref. Each row is one outcome event, so a changed mind appears as a later row.
     *
     * @return list<array<string, mixed>>
     */
    public function outcomesSince(?CarbonInterface $since, int $limit): array
    {
        return KnowledgeOutcome::query()
            ->when($since !== null, fn (Builder $q) => $q->where('decided_at', '>=', $since))
            ->orderBy('decided_at')->orderBy('id')
            ->limit($limit)
            ->get(['contribution_ref', 'decision_event_id', 'outcome', 'decided_at'])
            ->map(fn (KnowledgeOutcome $o) => [
                'contribution_ref' => $o->contribution_ref,
                'decision_event_id' => $o->decision_event_id,
                'outcome' => $o->outcome,
                'decided_at' => $o->decided_at->toISOString(),
            ])->all();
    }

    /**
     * The only door to decision rows: readable() first, then this API's one subject type and signature.
     *
     * @return Builder<LoopRecord>
     */
    private function decisions(): Builder
    {
        return $this->access->readable(LoopRecord::query())
            ->where('stage', 'decision')
            ->where('subject_type', (string) config('knowledge.subject_type'))
            ->where('signature', (string) config('knowledge.signature'));
    }

    /**
     * @return array<string, mixed>
     */
    private function item(LoopRecord $record, string $verdict, ?KnowledgeOutcome $outcome): array
    {
        $detail = $this->detailOf($record);

        return [
            'decision_event_id' => $record->event_id,
            'contribution_ref' => $record->subject_id,
            'verdict' => $verdict,
            'confidence' => $record->confidence,
            'rationale' => $this->rationale($this->firstOf($detail, ['rationale', 'decision.rationale'])),
            'rule_version' => $this->scalarOrNull($this->firstOf($detail, ['rule_version', 'decision.rule_version'])),
            'decided_at' => $record->occurred_at?->toISOString(),
            'status' => $outcome?->outcome ?? 'open',
            'outcome_at' => $outcome?->decided_at->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function state(LoopRecord $decision, KnowledgeOutcome $latest): array
    {
        return [
            'decision_event_id' => $decision->event_id,
            'contribution_ref' => $decision->subject_id,
            'status' => $latest->outcome,
            'outcome_at' => $latest->decided_at->toISOString(),
        ];
    }

    /**
     * The newest outcome row per decision, for a page of decisions.
     *
     * @param  list<string>  $eventIds
     * @return array<string, KnowledgeOutcome>
     */
    private function latestOutcomes(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $latest = [];
        $rows = KnowledgeOutcome::query()->whereIn('decision_event_id', $eventIds)->orderBy('decided_at')->orderBy('id')->get();
        foreach ($rows as $row) {
            $latest[$row->decision_event_id] = $row; // ascending, so the last one wins
        }

        return $latest;
    }

    /**
     * @return array<string, mixed>
     */
    private function detailOf(LoopRecord $record): array
    {
        try {
            $detail = $record->detail;
        } catch (DecryptException) {
            return [];
        }

        return is_array($detail) ? $detail : [];
    }

    /** The recommendation's verdict, or null when the row does not carry a valid one (then it is not a recommendation). */
    private function verdictOf(LoopRecord $record): ?string
    {
        if ($record->subject_type !== config('knowledge.subject_type')) {
            return null;
        }

        $detail = $this->detailOf($record);
        $verdict = $this->firstOf($detail, ['verdict', 'decision.verdict', 'recommendation', 'decision.recommendation']);

        return is_string($verdict) && in_array($verdict, (array) config('knowledge.verdicts'), true) ? $verdict : null;
    }

    /**
     * Normalise the rationale to [{rule, effect, detail}]; plain strings (Brain's loop schema allows them) become `detail`.
     *
     * @return list<array{rule: string|null, effect: string|null, detail: string|null}>
     */
    private function rationale(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (is_string($entry)) {
                $out[] = ['rule' => null, 'effect' => null, 'detail' => $entry];
            } elseif (is_array($entry)) {
                $out[] = [
                    'rule' => $this->scalarOrNull($entry['rule'] ?? null),
                    'effect' => $this->scalarOrNull($entry['effect'] ?? null),
                    'detail' => $this->scalarOrNull($entry['detail'] ?? null),
                ];
            }
        }

        return $out;
    }

    /**
     * The first non-null value among the given dot paths. Brain's detail is canonically flat
     * (`verdict`, `rationale`, `rule_version`); the nested `decision.*` and `recommendation` spellings
     * of its loop schema are understood too.
     *
     * @param  array<string, mixed>  $detail
     * @param  list<string>  $paths
     */
    private function firstOf(array $detail, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($detail, $path);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function scalarOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function encodeCursor(LoopRecord $record): string
    {
        return rtrim(strtr(base64_encode((string) json_encode([$record->getRawOriginal('occurred_at'), (int) $record->getKey()])), '+/', '-_'), '=');
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeCursor(string $cursor): array
    {
        $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true);

        if (! is_array($decoded) || count($decoded) !== 2 || ! is_string($decoded[0]) || ! is_int($decoded[1])
            || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d+)?$/', $decoded[0]) !== 1) {
            throw new HttpResponseException(response()->json([
                'code' => 'invalid_cursor',
                'message' => 'The cursor is not valid.',
            ], 422));
        }

        return [$decoded[0], $decoded[1]];
    }
}
