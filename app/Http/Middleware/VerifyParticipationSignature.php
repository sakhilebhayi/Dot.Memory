<?php

namespace App\Http\Middleware;

use App\Services\Participation\ParticipationSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signature check for the server-to-server /api/participation endpoints (same scheme as Dot.Dopemine's
 * points endpoints). One secret per calling platform; a platform with no configured secret cannot
 * authenticate at all. Errors are `{code, message}`.
 */
class VerifyParticipationSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ((array) config('participation.secrets') as $secret) {
            if (ParticipationSignature::valid($request, (string) $secret)) {
                return $next($request);
            }
        }

        return response()->json(['code' => 'invalid_signature', 'message' => 'Invalid signature.'], 401);
    }
}
