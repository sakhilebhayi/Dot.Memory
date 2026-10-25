<?php

namespace Tests\Feature\Knowledge;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Signature required everywhere, fail-closed, and the consumer permission matrix.
 */
class KnowledgeSignatureTest extends TestCase
{
    use RefreshDatabase;
    use SignsKnowledgeRequests;

    /**
     * @return array<string, array{0: string, 1: string, 2: array<mixed>|null, 3: string}>
     */
    public static function endpoints(): array
    {
        return [
            'list' => ['GET', '/api/knowledge-recommendations', null, 'infodot'],
            'outcome' => ['POST', '/api/knowledge-recommendations/evt-1/outcome', ['outcome' => 'adopted', 'actor_ref' => '4b8f1c52-7d0e-4a39-9f6b-2c1e8a7d5b30'], 'infodot'],
            'feed' => ['GET', '/api/knowledge-recommendations/outcomes', null, 'brain'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_an_unsigned_request_is_401(string $method, string $uri, ?array $payload, string $consumer): void
    {
        $this->useSecrets();

        $this->call($method, $uri, [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], $payload === null ? '' : json_encode($payload))
            ->assertStatus(401)
            ->assertExactJson(['code' => 'invalid_signature', 'message' => 'Invalid signature.']);
    }

    #[DataProvider('endpoints')]
    public function test_a_wrong_secret_stale_and_future_timestamps_and_a_tampered_body_are_401(string $method, string $uri, ?array $payload, string $consumer): void
    {
        $this->useSecrets();

        $this->signedAs($consumer, $method, $uri, $payload, secret: 'some-other-secret')->assertStatus(401)->assertJsonPath('code', 'invalid_signature');
        $this->signedAs($consumer, $method, $uri, $payload, timestamp: now()->subSeconds(301)->timestamp)->assertStatus(401);
        $this->signedAs($consumer, $method, $uri, $payload, timestamp: now()->addSeconds(301)->timestamp)->assertStatus(401);

        $timestamp = now()->timestamp;
        $this->signedAs($consumer, $method, $uri, $payload, headers: [
            'X-InfoDot-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.json_encode(['tampered' => true]), $this->secrets[$consumer]),
            'X-InfoDot-Timestamp' => (string) $timestamp,
        ])->assertStatus(401);
    }

    #[DataProvider('endpoints')]
    public function test_an_unset_or_empty_secret_fails_closed_even_for_a_signature_made_with_an_empty_key(string $method, string $uri, ?array $payload, string $consumer): void
    {
        foreach ([null, ''] as $unset) {
            config(['knowledge.secrets' => ['infodot' => $unset, 'brain' => $unset]]);

            $this->signedAs($consumer, $method, $uri, $payload, secret: '')->assertStatus(401);
            $this->signedAs($consumer, $method, $uri, $payload)->assertStatus(401);
        }
    }

    #[DataProvider('endpoints')]
    public function test_one_unset_secret_blocks_only_its_own_consumer(string $method, string $uri, ?array $payload, string $consumer): void
    {
        $other = $consumer === 'infodot' ? 'brain' : 'infodot';
        config(['knowledge.secrets' => [$consumer => $this->secrets[$consumer], $other => null]]);

        $this->signedAs($consumer, $method, $uri, $payload)->assertStatus($consumer === 'infodot' && $method === 'POST' ? 404 : 200);
        $this->signedAs($other, $method, $uri, $payload, secret: '')->assertStatus(401);
    }

    #[DataProvider('endpoints')]
    public function test_the_permission_matrix(string $method, string $uri, ?array $payload, string $allowed): void
    {
        $this->useSecrets();
        $denied = $allowed === 'infodot' ? 'brain' : 'infodot';

        // The allowed consumer gets through (404 only because the decision in the URL does not exist).
        $this->assertContains($this->signedAs($allowed, $method, $uri, $payload)->getStatusCode(), [200, 404]);

        // The other consumer has a VALID signature but no permission.
        $this->signedAs($denied, $method, $uri, $payload)
            ->assertStatus(403)
            ->assertExactJson(['code' => 'forbidden_consumer', 'message' => 'This consumer may not call this endpoint.']);
    }

    #[DataProvider('endpoints')]
    public function test_a_participation_signature_is_not_accepted(string $method, string $uri, ?array $payload, string $consumer): void
    {
        $this->useSecrets();
        config(['participation.secrets.infodot' => 'test-participation-secret-not-real']);

        $this->signedAs($consumer, $method, $uri, $payload, secret: 'test-participation-secret-not-real')->assertStatus(401);
    }

    public function test_the_knowledge_secrets_do_not_open_the_participation_or_intelligence_apis(): void
    {
        $this->useSecrets();
        config(['participation.secrets.infodot' => 'test-participation-secret-not-real']);

        $this->signedAs('infodot', 'GET', '/api/participation/4b8f1c52-7d0e-4a39-9f6b-2c1e8a7d5b30/context')->assertStatus(401);
        $this->signedAs('brain', 'POST', '/api/intelligence/decisions', ['loop_id' => 'x'])->assertUnauthorized();
    }

    public function test_identical_consumer_secrets_are_refused_rather_than_guessed(): void
    {
        config(['knowledge.secrets' => ['infodot' => 'same-secret-for-both', 'brain' => 'same-secret-for-both']]);

        $this->signedAs('infodot', 'GET', '/api/knowledge-recommendations', secret: 'same-secret-for-both')->assertStatus(401);
        $this->signedAs('brain', 'GET', '/api/knowledge-recommendations/outcomes', secret: 'same-secret-for-both')->assertStatus(401);
    }

    public function test_a_signature_without_the_sha256_prefix_is_accepted_like_the_participation_scheme(): void
    {
        $this->useSecrets();
        $timestamp = now()->timestamp;

        $this->signedAs('infodot', 'GET', '/api/knowledge-recommendations', headers: [
            'X-InfoDot-Signature' => hash_hmac('sha256', $timestamp.'.', $this->secrets['infodot']),
            'X-InfoDot-Timestamp' => (string) $timestamp,
        ])->assertOk();
    }

    public function test_requests_are_rate_limited(): void
    {
        $this->useSecrets();
        config(['knowledge.rate_limit_per_minute' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->signedAs('infodot', 'GET', '/api/knowledge-recommendations')->assertOk();
        }

        $this->signedAs('infodot', 'GET', '/api/knowledge-recommendations')->assertStatus(429);
        // Unsigned floods are throttled too (the limiter runs before the signature check).
        $this->getJson('/api/knowledge-recommendations')->assertStatus(429);
    }
}
