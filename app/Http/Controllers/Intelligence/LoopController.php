<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\LoopRecord;
use App\Services\Memory\LoopContextBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dot.Memory's side of the ecosystem intelligence loop (Dot.Brain's
 * schemas/intelligence-loop.schema.json, ADR-0015).
 *
 * Memory remembers; it does not reason and does not execute. These
 * endpoints therefore only ever RECORD what other platforms did, and
 * ANSWER the question "what do we know about this subject?" -- they never
 * choose an action. Every write is idempotent on event_id so a replayed
 * message cannot inflate the history the system learns from.
 */
class LoopController extends Controller
{
    /**
     * Record one observation: something happened, worth remembering.
     */
    public function storeEvent(Request $request): JsonResponse
    {
        return $this->record($request, 'observation', [
            'signature' => 'nullable|string|max:255',
        ]);
    }

    /**
     * Record a decision Dot.Brain made, with the reasoning that produced it.
     */
    public function storeDecision(Request $request): JsonResponse
    {
        return $this->record($request, 'decision', [
            'signature' => 'nullable|string|max:255',
            'confidence' => 'required|numeric|min:0|max:1',
            'risk' => 'required|numeric|min:0|max:1',
            'autonomy_level' => 'required|string|in:'.implode(',', LoopRecord::AUTONOMY_LEVELS),
            'requires_approval' => 'nullable|boolean',
        ]);
    }

    /**
     * Record an action an executor platform performed (or refused).
     */
    public function storeAction(Request $request): JsonResponse
    {
        return $this->record($request, 'action', [
            'signature' => 'nullable|string|max:255',
            'action_kind' => 'required|string|max:255',
            'executor_platform' => 'required|string|max:255',
            'mechanic_ref' => 'nullable|string|max:255',
            'approval_status' => 'nullable|string|in:'.implode(',', LoopRecord::APPROVAL_STATUSES),
            'execution_status' => 'required|string|in:'.implode(',', LoopRecord::EXECUTION_STATUSES),
        ]);
    }

    /**
     * Record what actually happened afterwards. This is the step that turns
     * storage into learning: an ungraded decision teaches nothing, so the
     * verdict here is what later raises or lowers confidence in the same
     * kind of action.
     */
    public function storeOutcome(Request $request): JsonResponse
    {
        return $this->record($request, 'outcome', [
            'signature' => 'nullable|string|max:255',
            'verdict' => 'required|string|in:'.implode(',', LoopRecord::VERDICTS),
            'measure' => 'nullable|string|max:255',
        ]);
    }

    /**
     * "What do we know?" -- the evidence pack Dot.Brain asks for before
     * deciding, so it never has to rediscover what Memory already holds.
     */
    public function context(Request $request, LoopContextBuilder $builder): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => 'required|string|max:255',
            'subject_id' => 'required|string|max:255',
            'signature' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:255',
        ]);

        return response()->json(['data' => $builder->build(
            $validated['subject_type'],
            $validated['subject_id'],
            $validated['signature'] ?? null,
            $validated['platform'] ?? null,
        )]);
    }

    /**
     * @param  array<string, string>  $extraRules
     */
    private function record(Request $request, string $stage, array $extraRules): JsonResponse
    {
        $validated = $request->validate([
            'loop_id' => 'required|string|max:255',
            'event_id' => 'required|string|max:255',
            'platform' => 'required|string|max:255',
            'source' => 'nullable|string|max:255',
            'subject_type' => 'required|string|max:255',
            'subject_id' => 'required|string|max:255',
            'subject_label' => 'nullable|string|max:255',
            'team_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'occurred_at' => 'required|date',
            'detail' => 'nullable|array',
            ...$extraRules,
        ]);

        $existing = LoopRecord::query()->where('event_id', $validated['event_id'])->first();

        // Idempotent on event_id: a replayed message must not inflate the
        // history the system grades itself against.
        if ($existing !== null) {
            $existing->update([...$validated, 'stage' => $stage]);

            return response()->json(['data' => $existing->refresh()], 200);
        }

        $record = LoopRecord::query()->create([...$validated, 'stage' => $stage]);

        return response()->json(['data' => $record], 201);
    }
}
