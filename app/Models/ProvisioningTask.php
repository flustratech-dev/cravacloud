<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Task async untuk operasi provider (create/start/stop/reboot/reinstall).
 * Job queue meng-update row ini (progress, current_step, error) supaya
 * dashboard customer bisa polling status realtime.
 *
 * Status: pending | running | succeeded | failed | cancelled
 * Kind:   provision | start | stop | reboot | force_reboot | reinstall | destroy | reconcile
 */
class ProvisioningTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'order_id', 'vps_instance_id', 'kind', 'status',
        'progress', 'current_step', 'provider', 'input', 'output',
        'error', 'attempts', 'started_at', 'finished_at', 'actor_user_id',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'progress' => 'integer',
        'attempts' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $task) {
            if (empty($task->uuid)) {
                $task->uuid = (string) Str::uuid();
            }
        });
    }

    public function order() { return $this->belongsTo(Order::class); }
    public function vpsInstance() { return $this->belongsTo(VpsInstance::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }

    public function markStarted(?string $step = null): void
    {
        $this->update([
            'status' => 'running',
            'attempts' => $this->attempts + 1,
            'started_at' => $this->started_at ?? now(),
            'current_step' => $step,
            'error' => null,
        ]);
    }

    public function markProgress(int $progress, ?string $step = null): void
    {
        $this->update([
            'progress' => max(0, min(100, $progress)),
            'current_step' => $step ?? $this->current_step,
        ]);
    }

    public function markSucceeded(?array $output = null): void
    {
        $this->update([
            'status' => 'succeeded',
            'progress' => 100,
            'output' => $output ?? $this->output,
            'finished_at' => now(),
            'error' => null,
        ]);
    }

    public function markFailed(string $error, ?array $output = null): void
    {
        $this->update([
            'status' => 'failed',
            'output' => $output ?? $this->output,
            'error' => mb_substr($error, 0, 5000),
            'finished_at' => now(),
        ]);
    }
}
