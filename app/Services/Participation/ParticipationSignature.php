<?php

namespace App\Services\Participation;

use Illuminate\Http\Request;

/**
 * Verifies `X-InfoDot-Signature` = `sha256=` + HMAC-SHA256(secret, "{X-InfoDot-Timestamp}.{raw body}"),
 * inside a 300 s window. This is the exact scheme Dot.Dopemine uses for its points intake, so InfoDot signs
 * every server-to-server call the same way. A GET has no body and signs the empty string.
 */
class ParticipationSignature
{
    public static function valid(Request $request, string $secret): bool
    {
        $timestamp = (int) $request->header('X-InfoDot-Timestamp');
        $signature = (string) $request->header('X-InfoDot-Signature');

        // Always do the HMAC, even for an unconfigured platform, so a missing secret costs the same as a wrong signature.
        $key = $secret !== '' ? $secret : hash('sha256', 'memory-participation-unconfigured-platform');
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $key);
        $fresh = $timestamp !== 0 && abs(now()->timestamp - $timestamp) <= (int) config('participation.tolerance', 300);
        $given = str_starts_with($signature, 'sha256=') ? $signature : 'sha256='.$signature;

        return hash_equals($expected, $given) && $fresh && $secret !== '';
    }
}
