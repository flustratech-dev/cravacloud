<?php

namespace App\Services\Provisioning;

use App\Jobs\Provisioning\DestroyVpsJob;
use App\Jobs\Provisioning\RenewVpsJob;
use App\Jobs\Provisioning\StartVpsJob;
use App\Jobs\Provisioning\StopVpsJob;
use App\Models\ProvisioningTask;
use App\Models\VpsInstance;
use App\Services\VpsStateMachine;

/**
 * Phase 4C — shared dispatch helpers for the tencent lifecycle flow
 * (billing crons + AdminController). The manual flow NEVER goes through
 * here: callers check supports() first and keep their existing immediate
 * state transitions.
 *
 * Design:
 *  - suspend  → StopVpsJob; 'suspended' is set AFTER upstream stop succeeds
 *               (task.input.target_status). Already-stopped instances skip
 *               the API call entirely.
 *  - destroy  → DestroyVpsJob (Isolate → SHUTDOWN → Terminate upstream),
 *               then quota release + VPS/order → terminated on success.
 *  - unsuspend→ upstream state decides: STOPPED → StartVpsJob,
 *               SHUTDOWN (recycle bin) → RenewVpsJob — RenewInstances is
 *               the ONLY recovery path (Revisi 4).
 *
 * Duplicate guard: a live (pending/running) task of the same kind for the
 * instance means a previous request is still in flight → skip, so cron
 * re-runs never stack jobs.
 */
class VpsLifecycleDispatcher
{
    public function __construct(private readonly ProviderManager $manager)
    {
    }

    /** Only auto-provisioned tencent instances with an upstream id. */
    public function supports(VpsInstance $instance): bool
    {
        return $instance->provider === 'tencent' && (bool) $instance->provider_resource_id;
    }

    public function suspend(VpsInstance $instance, string $reason): void
    {
        if ($instance->status === 'stopped') {
            // Already powered off upstream — no API call needed.
            app(VpsStateMachine::class)->transition($instance, 'suspended', [
                'reason' => $reason,
                'actor_type' => 'system',
            ]);

            return;
        }

        if ($this->hasLiveTask($instance, 'stop')) {
            return;
        }

        $task = $this->createTask($instance, 'stop', [
            'target_status' => 'suspended',
            'reason' => $reason,
        ]);
        StopVpsJob::dispatch($task->id);
    }

    public function destroy(VpsInstance $instance, string $reason): void
    {
        if ($this->hasLiveTask($instance, 'destroy')) {
            return;
        }

        $task = $this->createTask($instance, 'destroy', ['reason' => $reason]);
        DestroyVpsJob::dispatch($task->id);
    }

    /**
     * @throws \Throwable when the upstream state cannot be queried — caller
     *         surfaces the error; nothing is dispatched blindly.
     */
    public function unsuspend(VpsInstance $instance, string $reason): void
    {
        $provider = $this->manager->provider('tencent');
        $status = $provider->status($instance);
        $upstream = strtoupper((string) ($status['meta']['upstream_state'] ?? ''));

        if (in_array($upstream, ['SHUTDOWN', 'ISOLATING'], true)) {
            if ($this->hasLiveTask($instance, 'renew')) {
                return;
            }

            // Recycle-bin recovery: renew 1 month (the only recovery path),
            // job re-syncs IPs and flips the instance back to running.
            $task = $this->createTask($instance, 'renew', [
                'months' => 1,
                'unsuspend' => true,
                'reason' => $reason,
            ]);
            RenewVpsJob::dispatch($task->id);

            return;
        }

        if ($this->hasLiveTask($instance, 'start')) {
            return;
        }

        $task = $this->createTask($instance, 'start', ['reason' => $reason]);
        StartVpsJob::dispatch($task->id);
    }

    private function hasLiveTask(VpsInstance $instance, string $kind): bool
    {
        return ProvisioningTask::where('vps_instance_id', $instance->id)
            ->where('kind', $kind)
            ->whereIn('status', ['pending', 'running'])
            ->exists();
    }

    private function createTask(VpsInstance $instance, string $kind, array $input): ProvisioningTask
    {
        return ProvisioningTask::create([
            'order_id' => $instance->order_id,
            'vps_instance_id' => $instance->id,
            'kind' => $kind,
            'status' => 'pending',
            'provider' => 'tencent',
            'input' => $input,
        ]);
    }
}