<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\OpsIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Operational-memory API for the Dot.Brain guardian.
 *
 * Stores incident archives and answers signature-recall queries so the
 * guardian's decision engine can weigh "have we seen this before, and did
 * the fix work?". Filtering and aggregation touch ONLY envelope columns
 * (platform, signature, status, rollback flag, timestamps); the `record`
 * blob passes through opaque and encrypted -- wiki.md §2, store without
 * reading.
 */
class OpsIncidentController extends Controller
{
    /**
     * Create (or idempotently upsert by incident_uid) one incident archive.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'incident_uid' => 'required|string|max:255',
            'platform' => 'required|string|max:255',
            'environment' => 'required|string|max:255',
            'detection_source' => 'required|string|max:255',
            'signature' => 'required|string|max:255',
            'component' => 'required|string|max:255',
            'severity' => 'required|string|in:'.implode(',', OpsIncident::SEVERITIES),
            'status' => 'required|string|in:'.implode(',', OpsIncident::STATUSES),
            'tests_result' => 'nullable|string|max:255',
            'deploy_result' => 'nullable|string|max:255',
            'validation_result' => 'nullable|string|max:255',
            'rollback_occurred' => 'nullable|boolean',
            'detected_at' => 'required|date',
            'resolved_at' => 'nullable|date',
            'record' => 'nullable|array',
        ]);

        $existing = OpsIncident::query()
            ->where('incident_uid', $validated['incident_uid'])
            ->first();

        if ($existing !== null) {
            $existing->update($validated);

            return response()->json(['data' => $existing->refresh()], 200);
        }

        $incident = OpsIncident::query()->create($validated);

        return response()->json(['data' => $incident], 201);
    }

    /**
     * Partially update one incident archive by its uid.
     */
    public function update(Request $request, string $incidentUid): JsonResponse
    {
        $incident = OpsIncident::query()
            ->where('incident_uid', $incidentUid)
            ->firstOrFail();

        $validated = $request->validate([
            'severity' => 'sometimes|string|in:'.implode(',', OpsIncident::SEVERITIES),
            'status' => 'sometimes|string|in:'.implode(',', OpsIncident::STATUSES),
            'tests_result' => 'nullable|string|max:255',
            'deploy_result' => 'nullable|string|max:255',
            'validation_result' => 'nullable|string|max:255',
            'rollback_occurred' => 'nullable|boolean',
            'resolved_at' => 'nullable|date',
            'record' => 'nullable|array',
        ]);

        $incident->update($validated);

        return response()->json(['data' => $incident->refresh()]);
    }

    /**
     * List incident archives, newest first, filtered by envelope fields.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'nullable|string|max:255',
            'signature' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:'.implode(',', OpsIncident::STATUSES),
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $incidents = OpsIncident::query()
            ->when($validated['platform'] ?? null, fn ($query, $platform) => $query->where('platform', $platform))
            ->when($validated['signature'] ?? null, fn ($query, $signature) => $query->where('signature', $signature))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('detected_at')
            ->limit((int) ($validated['limit'] ?? 50))
            ->get();

        return response()->json(['data' => $incidents]);
    }

    /**
     * Summarise history for one (platform, signature) pair: how often it
     * recurred, how remediation went, and the most recent successful fix.
     */
    public function recall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|max:255',
            'signature' => 'required|string|max:255',
        ]);

        $incidents = OpsIncident::query()
            ->where('platform', $validated['platform'])
            ->where('signature', $validated['signature'])
            ->orderByDesc('detected_at')
            ->get();

        // Usage proof for the "how memory helps Dot.Brain" surface --
        // envelope-only, and never allowed to break the lookup itself.
        try {
            \Illuminate\Support\Facades\DB::table('ops_recall_events')->insert([
                'platform' => $validated['platform'],
                'signature' => $validated['signature'],
                'matches' => $incidents->count(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Recall must answer even if usage accounting fails.
        }

        $resolved = $incidents->where('status', 'resolved')->count();
        $rolledBack = $incidents->where('rollback_occurred', true)->count();
        $closed = $resolved + $incidents->whereIn('status', ['rolled_back', 'escalated'])->count();

        $lastSuccessfulFix = $incidents
            ->where('status', 'resolved')
            ->where('rollback_occurred', false)
            ->sortByDesc('resolved_at')
            ->first();

        return response()->json([
            'data' => [
                'matches' => $incidents->count(),
                'resolved' => $resolved,
                'rolled_back' => $rolledBack,
                'success_rate' => $closed > 0 ? round($resolved / $closed, 2) : null,
                'last_successful_fix' => $lastSuccessfulFix,
                'incidents' => $incidents->take(10)->values(),
            ],
        ]);
    }
}
