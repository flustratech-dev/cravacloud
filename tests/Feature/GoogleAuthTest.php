<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_page_renders_google_button_and_terms(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('Lanjutkan dengan Google');
        $response->assertSee(route('auth.google'));
        $response->assertSee('Ketentuan Layanan');
        $response->assertSee('Kebijakan Privasi');
        $response->assertDontSee('Ingat sesi saya');
        $response->assertSee('showPassword');
        $response->assertSee('KELOLA INFRASTRUKTUR CLOUD DALAM SATU PORTAL');
        $response->assertSee('astronaut-hero.jpg');
        $response->assertDontSee('SECURITY AUTHENTICATION');
        $response->assertDontSee('Created by cravacloud.');
        $response->assertDontSee('MASUK AKUN');
        $response->assertDontSee('Akses client console, faktur, dan kontrol SSH root server Anda.');

        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        $this->assertSame(0, preg_match($emojiPattern, $response->getContent()), 'Login page contains emojis');
    }

    public function test_register_page_renders_google_button_strongmeter_and_terms(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
        $response->assertSee('Daftar Cepat dengan Google');
        $response->assertSee(route('auth.google'));
        $response->assertSee('Password *');
        $response->assertSee('Konfirmasi Password *');
        $response->assertSee('strength');
        $response->assertSee('Min. 8 karakter');
        $response->assertSee('Ketentuan Layanan');
        $response->assertSee('Kebijakan Privasi');
        $response->assertSee('DEPLOY SERVER NVME DALAM 60 DETIK');
        $response->assertSee('astronaut-hero.jpg');
        $response->assertDontSee('CREATE ACCOUNT');
        $response->assertDontSee('PENDAFTARAN');
        $response->assertDontSee('Created by cravacloud.');

        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        $this->assertSame(0, preg_match($emojiPattern, $response->getContent()), 'Register page contains emojis');
    }

    public function test_google_redirect_route_redirects(): void
    {
        $response = $this->get(route('auth.google'));

        // Socialite redirect should redirect to Google OAuth URL
        $response->assertStatus(302);
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_google_callback_for_unregistered_user_redirects_to_register_with_prefill(): void
    {
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('google-1092837465');
        $abstractUser->shouldReceive('getName')->andReturn('Budi Santoso');
        $abstractUser->shouldReceive('getEmail')->andReturn('budisantoso@gmail.com');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://google.com/avatar.jpg');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        // Harus diarahkan ke halaman register sesuai instruksi user
        $response->assertRedirect(route('register'));
        $response->assertSessionHas('info');
        $response->assertSessionHas('google_prefill', [
            'full_name' => 'Budi Santoso',
            'email' => 'budisantoso@gmail.com',
            'google_id' => 'google-1092837465',
            'avatar' => 'https://google.com/avatar.jpg',
        ]);

        // User belum dibuat di database
        $this->assertDatabaseMissing('users', [
            'email' => 'budisantoso@gmail.com',
        ]);
        $this->assertGuest();
    }

    public function test_google_callback_for_registered_user_logs_in_successfully(): void
    {
        $existingUser = User::create([
            'username' => 'existinguser',
            'email' => 'existing@gmail.com',
            'full_name' => 'Existing User',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);

        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('google-registered-999');
        $abstractUser->shouldReceive('getName')->andReturn('Existing User');
        $abstractUser->shouldReceive('getEmail')->andReturn('existing@gmail.com');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://google.com/avatar999.jpg');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        // Redirect ke dashboard
        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($existingUser);

        // google_id dan avatar terhubung ke akun user
        $existingUser->refresh();
        $this->assertEquals('google-registered-999', $existingUser->google_id);
        $this->assertEquals('https://google.com/avatar999.jpg', $existingUser->avatar);
        $this->assertNotNull($existingUser->email_verified_at);
    }

    public function test_registering_with_google_id_auto_verifies_email(): void
    {
        $response = $this->post(route('register'), [
            'full_name' => 'Google New User',
            'username' => 'googlenewuser',
            'email' => 'newgoogle@gmail.com',
            'password' => 'SecureP@ss123',
            'password_confirmation' => 'SecureP@ss123',
            'google_id' => 'gid-998877',
            'avatar' => 'https://google.com/photo.jpg',
        ]);

        $response->assertRedirect(route('dashboard.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'googlenewuser',
            'email' => 'newgoogle@gmail.com',
            'google_id' => 'gid-998877',
        ]);

        $user = User::where('email', 'newgoogle@gmail.com')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_renders_mandatory_terms_checkbox(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('id="agree_terms"', false);
        $response->assertSee('type="checkbox"', false);
        $response->assertSee('required', false);
        $response->assertSee('Ketentuan Layanan');
        $response->assertSee('Kebijakan Privasi');
    }

    public function test_login_fails_when_terms_not_accepted(): void
    {
        $response = $this->post(route('login'), [
            'username' => 'admin',
            'password' => '12345678',
            'terms' => '0',
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
    }

    public function test_login_succeeds_when_terms_accepted(): void
    {
        $response = $this->post(route('login'), [
            'username' => 'admin',
            'password' => '12345678',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('admin.index'));
        $this->assertAuthenticated();
    }

    public static function redirectOriginProvider(): array
    {
        return [
            'local localhost 8050' => ['http://localhost:8050', 'http://localhost:8050/auth/google/callback'],
            'local 127.0.0.1 8050' => ['http://127.0.0.1:8050', 'http://127.0.0.1:8050/auth/google/callback'],
            'http dev my.id' => ['http://dev.cravacloud.my.id', 'http://dev.cravacloud.my.id/auth/google/callback'],
            'https dev my.id' => ['https://dev.cravacloud.my.id', 'https://dev.cravacloud.my.id/auth/google/callback'],
            'https staging my.id' => ['https://staging.cravacloud.my.id', 'https://staging.cravacloud.my.id/auth/google/callback'],
            'https prod my.id' => ['https://cravacloud.my.id', 'https://cravacloud.my.id/auth/google/callback'],
            'https dev com' => ['https://dev.cravacloud.com', 'https://dev.cravacloud.com/auth/google/callback'],
            'https staging com' => ['https://staging.cravacloud.com', 'https://staging.cravacloud.com/auth/google/callback'],
            'https prod com' => ['https://cravacloud.com', 'https://cravacloud.com/auth/google/callback'],
            'https www com' => ['https://www.cravacloud.com', 'https://www.cravacloud.com/auth/google/callback'],
        ];
    }

    #[DataProvider('redirectOriginProvider')]
    public function test_google_redirect_adapts_callback_uri_to_active_origin(string $origin, string $expectedCallback): void
    {
        config([
            'app.url' => 'https://cravacloud.id',
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://cravacloud.id/auth/google/callback',
        ]);

        $response = $this->get($origin . '/auth/google');

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('accounts.google.com', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame($expectedCallback, $query['redirect_uri'] ?? null);
    }

    public function test_google_redirect_preserves_intended_checkout_url_and_blocks_external_redirect(): void
    {
        $intendedCheckout = 'https://cravacloud.my.id/checkout/3';

        $existingUser = User::create([
            'username' => 'checkoutgoogleuser',
            'email' => 'checkoutgoogle@gmail.com',
            'full_name' => 'Checkout Google User',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);

        $this->get('https://cravacloud.my.id/auth/google?intended=' . urlencode($intendedCheckout))
            ->assertStatus(302);

        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('google-checkout-123');
        $abstractUser->shouldReceive('getName')->andReturn('Checkout Google User');
        $abstractUser->shouldReceive('getEmail')->andReturn('checkoutgoogle@gmail.com');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/a/test');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $callbackResponse = $this->get('https://cravacloud.my.id/auth/google/callback');
        $callbackResponse->assertRedirect($intendedCheckout);
        $this->assertAuthenticatedAs($existingUser);
    }

    public function test_google_redirect_ignores_external_untrusted_intended_url(): void
    {
        $this->get('https://cravacloud.my.id/auth/google?intended=' . urlencode('https://evil.example/phish'))
            ->assertStatus(302);

        $this->assertNull(session('url.intended'));
    }

    public function test_google_callback_handles_invalid_state_with_clear_message(): void
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andThrow(new InvalidStateException());

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Sesi login Google sudah kadaluarsa atau tidak valid. Silakan coba masuk dengan Google lagi.');
        $this->assertGuest();
    }

    public function test_register_rejects_email_tampering_after_google_prefill(): void
    {
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('google-locked-email-1');
        $abstractUser->shouldReceive('getName')->andReturn('Locked Email User');
        $abstractUser->shouldReceive('getEmail')->andReturn('verified@gmail.com');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/a/locked');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))->assertRedirect(route('register'));

        $response = $this->post(route('register'), [
            'full_name' => 'Locked Email User',
            'username' => 'lockedemailuser',
            'email' => 'tampered@evil.com',
            'password' => 'SecureP@ss123',
            'password_confirmation' => 'SecureP@ss123',
            'google_id' => 'google-locked-email-1',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'tampered@evil.com']);
        $this->assertGuest();
    }
}
