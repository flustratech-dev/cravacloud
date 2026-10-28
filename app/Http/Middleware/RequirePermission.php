<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate berdasarkan permission slug. Panggil di route:
 *   ->middleware('permission:vps.manage')
 *
 * Cek permission via User::hasPermission() yang resolve dari role
 * user di currentOrganization + is_admin bypass.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();
        if (!$user) abort(401);

        if (!$user->hasPermission($permission)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => "Permission denied: {$permission}"], 403);
            }
            abort(403, "Permission denied: {$permission}");
        }
        return $next($request);
    }
}
