<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Security\LoginActivityRecorder;
use App\Services\Security\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $svc,
        protected LoginActivityRecorder $activityRecorder
    ) {
    }

    // ============================================================
    // Setup 2FA (dari halaman security settings)
    // ============================================================
    public function showEnable(Request $request)
    {
        $user = $request->user();
        $secret = session('2fa.setup_secret');
        if (!$secret) {
            $secret = $this->svc->generateSecret();
            session(['2fa.setup_secret' => $secret]);
        }
        $qrUri = $this->svc->qrProvisioningUri($secret, $user);

        return view('security.two-factor-enable', [
            'secret' => $secret,
            'qrUri' => $qrUri,
        ]);
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();
        $secret = session('2fa.setup_secret');
        if (!$secret) return back()->with('error', 'Sesi setup 2FA sudah kadaluarsa. Coba lagi.');

        if (!$this->svc->verifyCode($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Kode tidak valid. Cek jam device Anda.']);
        }

        $this->svc->enable($user, $secret);
        $this->svc->confirm($user, $request->input('code'));
        session()->forget('2fa.setup_secret');

        return redirect()->route('security.recovery-codes')
            ->with('success', '2FA berhasil diaktifkan. Simpan recovery codes di tempat aman.');
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (!Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Password salah.']);
        }
        $this->svc->disable($user);
        return back()->with('success', '2FA dinonaktifkan.');
    }

    public function showRecoveryCodes(Request $request)
    {
        $user = $request->user();
        if (!$user->hasTwoFactorEnabled()) return redirect()->route('security.settings');
        $codes = $this->svc->getRecoveryCodes($user);
        return view('security.two-factor-recovery-codes', ['codes' => $codes]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();
        $codes = $this->svc->generateRecoveryCodes();
        // Update encrypted codes.
        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        ])->save();
        return back()->with('success', 'Recovery codes baru sudah dibuat.');
    }

    // ============================================================
    // Challenge saat login (setelah primary auth)
    // ============================================================
    public function showChallenge(Request $request)
    {
        $pendingId = session('2fa.pending_user_id') ?? Auth::id();
        if (!$pendingId) return redirect()->route('login');
        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request)
    {
        $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $throttleKey = '2fa|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->with('error', "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.");
        }

        $pendingId = session('2fa.pending_user_id') ?? Auth::id();
        $user = User::find($pendingId);
        if (!$user) return redirect()->route('login');

        $code = trim((string) $request->input('code', ''));
        $recovery = trim((string) $request->input('recovery_code', ''));

        $passed = false;
        if ($code !== '' && $this->svc->verifyForUser($user, $code)) {
            $passed = true;
        } elseif ($recovery !== '' && $this->svc->consumeRecoveryCode($user, $recovery)) {
            $passed = true;
        }

        if (!$passed) {
            RateLimiter::hit($throttleKey, 60);
            $this->activityRecorder->recordFailure($user->email, $request, 'invalid_2fa', $user->id, 'two_factor_failed');
            return back()->withErrors(['code' => 'Kode tidak valid.']);
        }

        RateLimiter::clear($throttleKey);

        // Kalau ini flow login (bukan re-auth), complete login.
        if (session('2fa.pending_user_id')) {
            $remember = (bool) session('2fa.remember', false);
            session()->forget(['2fa.pending_user_id', '2fa.remember']);
            app(AuthController::class)->completeLogin($user, $request, $remember);
        } else {
            session(['2fa.passed' => true]);
        }

        return $user->is_admin
            ? redirect()->intended(route('admin.index'))
            : redirect()->intended(route('dashboard.index'));
    }
}
