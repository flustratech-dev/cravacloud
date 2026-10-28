<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\Request;

/**
 * PHASE 7 - Masuk sebagai pelanggan dan kembali ke admin.
 */
class ImpersonationController extends Controller
{
    use LogsAdminAudit;

    public function __construct(
        protected ImpersonationService $impersonation
    ) {
    }

    /**
     * Rute admin: mulai impersonation.
     */
    public function start(Request $request, int $id)
    {
        $admin = $request->user();
        $target = User::findOrFail($id);

        $error = $this->impersonation->start($request, $admin, $target);

        if ($error !== null) {
            return back()->with('error', $error);
        }

        // Dicatat atas nama admin, bukan pelanggan yang sekarang sedang login.
        AdminAuditLog::create([
            'admin_user_id' => $admin->id,
            'action' => 'impersonation.started',
            'description' => "Masuk sebagai pelanggan {$target->email}.",
            'subject_type' => User::class,
            'subject_id' => $target->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('dashboard.index')
            ->with('success', 'Anda sekarang melihat panel sebagai ' . ($target->full_name ?: $target->email) . '.');
    }

    /**
     * Rute pengguna terautentikasi: kembali ke akun admin.
     */
    public function leave(Request $request)
    {
        $data = $this->impersonation->data($request);

        if (!$data) {
            return redirect()->route('dashboard.index');
        }

        $admin = $this->impersonation->stop($request, 'left');

        if (!$admin) {
            return redirect()->route('login')->with('error', 'Sesi admin tidak valid. Silakan masuk kembali.');
        }

        $this->audit(
            'impersonation.ended',
            'Kembali dari sesi masuk sebagai ' . ($data['target_name'] ?? 'pelanggan') . '.'
        );

        return redirect()->route('admin.customers')->with('success', 'Anda kembali ke akun admin.');
    }
}
