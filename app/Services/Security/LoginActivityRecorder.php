<?php

namespace App\Services\Security;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Recorder untuk login activity. Panggil dari AuthController & 2FA flow.
 */
class LoginActivityRecorder
{
    public function recordSuccess(User $user, Request $request, ?string $sessionId = null): LoginActivity
    {
        return LoginActivity::record([
            'user_id' => $user->id,
            'email' => $user->email,
            'outcome' => 'success',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_label' => $this->parseDeviceLabel($request->userAgent()),
            'session_id' => $sessionId ?? session()->getId(),
        ]);
    }

    public function recordFailure(?string $email, Request $request, string $reason, ?int $userId = null, string $outcome = 'failed'): LoginActivity
    {
        return LoginActivity::record([
            'user_id' => $userId,
            'email' => $email,
            'outcome' => $outcome,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_label' => $this->parseDeviceLabel($request->userAgent()),
            'reason' => $reason,
        ]);
    }

    /**
     * Cek apakah login ini "baru" (bukan dari IP/device yang pernah dipakai).
     * Return true kalau IP+UA baru dalam 90 hari terakhir → kirim alert.
     */
    public function isNewLoginFor(User $user, Request $request): bool
    {
        $ip = $request->ip();
        $ua = $request->userAgent();

        return !LoginActivity::where('user_id', $user->id)
            ->where('outcome', 'success')
            ->where('created_at', '>=', now()->subDays(90))
            ->where(function ($q) use ($ip, $ua) {
                $q->where('ip_address', $ip)
                  ->orWhere('user_agent', $ua);
            })
            ->exists();
    }

    protected function parseDeviceLabel(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';
        $browser = 'Browser';
        if (str_contains($userAgent, 'Firefox/')) $browser = 'Firefox';
        elseif (str_contains($userAgent, 'Edg/')) $browser = 'Edge';
        elseif (str_contains($userAgent, 'Chrome/')) $browser = 'Chrome';
        elseif (str_contains($userAgent, 'Safari/')) $browser = 'Safari';
        elseif (str_contains($userAgent, 'curl/')) $browser = 'curl';

        $os = 'Unknown';
        if (str_contains($userAgent, 'Windows')) $os = 'Windows';
        elseif (str_contains($userAgent, 'Mac OS X')) $os = 'macOS';
        elseif (str_contains($userAgent, 'Android')) $os = 'Android';
        elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) $os = 'iOS';
        elseif (str_contains($userAgent, 'Linux')) $os = 'Linux';

        return "{$browser} on {$os}";
    }
}
