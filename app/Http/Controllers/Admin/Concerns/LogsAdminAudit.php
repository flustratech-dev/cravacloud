<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Pencatat jejak audit untuk aksi admin.
 *
 * Dipakai oleh controller-controller admin baru agar seluruh perubahan
 * yang berdampak ke pelanggan meninggalkan jejak yang dapat ditelusuri.
 */
trait LogsAdminAudit
{
    protected function audit(
        string $action,
        string $description,
        ?Model $subject = null,
        ?array $changes = null
    ): void {
        AdminAuditLog::create([
            'admin_user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
