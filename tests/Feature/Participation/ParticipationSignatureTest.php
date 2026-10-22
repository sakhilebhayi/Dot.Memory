<?php

namespace Tests\Feature\Participation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every participation endpoint is HMAC-signed (the Dot.Dopemine scheme) and fails closed.
 */
class ParticipationSignatureTest extends TestCase
{
    use RefreshDatabase;
    use SignsParticipationRequests;

    /**
     * @return array<string, array{0: string, 1: string, 2: array<mixed>|null}>
     */
    public static function endpoints(): array
    {
        $uuid = '4b8f1c52-7d0e-4a39-9f6b-2c1e8a7d5b30';

        return [
            'events' => ['POST', '/api/participation/events', ['event_id' => 'e1']],
            'consent' => ['PUT', "/api/participation/{$uuid}/consent", ['granted' => true]],
            'context' => ['GET', "/api/participation/{$uuid}/context", null],
            'erase' => ['DELETE', "/api/participation/{$uuid}", null],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_missing_signature_is_401(string $method, string $uri, ?array $payload): void
    {
        $this->useSecret();

        $this->call($method, $uri, [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], $payload === null ? '' : json_encode($payload))
            ->assertStatus(401)
            ->assertExactJson(['code' => 'invalid_signature', 'message' => 'Invalid signature.']);
    }

    #[DataProvider('endpoints')]
    public function test_wrong_secret_is_401(string $method, string $uri, ?array $payload): void
    {
        $this->useSecret();

        $this->signed($method, $uri, $payload, secret: 'some-other-secret')->assertStatus(401)->assertJsonPath('code', 'invalid_signature');
    }

    #[DataProvider('endpoints')]
    public function test_stale_and_future_timestamps_are_401(string $method, string $uri, ?array $payload): void
    {
        $this->useSecret();

        $this->signed($method, $uri, $payload, timestamp: now()->subSeconds(301)->timestamp)->assertStatus(401);
        $this->signed($method, $uri, $payload, timestamp: now()->addSeconds(301)->timestamp)->assertStatus(401);
    }

    #[DataProvider('endpoints')]
    public function test_a_tampered_body_is_401(string $method, string $uri, ?array $payload): void
    {
        $this->useSecret();

        $timestamp = now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.json_encode(['tampered' => true]), $this->secret);

        $this->signed($method, $uri, $payload, headers: ['X-InfoDot-Signature' => $signature, 'X-InfoDot-Timestamp' => (string) $timestamp])
            ->assertStatus(401);
    }

    #[DataProvider('endpoints')]
    public function test_an_unset_secret_fails_closed_even_for_a_signature_made_with_an_empty_key(string $method, string $uri, ?array $payload): void
    {
        config(['participation.secrets.infodot' => null]);
        $this->signed($method, $uri, $payload, secret: '')->assertStatus(401);
        $this->signed($method, $uri, $payload)->assertStatus(401);

        config(['participation.secrets.infodot' => '']);
        $this->signed($method, $uri, $payload, secret: '')->assertStatus(401);
    }

    public function test_a_correct_signature_without_the_sha256_prefix_is_accepted_like_dopemine(): void
    {
        $this->useSecret();
        $uuid = $this->uuid();
        $timestamp = now()->timestamp;

        $this->signed('GET', "/api/participation/{$uuid}/context", headers: [
            'X-InfoDot-Signature' => hash_hmac('sha256', $timestamp.'.', $this->secret),
            'X-InfoDot-Timestamp' => (string) $timestamp,
        ])->assertStatus(403)->assertJsonPath('code', 'consent_required');
    }

    public function test_the_sanctum_intelligence_api_does_not_accept_a_participation_signature(): void
    {
        $this->useSecret();

        $this->signed('POST', '/api/intelligence/events', ['loop_id' => 'x'])->assertUnauthorized();
    }
}
