<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PHASE 7 - Satu sesi impersonation.
 */
class ImpersonationLog extends Model
{
    protected $fillable = [
        'admin_user_id',
        'impersonated_user_id',
        'started_at',
        'ended_at',
        'end_reason',
        'blocked_actions',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'blocked_actions' => 'integer',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function impersonated()
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->ended_at) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }

    public function getEndReasonLabelAttribute(): string
    {
        return match ($this->end_reason) {
            'left' => 'Kembali ke admin',
            'logout' => 'Logout',
            'expired' => 'Kedaluwarsa otomatis',
            'invalid' => 'Dihentikan sistem',
            null => 'Masih berjalan',
            default => ucfirst((string) $this->end_reason),
        };
    }
}
