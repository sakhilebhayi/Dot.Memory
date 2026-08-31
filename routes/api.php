<?php

use App\Http\Controllers\Intelligence\LoopController;
use App\Http\Controllers\Ops\OpsIncidentController;
use App\Http\Controllers\Ops\GuardianBlupinController;
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

// dot-guardian/v1 health for the BluPin signal pipeline: Memory holds the
// loop records whose arrival IS that pipeline's health, so it answers.
Route::middleware('auth:sanctum')->get('/guardian/blupin',
    [GuardianBlupinController::class, 'show']);
