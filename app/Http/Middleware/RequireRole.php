<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate berdasarkan role. Panggil di route:
 *   ->middleware('role:owner,admin')
 * Multiple role dipisah koma → OR logic.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();
        if (!$user) abort(401);

        foreach ($roles as $r) {
            if ($user->hasRole($r)) return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Role required: ' . implode('|', $roles)], 403);
        }
        abort(403, 'Role required: ' . implode('|', $roles));
    }
}
