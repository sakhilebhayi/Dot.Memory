<?php

namespace Tests\Feature\Participation;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Builds requests signed the way InfoDot signs them: `X-InfoDot-Signature: sha256=` +
 * HMAC-SHA256(secret, "{timestamp}.{raw body}"), the same scheme as Dot.Dopemine's points intake.
 */
trait SignsParticipationRequests
{
    protected string $secret = 'test-participation-secret-not-real';

    protected function useSecret(): void
    {
        config(['participation.secrets.infodot' => $this->secret]);
    }

    /**
     * @param  array<mixed>|null  $payload  null sends an empty body (GET / DELETE)
     * @param  array<string, string>  $headers  merged over the signature headers (e.g. to break them)
     */
    protected function signed(string $method, string $uri, ?array $payload = null, ?string $secret = null, ?int $timestamp = null, array $headers = []): TestResponse
    {
        $body = $payload === null ? '' : json_encode($payload);
        $timestamp ??= now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? $this->secret);

        $server = $this->transformHeadersToServerVars(array_merge([
            'X-InfoDot-Timestamp' => (string) $timestamp,
            'X-InfoDot-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers));

        return $this->call($method, $uri, [], [], [], $server, $body);
    }

    protected function uuid(): string
    {
        return (string) Str::uuid();
    }
}
