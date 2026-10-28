<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VpsActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vps_instance_id',
        'user_id',
        'action',
        'description',
        'status',
        'ip_address',
        'user_agent',
    ];

    public function vpsInstance()
    {
        return $this->belongsTo(VpsInstance::class, 'vps_instance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getActionBadgeAttribute(): array
    {
        return match ($this->action) {
            'start' => ['label' => 'Power On', 'color' => 'emerald', 'bg' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'],
            'stop' => ['label' => 'Power Off', 'color' => 'rose', 'bg' => 'bg-rose-500/10 text-rose-400 border-rose-500/20'],
            'reboot' => ['label' => 'Reboot', 'color' => 'amber', 'bg' => 'bg-amber-500/10 text-amber-400 border-amber-500/20'],
            'force_reboot' => ['label' => 'Force Reboot', 'color' => 'orange', 'bg' => 'bg-orange-500/10 text-orange-400 border-orange-500/20'],
            'reinstall' => ['label' => 'OS Reinstall', 'color' => 'blue', 'bg' => 'bg-blue-500/10 text-blue-400 border-blue-500/20'],
            'provision' => ['label' => 'Provisioning', 'color' => 'cyan', 'bg' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20'],
            'password_reveal' => ['label' => 'View Password', 'color' => 'purple', 'bg' => 'bg-purple-500/10 text-purple-400 border-purple-500/20'],
            'suspend' => ['label' => 'Suspended', 'color' => 'red', 'bg' => 'bg-red-500/10 text-red-400 border-red-500/20'],
            'unsuspend' => ['label' => 'Unsuspended', 'color' => 'emerald', 'bg' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'],
            'terminate' => ['label' => 'Terminated', 'color' => 'zinc', 'bg' => 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20'],
            default => ['label' => ucfirst($this->action), 'color' => 'slate', 'bg' => 'bg-slate-500/10 text-slate-400 border-slate-500/20'],
        };
    }
}
