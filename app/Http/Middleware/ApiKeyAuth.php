<?php

namespace App\Http\Middleware;

use App\Services\Security\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk endpoint API yang di-auth via API key.
 * Header: Authorization: Bearer cv_live_<prefix>.<secret>
 *
 * Kalau valid: login user pemilik key di guard 'web' untuk request ini
 * saja (tanpa session), plus inject $request->attributes->set('api_key', $key).
 *
 * Scope check dilakukan controller/policy lain via $request->attributes.
 */
class ApiKeyAuth
{
    public function __construct(protected ApiKeyService $svc)
    {
    }

    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $header = $request->bearerToken() ?? $request->header('X-API-Key');
        if (!$header) {
            return response()->json(['message' => 'API key missing.'], 401);
        }

        $key = $this->svc->verify($header);
        if (!$key) {
            return response()->json(['message' => 'Invalid or expired API key.'], 401);
        }

        if ($requiredScope && !$key->hasScope($requiredScope)) {
            return response()->json(['message' => "API key missing scope: {$requiredScope}"], 403);
        }

        $this->svc->touch($key, $request->ip());
        Auth::onceUsingId($key->user_id);
        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
