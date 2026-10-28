<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Cek apakah request berasal dari session web admin yang sedang login
        if (auth('web')->check() && auth('web')->user()->is_admin) {
            return $next($request);
        }

        // 2. Cek API Key via Bearer token atau header X-Admin-Key
        $apiKey = $request->bearerToken() ?: $request->header('X-Admin-Key');
        $configuredKey = config('cravacloud.admin_api_key');

        if (!empty($configuredKey) && !empty($apiKey) && hash_equals($configuredKey, $apiKey)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak. Diperlukan Admin API Key yang valid atau sesi Admin yang aktif.',
        ], 401);
    }
}
