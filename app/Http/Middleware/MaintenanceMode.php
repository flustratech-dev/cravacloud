<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penutup akses saat maintenance menyala.
 *
 * Dipakai dua cara:
 *   - tanpa parameter  : hanya memeriksa maintenance global (Tingkat 1)
 *   - dengan parameter : memeriksa cakupan tertentu, mis. 'maintenance:checkout'
 *
 * Balasan memakai HTTP 503 beserta header Retry-After, bukan 200,
 * agar mesin pencari tidak mengindeks halaman maintenance sebagai isi permanen.
 */
class MaintenanceMode
{
    public function __construct(
        protected MaintenanceService $maintenance
    ) {
    }

    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        if ($this->maintenance->shouldBypass($request)) {
            return $next($request);
        }

        $blocked = $scope === null
            ? $this->maintenance->isGlobalActive()
            : $this->maintenance->isBlocked($scope);

        if (!$blocked) {
            return $next($request);
        }

        $effectiveScope = $scope ?? 'dashboard';
        $message = $this->maintenance->messageFor($effectiveScope);
        $retryAfter = $this->maintenance->retryAfterSeconds($effectiveScope);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'maintenance' => true,
            ], 503)->header('Retry-After', (string) $retryAfter);
        }

        // Permintaan non-GET (mis. submit form) dikembalikan ke halaman asal
        // agar pengguna tidak kehilangan konteks, tetap dengan status 503.
        if (!$request->isMethod('GET')) {
            return back()
                ->with('error', $message)
                ->setStatusCode(503)
                ->header('Retry-After', (string) $retryAfter);
        }

        return response()
            ->view('errors.maintenance', [
                'message' => $message,
                'window' => $this->maintenance->runningWindowFor($effectiveScope),
            ], 503)
            ->header('Retry-After', (string) $retryAfter);
    }
}
