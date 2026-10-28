<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kalau user punya 2FA confirmed atau mfa_required=true tapi session belum
 * pass 2FA challenge, redirect ke halaman challenge.
 */
class EnsureTwoFactorConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) return $next($request);

        $needs2fa = $user->two_factor_confirmed_at !== null || $user->mfa_required;
        $passed = $request->session()->get('2fa.passed', false);

        if ($needs2fa && !$passed) {
            // Ijinkan akses ke halaman 2FA challenge, logout, dan reset password.
            $allowed = ['two-factor.challenge', 'two-factor.verify', 'logout', 'password.reset'];
            if (in_array($request->route()?->getName(), $allowed, true)) {
                return $next($request);
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Two-factor authentication required.'], 423);
            }
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
