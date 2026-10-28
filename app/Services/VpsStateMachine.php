<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\VpsInstance;
use App\Models\VpsStatusHistory;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * State machine untuk VPS instance.
 *
 * Flow:
 *
 *   provisioning ──► running ──► stopped ──► running
 *        │             │           │
 *        │             ├─► rebooting ──► running
 *        │             ├─► reinstalling ──► running
 *        │             ├─► suspended ──► running (unsuspend)
 *        │             └─► terminated (terminal)
 *        └─► error ──► provisioning (retry) | terminated
 */
class VpsStateMachine
{
    public const STATUSES = [
        'provisioning',
        'running',
        'stopped',
        'rebooting',
        'reinstalling',
        'suspended',
        'terminated',
        'error',
    ];

    public const TRANSITIONS = [
        'provisioning' => ['running', 'error', 'terminated'],
        'running' => ['stopped', 'rebooting', 'reinstalling', 'suspended', 'terminated', 'error'],
        'stopped' => ['running', 'reinstalling', 'suspended', 'terminated', 'error'],
        'rebooting' => ['running', 'stopped', 'error'],
        'reinstalling' => ['running', 'error'],
        'suspended' => ['running', 'stopped', 'terminated'],
        'error' => ['provisioning', 'running', 'stopped', 'terminated'],
        'terminated' => [], // terminal
    ];

    public function transition(VpsInstance $vps, string $to, array $options = []): VpsInstance
    {
        if (!in_array($to, self::STATUSES, true)) {
            throw new InvalidStateTransitionException(
                'vps', $vps->status ?? '?', $to,
                'Status tujuan tidak dikenal.'
            );
        }

        $reason = $options['reason'] ?? null;
        $actorType = $options['actor_type'] ?? $this->detectActorType();
        $actorId = $options['actor_id'] ?? Auth::id();
        $metadata = $options['metadata'] ?? [];
        $onLocked = $options['onLocked'] ?? null;
        $allowSame = $options['allowSame'] ?? false;

        return DB::transaction(function () use (
            $vps, $to, $reason, $actorType, $actorId, $metadata, $onLocked, $allowSame
        ) {
            /** @var VpsInstance $locked */
            $locked = VpsInstance::whereKey($vps->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new InvalidStateTransitionException(
                    'vps', $vps->status ?? '?', $to,
                    'VPS tidak ditemukan saat lock.'
                );
            }

            $from = $locked->status;

            if ($from === $to) {
                if ($allowSame) {
                    return $locked;
                }
                throw new InvalidStateTransitionException(
                    'vps', $from, $to,
                    'VPS sudah berada pada status ini.'
                );
            }

            $allowed = self::TRANSITIONS[$from] ?? [];
            if (!in_array($to, $allowed, true)) {
                throw new InvalidStateTransitionException(
                    'vps', $from, $to,
                    'Transisi tidak ada di peta legal.'
                );
            }

            if ($onLocked instanceof Closure) {
                $onLocked($locked, $from, $to);
            }

            $locked->status = $to;
            $locked->save();

            VpsStatusHistory::create([
                'vps_instance_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'actor_user_id' => $actorId,
                'actor_type' => $actorType,
                'ip_address' => request()?->ip(),
                'metadata' => $metadata ?: null,
                'created_at' => now(),
            ]);

            Log::info('vps.state_transition', [
                'vps_id' => $locked->id,
                'from' => $from,
                'to' => $to,
                'actor_type' => $actorType,
                'reason' => $reason,
            ]);

            return $locked;
        });
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function nextStates(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    protected function detectActorType(): string
    {
        $user = Auth::user();
        if ($user) {
            return !empty($user->is_admin) ? 'admin' : 'customer';
        }
        return 'system';
    }
}
