<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE 7 - Penjaga selama admin masuk sebagai pelanggan.
 *
 * Memakai DAFTAR PUTIH, bukan daftar hitam: setiap permintaan yang mengubah
 * data (POST/PUT/PATCH/DELETE) ditolak, kecuali rute di
 * ImpersonationService::ALLOWED_WRITE_ROUTES. Dengan begitu rute baru yang
 * ditambahkan di kemudian hari otomatis ikut terlindungi, tanpa perlu diingat.
 *
 * Tujuannya: admin dapat MELIHAT panel persis seperti pelanggan, tetapi tidak
 * dapat bertindak atas nama pelanggan (membayar, mengganti kata sandi,
 * menghapus data, membalas tiket, dan seterusnya).
 */
class ImpersonationGuard
{
    public function __construct(
        protected ImpersonationService $impersonation
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->impersonation->isActive($request)) {
            return $next($request);
        }

        if ($this->impersonation->isExpired($request)) {
            $admin = $this->impersonation->stop($request, 'expired');
            $message = 'Sesi masuk sebagai pelanggan berakhir otomatis setelah '
                . ImpersonationService::MAX_MINUTES . ' menit.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 440);
            }

            return $admin
                ? redirect()->route('admin.customers')->with('error', $message)
                : redirect()->route('login')->with('error', $message);
        }

        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && in_array($routeName, ImpersonationService::ALLOWED_WRITE_ROUTES, true)) {
            return $next($request);
        }

        $this->impersonation->recordBlockedAction($request);

        $message = 'Aksi ini tidak diizinkan saat masuk sebagai pelanggan. '
            . 'Mode ini hanya untuk melihat tampilan pelanggan.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'impersonating' => true], 403);
        }

        return back()->with('error', $message);
    }
}
