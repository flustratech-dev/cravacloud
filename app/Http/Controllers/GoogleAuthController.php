<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(protected AuthController $authController)
    {
    }

    /**
     * Dynamically configure the redirect URI for local development or production.
     */
    protected function configureRedirectUri(): void
    {
        $host = request()->getHost();
        if (in_array($host, ['127.0.0.1', 'localhost']) || str_contains($host, 'ngrok')) {
            config(['services.google.redirect' => route('auth.google.callback')]);
        }
    }

    /**
     * Redirect user to Google OAuth provider.
     */
    public function redirect(Request $request)
    {
        if ($request->has('redirect')) {
            session(['url.intended' => $request->query('redirect')]);
        }
        $this->configureRedirectUri();
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(Request $request)
    {
        // Handle user cancellation or error returned by Google
        if ($request->has('error')) {
            return redirect()->route('login')->with('error', 'Login dengan Google dibatalkan atau terjadi kesalahan.');
        }

        $this->configureRedirectUri();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Gagal menghubungkan ke Google: ' . $e->getMessage());
        }

        $googleEmail = strtolower($googleUser->getEmail() ?? '');
        $googleId = (string) $googleUser->getId();

        if (empty($googleEmail)) {
            return redirect()->route('login')->with('error', 'Akun Google Anda tidak mengembalikan alamat email yang valid.');
        }

        // Check if user already exists by google_id or email
        $user = User::where('google_id', $googleId)
            ->orWhere('email', $googleEmail)
            ->first();

        // 1. Akun belum terdaftar -> arahkan ke halaman register sesuai instruksi
        if (!$user) {
            return redirect()->route('register')
                ->with('info', "Akun Google Anda ({$googleEmail}) belum terdaftar di CravaCloud. Silakan lengkapi formulir pendaftaran di bawah ini.")
                ->with('google_prefill', [
                    'full_name' => $googleUser->getName(),
                    'email' => $googleEmail,
                    'google_id' => $googleId,
                    'avatar' => $googleUser->getAvatar(),
                ]);
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
