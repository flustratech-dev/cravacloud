<?php

namespace App\Services\Security;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AdminAuditRecorder
{
    /**
     * Catat aksi admin. $subject boleh Eloquent Model atau null.
     * $changes = ['before' => [...], 'after' => [...]] atau context lain.
     */
    public function log(string $action, ?Model $subject = null, ?string $description = null, array $changes = []): AdminAuditLog
    {
        return AdminAuditLog::create([
            'admin_user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description ? mb_substr($description, 0, 500) : null,
            'changes' => $changes ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
