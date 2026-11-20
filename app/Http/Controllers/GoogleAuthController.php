<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function __construct(protected AuthController $authController)
    {
    }

    /**
     * Dynamically configure the OAuth callback URI to match the active host & scheme
     * across local, tunnel, testing (cravacloud.my.id), and production (cravacloud.com/.id).
     */
    protected function configureRedirectUri(Request $request): string
    {
        $host = strtolower($request->getHost());
        $configuredRedirect = (string) config('services.google.redirect');

        if ($configuredRedirect === '' || $this->isDynamicRedirectHost($host)) {
            $redirectUri = route('auth.google.callback');
            config(['services.google.redirect' => $redirectUri]);

            return $redirectUri;
        }

        return $configuredRedirect;
    }

    protected function isDynamicRedirectHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return true;
        }

        if (
            str_contains($host, 'ngrok')
            || str_ends_with($host, '.trycloudflare.com')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local')
        ) {
            return true;
        }

        if ($this->isAllowedCravaCloudHost($host)) {
            return true;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $appHost !== '' && $host === $appHost;
    }

    protected function isAllowedCravaCloudHost(string $host): bool
    {
        foreach (['cravacloud.com', 'cravacloud.my.id', 'cravacloud.id'] as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    protected function isSafeIntendedUrl(string $url, Request $request): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_starts_with($url, '/\\')) {
            return true;
        }

        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        return $host === strtolower($request->getHost()) || $this->isDynamicRedirectHost($host);
    }

    protected function hasOAuthCredentials(): bool
    {
        return !empty(config('services.google.client_id'))
            && !empty(config('services.google.client_secret'));
    }

    /**
     * Redirect user to Google OAuth provider.
     */
    public function redirect(Request $request)
    {
        if (!app()->runningUnitTests() && !$this->hasOAuthCredentials()) {
            Log::warning('Google OAuth redirect aborted: missing GOOGLE_CLIENT_ID or GOOGLE_CLIENT_SECRET.', [
                'host' => $request->getHost(),
            ]);

            return redirect()->route('login')
                ->with('error', 'Login dengan Google belum dikonfigurasi pada environment ini.');
        }

        $intended = $request->query('redirect')
            ?? $request->query('intended')
            ?? $request->query('redirect_to');

        if (is_string($intended) && $this->isSafeIntendedUrl($intended, $request)) {
            session(['url.intended' => $intended]);
        }

        $this->configureRedirectUri($request);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(Request $request)
    {
        // Handle user cancellation or error returned by Google
        if ($request->has('error')) {
            Log::info('Google OAuth callback returned error from provider.', [
                'error' => $request->query('error'),
                'error_description' => $request->query('error_description'),
                'host' => $request->getHost(),
            ]);

            return redirect()->route('login')
                ->with('error', 'Login dengan Google dibatalkan atau terjadi kesalahan.');
        }

        if (!app()->runningUnitTests() && !$this->hasOAuthCredentials()) {
            Log::warning('Google OAuth callback aborted: missing GOOGLE_CLIENT_ID or GOOGLE_CLIENT_SECRET.', [
                'host' => $request->getHost(),
            ]);

            return redirect()->route('login')
                ->with('error', 'Login dengan Google belum dikonfigurasi pada environment ini.');
        }

        $redirectUri = $this->configureRedirectUri($request);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            Log::warning('Google OAuth state validation failed.', [
                'host' => $request->getHost(),
                'scheme' => $request->getScheme(),
                'redirect_uri' => $redirectUri,
                'has_state' => $request->filled('state'),
                'has_code' => $request->filled('code'),
                'has_session_cookie' => $request->cookies->has(config('session.cookie')),
            ]);

            return redirect()->route('login')
                ->with('error', 'Sesi login Google sudah kadaluarsa atau tidak valid. Silakan coba masuk dengan Google lagi.');
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed: ' . $e->getMessage(), [
                'exception' => $e::class,
                'host' => $request->getHost(),
                'redirect_uri' => $redirectUri,
            ]);

            return redirect()->route('login')
                ->with('error', 'Gagal menghubungkan ke akun Google. Silakan coba lagi atau masuk dengan email dan password.');
        }

        $googleEmail = strtolower(trim((string) ($googleUser->getEmail() ?? '')));
        $googleId = trim((string) $googleUser->getId());

        if ($googleEmail === '' || $googleId === '') {
            return redirect()->route('login')
                ->with('error', 'Akun Google Anda tidak mengembalikan alamat email yang valid.');
        }

        $rawGoogleUser = property_exists($googleUser, 'user') && is_array($googleUser->user)
            ? $googleUser->user
            : null;
        if ($rawGoogleUser !== null && ($rawGoogleUser['email_verified'] ?? true) === false) {
            return redirect()->route('login')
                ->with('error', 'Email pada akun Google Anda belum terverifikasi oleh Google.');
        }

        // Check if user already exists by google_id first, then fallback to email
        $user = User::where('google_id', $googleId)->first();

        if (!$user) {
            $user = User::whereRaw('LOWER(email) = ?', [$googleEmail])->first();

            if ($user && $user->google_id && $user->google_id !== $googleId) {
                Log::warning('Google OAuth email matched user linked to different google_id.', [
                    'user_id' => $user->id,
                    'email' => $googleEmail,
                ]);

                return redirect()->route('login')
                    ->with('error', 'Email ini sudah tertaut ke akun Google lain. Silakan masuk menggunakan akun Google sebelumnya atau password Anda.');
            }
        }

        // 1. Akun belum terdaftar -> arahkan ke halaman register dengan prefill terverifikasi
        if (!$user) {
            $prefill = [
                'full_name' => $googleUser->getName(),
                'email' => $googleEmail,
                'google_id' => $googleId,
                'avatar' => $googleUser->getAvatar(),
            ];

            $request->session()->put('google_oauth_registration', [
                'google_id' => $googleId,
                'email' => $googleEmail,
                'avatar' => $googleUser->getAvatar(),
            ]);

            return redirect()->route('register')
                ->with('info', "Akun Google Anda ({$googleEmail}) belum terdaftar di CravaCloud. Silakan lengkapi formulir pendaftaran di bawah ini.")
                ->with('google_prefill', $prefill);
        }

        // 2. Akun terdaftar -> link google_id & avatar bila belum ada
        $updates = [];
        if (!$user->google_id) {
            $updates['google_id'] = $googleId;
        }
        if (!$user->avatar && $googleUser->getAvatar()) {
            $updates['avatar'] = $googleUser->getAvatar();
        }
        if (!empty($updates)) {
            $user->update($updates);
        }

        // Otomatis verifikasi email jika belum diverifikasi karena Google sudah memvalidasinya
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Cek Two Factor Authentication jika user mengaktifkannya
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa.pending_user_id', $user->id);
            $request->session()->put('2fa.remember', true);
            return redirect()->route('two-factor.challenge');
        }

        // Selesaikan sesi login user
        $this->authController->completeLogin($user, $request, true);

        if ($user->is_admin) {
            return redirect()->intended(route('admin.index'))
                ->with('success', "Selamat datang kembali, {$user->full_name}!");
        }

        return redirect()->intended(route('dashboard.index'))
            ->with('success', "Selamat datang kembali, {$user->full_name}!");
    }
}
