<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\Guardian\BlupinLoopReport;
use Illuminate\Http\JsonResponse;

/**
 * dot-guardian/v1 health for the BluPin signal pipeline. Memory is the
 * right platform to answer this: it holds the loop records whose arrival
 * IS the pipeline's health. Read-only; Sanctum service-token authed like
 * the rest of the ops API.
 */
class GuardianBlupinController extends Controller
{
    public function show(BlupinLoopReport $report): JsonResponse
    {
        return response()->json($report->toArray());
    }
}
