<?php

namespace App\Services;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * PHASE 7 - Masuk sebagai pelanggan untuk keperluan dukungan.
 *
 * Aturan pengaman:
 *   - Hanya admin yang boleh memulai, dan target wajib akun pelanggan (bukan admin)
 *   - Tidak boleh bertingkat: impersonation di dalam impersonation ditolak
 *   - Sesi berakhir otomatis setelah MAX_MINUTES
 *   - Selama impersonation, permintaan yang mengubah data ditolak oleh
 *     middleware ImpersonationGuard, kecuali yang ada di daftar putih
 *   - Status 2FA admin disimpan lalu dipulihkan, agar tantangan 2FA milik
 *     pelanggan tidak menghalangi admin dan tidak bocor ke sesi admin
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonation';

    public const MAX_MINUTES = 60;

    /**
     * Rute yang tetap boleh diubah selama impersonation.
     */
    public const ALLOWED_WRITE_ROUTES = [
        'impersonate.leave',
        'logout',
        'organizations.switch',
    ];

    public function isActive(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has(self::SESSION_KEY);
    }

    public function data(Request $request): ?array
    {
        return $this->isActive($request) ? $request->session()->get(self::SESSION_KEY) : null;
    }

    public function isExpired(Request $request): bool
    {
        $data = $this->data($request);

        if (!$data) {
            return false;
        }

        return now()->timestamp - (int) ($data['started_at'] ?? 0) > self::MAX_MINUTES * 60;
    }

    /**
     * Mulai impersonation. Mengembalikan pesan kesalahan, atau null bila berhasil.
     */
    public function start(Request $request, User $admin, User $target): ?string
    {
        if (!$admin->is_admin) {
            return 'Hanya admin yang dapat masuk sebagai pelanggan.';
        }

        if ($target->is_admin) {
            return 'Tidak dapat masuk sebagai akun admin.';
        }

        if ($target->id === $admin->id) {
            return 'Tidak dapat masuk sebagai diri sendiri.';
        }

        if ($this->isActive($request)) {
            return 'Anda sedang dalam sesi impersonation. Kembali ke admin terlebih dahulu.';
        }

        $log = ImpersonationLog::create([
            'admin_user_id' => $admin->id,
            'impersonated_user_id' => $target->id,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);

        $admin2faPassed = (bool) $request->session()->get('2fa.passed', false);

        Auth::guard('web')->login($target);
        $request->session()->regenerate();

        $request->session()->put(self::SESSION_KEY, [
            'admin_id' => $admin->id,
            'log_id' => $log->id,
            'started_at' => now()->timestamp,
            'admin_2fa_passed' => $admin2faPassed,
            'target_name' => $target->full_name ?: $target->email,
        ]);

        // Tantangan 2FA milik pelanggan tidak berlaku untuk admin yang mewakilinya.
        $request->session()->put('2fa.passed', true);

        return null;
    }

    /**
     * Akhiri impersonation dan kembalikan sesi ke admin.
     * Mengembalikan admin, atau null bila admin sudah tidak valid (sesi diakhiri penuh).
     */
    public function stop(Request $request, string $reason = 'left'): ?User
    {
        $data = $this->data($request);

        if (!$data) {
            return null;
        }

        $this->closeLog((int) ($data['log_id'] ?? 0), $reason);

        $admin = User::find($data['admin_id'] ?? 0);

        if (!$admin || !$admin->is_admin) {
            // Admin sudah dihapus atau dicabut haknya: jangan kembalikan sesi admin.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return null;
        }

        Auth::guard('web')->login($admin);
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put('2fa.passed', (bool) ($data['admin_2fa_passed'] ?? false));
        $request->session()->regenerate();

        return $admin;
    }

    /**
     * Tutup catatan sesi tanpa mengubah autentikasi (dipakai saat logout).
     */
    public function closeLog(int $logId, string $reason): void
    {
        if ($logId <= 0) {
            return;
        }

        ImpersonationLog::whereKey($logId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now(), 'end_reason' => $reason]);
    }

    public function recordBlockedAction(Request $request): void
    {
        $logId = (int) ($this->data($request)['log_id'] ?? 0);

        if ($logId > 0) {
            ImpersonationLog::whereKey($logId)->increment('blocked_actions');
        }
    }
}
