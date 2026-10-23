<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeOutcome;
use App\Services\Knowledge\KnowledgeRecommendations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Signed server-to-server API (see docs/knowledge-recommendations-api.md). Which consumer may call which
 * action is decided by the route's VerifyKnowledgeSignature middleware, not here.
 */
class KnowledgeRecommendationController extends Controller
{
    public function __construct(private readonly KnowledgeRecommendations $recommendations) {}

    /** InfoDot: the moderator queue and history. */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'decided', 'all'])],
            'verdict' => ['nullable', Rule::in((array) config('knowledge.verdicts'))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('knowledge.max_limit', 100)],
            'cursor' => ['nullable', 'string', 'max:255'],
        ]);

        $page = $this->recommendations->list(
            $validated['status'] ?? 'open',
            $validated['verdict'] ?? null,
            (int) ($validated['limit'] ?? config('knowledge.default_limit', 50)),
            $validated['cursor'] ?? null,
        );

        return response()->json(['items' => $page['items'], 'next_cursor' => $page['next_cursor']]);
    }

    /** InfoDot: report what a moderator did with a recommendation. */
    public function storeOutcome(Request $request, string $decisionEventId): JsonResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', 'string', Rule::in(KnowledgeOutcome::OUTCOMES)],
            'actor_ref' => ['required', 'string', 'uuid'],
        ]);

        $result = $this->recommendations->recordOutcome($decisionEventId, $validated['outcome'], $validated['actor_ref']);

        return response()->json(['data' => $result['state']], $result['recorded'] ? 201 : 200);
    }

    /** Brain: what moderators decided, so it can grade itself. No actor_ref. */
    public function outcomes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('knowledge.outcomes_max_limit', 500)],
        ]);

        // Stored in the app timezone; a Carbon bound as-is would be formatted in its own offset.
        $since = isset($validated['since'])
            ? Carbon::parse($validated['since'])->setTimezone((string) config('app.timezone'))
            : null;

        return response()->json(['items' => $this->recommendations->outcomesSince(
            $since,
            (int) ($validated['limit'] ?? config('knowledge.outcomes_default_limit', 100)),
        )]);
    }
}
