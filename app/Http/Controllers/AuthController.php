<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\NewLoginAlertNotification;
use App\Services\Security\LoginActivityRecorder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Rules\Turnstile;

class AuthController extends Controller
{
    public function __construct(protected LoginActivityRecorder $activityRecorder)
    {
    }

    public function showLogin(Request $request)
    {
        if ($request->has('redirect')) {
            session(['url.intended' => $request->query('redirect')]);
        } elseif ($request->has('redirect_to')) {
            session(['url.intended' => $request->query('redirect_to')]);
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $rules = [
            'username' => 'required|string',
            'password' => 'required|string',
            'terms' => 'sometimes|accepted',
        ];

        if (config('services.turnstile.secret') && (!app()->runningUnitTests() || config('services.turnstile.testing_active', false))) {
            $rules['cf-turnstile-response'] = ['required', new Turnstile()];
        }

        $validated = $request->validate($rules, [
            'terms.accepted' => 'Anda wajib menyetujui Ketentuan Layanan dan Kebijakan Privasi untuk melanjutkan.',
            'cf-turnstile-response.required' => 'Verifikasi keamanan Cloudflare Turnstile wajib dicentang.',
        ]);

        $throttleKey = Str::transliterate(Str::lower($validated['username']) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->activityRecorder->recordFailure($validated['username'], $request, "throttle:{$seconds}s", null, 'blocked');
            return back()->withInput($request->only('username', 'remember'))->withErrors([
                'username' => "Terlalu banyak percobaan login gagal. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        $loginInput = trim((string) $validated['username']);
        $normalizedLogin = strtolower(preg_replace('/@([a-zA-Z0-9.-]+),([a-zA-Z]{2,})/i', '@$1.$2', $loginInput));
        $user = User::whereRaw('LOWER(email) = ?', [$normalizedLogin])
            ->orWhereRaw('LOWER(username) = ?', [strtolower($loginInput)])
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            $this->activityRecorder->recordFailure($validated['username'], $request, 'invalid_credentials', $user?->id);
            return back()->withInput($request->only('username', 'remember'))->withErrors([
                'username' => 'Username/email atau password yang Anda masukkan tidak sesuai.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Kalau user punya 2FA aktif, jangan login penuh — simpan pending user id
        // di session lalu redirect ke challenge.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa.pending_user_id', $user->id);
            $request->session()->put('2fa.remember', $request->boolean('remember'));
            $this->activityRecorder->recordFailure($user->email, $request, 'awaiting_2fa', $user->id, 'two_factor_required');
            return redirect()->route('two-factor.challenge');
        }

        $this->completeLogin($user, $request, $request->boolean('remember'));

        if ($user->is_admin) {
            return redirect()->intended(route('admin.index'));
        }
        return redirect()->intended(route('dashboard.index'));
    }

    /**
     * Login flow selesai: catat activity, alert kalau device baru, update meta.
     */
    public function completeLogin(User $user, Request $request, bool $remember = false): void
    {
        $isNewDevice = $this->activityRecorder->isNewLoginFor($user, $request);

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('2fa.passed', true);

        $activity = $this->activityRecorder->recordSuccess($user, $request, $request->session()->getId());

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        if ($isNewDevice) {
            try {
                $user->notify(new NewLoginAlertNotification($activity));
            } catch (\Throwable $e) {
                // ignore notif error
            }
        }
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $throttleKey = 'register|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withInput()->withErrors([
                'email' => "Terlalu banyak pendaftaran dari perangkat ini. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'username' => 'required|string|min:3|max:50|alpha_dash|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:25',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'google_id' => 'nullable|string|max:255|unique:users,google_id',
            'avatar' => 'nullable|string|max:1000',
            'terms' => 'sometimes|accepted',
        ], [
            'terms.accepted' => 'Anda wajib menyetujui Ketentuan Layanan dan Kebijakan Privasi untuk melanjutkan.',
        ]);

        RateLimiter::hit($throttleKey, 60);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'username' => strtolower($validated['username']),
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'channel' => 'website',
            'google_id' => $validated['google_id'] ?? null,
            'avatar' => $validated['avatar'] ?? null,
        ]);

        $user->forceFill(['password_changed_at' => now()])->save();

        if (!empty($validated['google_id'])) {
            $user->markEmailAsVerified();
        } else {
            // Kirim link verifikasi email.
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                // Jangan gagalkan register kalau mail gagal; user bisa resend nanti.
            }
        }

        $this->completeLogin($user, $request);

        return redirect()->intended(route('dashboard.index'))
            ->with('success', 'Selamat datang di CravaCloud! Kami sudah mengirim link verifikasi ke email Anda.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Anda telah berhasil logout.');
    }

    // ============================================================
    // Email verification (built-in Laravel signed URL flow)
    // ============================================================
    public function showVerificationNotice()
    {
        return view('auth.verify-email');
    }

    public function verifyEmail(Request $request)
    {
        $user = User::findOrFail($request->route('id'));
        if (!hash_equals(sha1($user->email), (string) $request->route('hash'))) {
            abort(403);
        }
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
        return redirect()->route('dashboard.index')->with('success', 'Email berhasil diverifikasi.');
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        if ($user->hasVerifiedEmail()) return back()->with('info', 'Email Anda sudah terverifikasi.');
        $user->sendEmailVerificationNotification();
        return back()->with('success', 'Link verifikasi baru sudah dikirim ke email Anda.');
    }

    // ============================================================
    // Password reset
    // ============================================================
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $throttleKey = 'pwreset|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return back()->with('error', 'Terlalu banyak permintaan reset. Coba lagi nanti.');
        }
        RateLimiter::hit($throttleKey, 300);

        $status = Password::sendResetLink($request->only('email'));

        // Selalu tampilkan pesan generic supaya tidak leak email exists.
        return back()->with('success', 'Jika email terdaftar, link reset password sudah dikirim.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'password_changed_at' => now(),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password berhasil direset. Silakan login.')
            : back()->withErrors(['email' => __($status)]);
    }
}
