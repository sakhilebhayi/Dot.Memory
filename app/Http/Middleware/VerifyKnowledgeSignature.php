<?php

namespace App\Http\Middleware;

use App\Services\Participation\ParticipationSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signature check for the server-to-server /api/knowledge-recommendations endpoints. Same scheme as the
 * participation API (X-InfoDot-Timestamp + X-InfoDot-Signature: sha256=HMAC-SHA256(secret, "{ts}.{raw body}"),
 * 300 s window) but ONE SECRET PER CONSUMER, and the middleware records which consumer signed so that
 * permissions can differ:
 *
 *   use as  VerifyKnowledgeSignature::class.':infodot'  (or ':brain', or ':infodot,brain')
 *
 *   no valid signature for any consumer      -> 401 invalid_signature
 *   valid, but the consumer is not allowed   -> 403 forbidden_consumer
 *
 * A consumer with no configured secret can never authenticate (fails closed). Two consumers configured
 * with the same secret are indistinguishable, so such a signature is refused as invalid rather than
 * guessed at.
 */
class VerifyKnowledgeSignature
{
    public const ATTRIBUTE = 'knowledge_consumer';

    public function handle(Request $request, Closure $next, string ...$allowed): Response
    {
        $tolerance = (int) config('knowledge.tolerance', 300);
        $matched = [];

        foreach ((array) config('knowledge.secrets') as $consumer => $secret) {
            if (ParticipationSignature::valid($request, (string) $secret, $tolerance)) {
                $matched[] = (string) $consumer;
            }
        }

        if (count($matched) !== 1) {
            if (count($matched) > 1) {
                Log::warning('Knowledge API: the consumer secrets are identical; refusing the request.');
            }

            return response()->json(['code' => 'invalid_signature', 'message' => 'Invalid signature.'], 401);
        }

        if (! in_array($matched[0], $allowed, true)) {
            return response()->json(['code' => 'forbidden_consumer', 'message' => 'This consumer may not call this endpoint.'], 403);
        }

        $request->attributes->set(self::ATTRIBUTE, $matched[0]);

        return $next($request);
    }
}
