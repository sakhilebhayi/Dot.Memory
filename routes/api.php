<?php

use App\Http\Controllers\Ops\OpsIncidentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Operational-memory API for the Dot.Brain guardian: incident archives in,
// signature-recall summaries out. Authenticated by a Sanctum personal
// access token issued to the guardian service account.
Route::middleware('auth:sanctum')->prefix('ops')->group(function () {
    Route::post('/incidents', [OpsIncidentController::class, 'store']);
    Route::patch('/incidents/{incidentUid}', [OpsIncidentController::class, 'update']);
    Route::get('/incidents', [OpsIncidentController::class, 'index']);
    Route::get('/recall', [OpsIncidentController::class, 'recall']);
});
