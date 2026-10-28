<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan user punya currentOrganization aktif. Kalau tidak, coba
 * pick org pertama; kalau tidak ada, buat Personal Org otomatis.
 *
 * Ini menjamin semua controller downstream bisa memakai
 * $request->user()->currentOrganization tanpa null-check.
 */
class EnsureOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) return $next($request);

        // Kalau sudah ada current_organization_id yang valid, cek member.
        if ($user->current_organization_id) {
            $current = $user->currentOrganization;
            if ($current && $current->hasMember($user)) {
                return $next($request);
            }
        }

        // Coba ambil org pertama dimana user adalah member.
        $first = $user->organizations()->first();
        if ($first) {
            $user->forceFill(['current_organization_id' => $first->id])->save();
            return $next($request);
        }

        // Fallback: bikin Personal Org.
        $org = $user->createPersonalOrganization();
        $user->forceFill(['current_organization_id' => $org->id])->save();

        return $next($request);
    }
}
