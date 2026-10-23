<?php

namespace Tests\Feature\Knowledge;

use App\Models\LoopRecord;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Signs requests the way InfoDot and Brain sign them (the participation scheme, one secret per consumer) and
 * builds decision records the way Brain writes them.
 */
trait SignsKnowledgeRequests
{
    /** @var array<string, string> */
    protected array $secrets = [
        'infodot' => 'test-knowledge-infodot-secret-not-real',
        'brain' => 'test-knowledge-brain-secret-not-real',
    ];

    protected function useSecrets(): void
    {
        config(['knowledge.secrets' => $this->secrets]);
    }

    /**
     * @param  array<mixed>|null  $payload  null sends an empty body (GET)
     * @param  array<string, string>  $headers  merged over the signature headers
     */
    protected function signedAs(string $consumer, string $method, string $uri, ?array $payload = null, ?string $secret = null, ?int $timestamp = null, array $headers = []): TestResponse
    {
        $body = $payload === null ? '' : json_encode($payload);
        $timestamp ??= now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? $this->secrets[$consumer]);

        $server = $this->transformHeadersToServerVars(array_merge([
            'X-InfoDot-Timestamp' => (string) $timestamp,
            'X-InfoDot-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers));

        return $this->call($method, $uri, [], [], [], $server, $body);
    }

    /**
     * @param  array<string, mixed>  $overrides  LoopRecord attributes; `detail` is merged over the default Brain detail
     */
    protected function decision(string $ref = 'infodot:answer:1', array $overrides = []): LoopRecord
    {
        $detail = array_merge([
            'verdict' => 'recommend_for_library',
            'rule_version' => 'kc-rules-1',
            'rationale' => [
                ['rule' => 'accepted-answer', 'effect' => '+0.4', 'detail' => 'The asker accepted this answer.'],
                ['rule' => 'verified', 'effect' => '+0.3', 'detail' => 'Two moderators verified it.'],
            ],
        ], $overrides['detail'] ?? []);
        unset($overrides['detail']);

        return LoopRecord::query()->create(array_merge([
            'loop_id' => 'loop-'.Str::uuid(),
            'event_id' => 'evt-'.Str::uuid(),
            'stage' => 'decision',
            'platform' => 'dot-brain',
            'source' => 'knowledge-recommender',
            'subject_type' => 'infodot-contribution',
            'subject_id' => $ref,
            'signature' => 'knowledge-candidate',
            'confidence' => 0.82,
            'risk' => 0.1,
            'autonomy_level' => 'recommend',
            'detail' => $detail,
            'occurred_at' => now()->subHour(),
        ], $overrides));
    }

    protected function moderator(): string
    {
        return (string) Str::uuid();
    }
}
