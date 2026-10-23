<?php

use App\Http\Controllers\GuardianHealthController;
use App\Http\Controllers\Intelligence\LoopController;
use App\Http\Controllers\Knowledge\KnowledgeRecommendationController;
use App\Http\Controllers\Ops\OpsIncidentController;
use App\Http\Controllers\Participation\ParticipationController;
use App\Http\Middleware\VerifyKnowledgeSignature;
use App\Http\Middleware\VerifyParticipationSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Operational-memory API for the Dot.Brain guardian: incident archives in,
// signature-recall summaries out. Authenticated by a Sanctum personal
// access token issued to the guardian service account.
// The ecosystem intelligence loop (Dot.Brain ADR-0015): Memory records
// every stage and answers "what do we know about this subject?". Memory
// never reasons and never executes -- these endpoints only remember.
Route::middleware('auth:sanctum')->prefix('intelligence')->group(function () {
    Route::post('/events', [LoopController::class, 'storeEvent']);
    Route::post('/decisions', [LoopController::class, 'storeDecision']);
    Route::post('/actions', [LoopController::class, 'storeAction']);
    Route::post('/outcomes', [LoopController::class, 'storeOutcome']);
    Route::get('/context', [LoopController::class, 'context']);
});

Route::middleware('auth:sanctum')->prefix('ops')->group(function () {
    Route::post('/incidents', [OpsIncidentController::class, 'store']);
    Route::patch('/incidents/{incidentUid}', [OpsIncidentController::class, 'update']);
    Route::get('/incidents', [OpsIncidentController::class, 'index']);
    Route::get('/recall', [OpsIncidentController::class, 'recall']);
});

// Guardian observability report for Dot.Brain (dot-guardian/v1). Read with
// the guardian's existing Sanctum service token (the same bearer the
// incident archive and recall use), so no second secret exists.
Route::middleware('auth:sanctum')->get('/guardian/health', GuardianHealthController::class)->name('guardian.health');

// Opt-in participation signals (R-9): person-level topic + count, only for people who said yes,
// keyed by InfoDot's user UUID. Server-to-server and HMAC-signed (the Dot.Dopemine scheme), NOT
// Sanctum: the intelligence API above is untouched. See docs/participation-api.md.
Route::middleware(VerifyParticipationSignature::class)->prefix('participation')->group(function () {
    Route::post('/events', [ParticipationController::class, 'storeEvents']);
    Route::put('/{userUuid}/consent', [ParticipationController::class, 'setConsent']);
    Route::get('/{userUuid}/context', [ParticipationController::class, 'context']);
    Route::delete('/{userUuid}', [ParticipationController::class, 'erase']);
});

// Knowledge recommendations (R-9 sections 5/6): Brain's `infodot-contribution` decisions for InfoDot's moderators,
// the moderator outcome back, and the outcome feed Brain grades itself with. Signed like participation but with ONE
// SECRET PER CONSUMER; the consumer decides what may be called (infodot: list + report; brain: the outcome feed).
// Records are about contributions, never people. See docs/knowledge-recommendations-api.md.
Route::middleware(['throttle:knowledge', VerifyKnowledgeSignature::class.':infodot'])->prefix('knowledge-recommendations')->group(function () {
    Route::get('/', [KnowledgeRecommendationController::class, 'index']);
    Route::post('/{decisionEventId}/outcome', [KnowledgeRecommendationController::class, 'storeOutcome']);
});

Route::middleware(['throttle:knowledge', VerifyKnowledgeSignature::class.':brain'])->prefix('knowledge-recommendations')->group(function () {
    Route::get('/outcomes', [KnowledgeRecommendationController::class, 'outcomes']);
});
