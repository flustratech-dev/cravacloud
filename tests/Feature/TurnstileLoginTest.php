<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_turnstile_widget_when_sitekey_configured(): void
    {
        config(['services.turnstile.key' => '0x4AAAAAAE5Ryx_iTQzttsDQ']);

        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false);
        $response->assertSee('cf-turnstile', false);
        $response->assertSee('data-sitekey="0x4AAAAAAE5Ryx_iTQzttsDQ"', false);
    }

    public function test_login_fails_when_turnstile_response_missing_and_active(): void
    {
        User::create([
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => Hash::make('password123'),
        ]);

        config([
            'services.turnstile.secret' => '0x4AAAAAAE5Ry-YjnJW8ak61CLqjwkuW5Rc',
            'services.turnstile.testing_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'username' => 'testuser',
            'password' => 'password123',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    public function test_login_fails_when_cloudflare_rejects_token(): void
    {
        User::create([
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => Hash::make('password123'),
        ]);

        config([
            'services.turnstile.secret' => '0x4AAAAAAE5Ry-YjnJW8ak61CLqjwkuW5Rc',
            'services.turnstile.testing_active' => true,
        ]);

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        ]);

        $response = $this->post(route('login'), [
            'username' => 'testuser',
            'password' => 'password123',
            'terms' => '1',
            'cf-turnstile-response' => 'invalid_dummy_token',
        ]);

        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    public function test_login_succeeds_when_cloudflare_verifies_token(): void
    {
        $user = User::create([
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => Hash::make('password123'),
        ]);

        config([
            'services.turnstile.secret' => '0x4AAAAAAE5Ry-YjnJW8ak61CLqjwkuW5Rc',
            'services.turnstile.testing_active' => true,
        ]);

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'challenge_ts' => now()->toISOString(),
                'hostname' => 'cravacloud.id',
            ], 200),
        ]);

        $response = $this->post(route('login'), [
            'username' => 'testuser',
            'password' => 'password123',
            'terms' => '1',
            'cf-turnstile-response' => 'valid_turnstile_token_123',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertAuthenticatedAs($user);
    }
}
