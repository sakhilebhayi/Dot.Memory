<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\ApiTokenManager;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Integrating platforms (Dot.Central, the guardian) authenticate with
 * Sanctum bearer tokens. A person can mint one from their profile; a
 * service account gets one through the provision-service-token workflow.
 */
class ApiTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_user_can_open_the_api_tokens_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get('/user/api-tokens')->assertOk()->assertSee('API Tokens');
    }

    public function test_a_user_can_create_a_token_that_authenticates_against_the_api(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($user)
            ->test(ApiTokenManager::class)
            ->set(['createApiTokenForm' => ['name' => 'dot-central', 'permissions' => ['read']]])
            ->call('createApiToken');

        $token = $user->fresh()->tokens()->where('name', 'dot-central')->first();
        $this->assertNotNull($token);

        $plain = $user->createToken('probe')->plainTextToken;
        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$plain])->assertOk()->assertJsonPath('email', $user->email);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/user/api-tokens')->assertRedirect('/login');
    }
}
