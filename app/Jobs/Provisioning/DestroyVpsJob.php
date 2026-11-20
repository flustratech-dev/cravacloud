<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Models\Subscription;
use App\Models\VpsInstance;
use App\Notifications\ServiceTerminatedNotification;
use App\Services\AdminAlertService;
use App\Services\UpstreamQuotaService;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

/**
 * Phase 4C — permanent destruction (kind=destroy).
 * provider->destroy(): IsolateInstances → poll SHUTDOWN → TerminateInstances
 * (two-stage mandatory upstream). On success:
 *   - release the USED quota slot, idempotent via provider_meta['quota_released'];
 *   - VpsStateMachine → terminated;
 *   - Order → terminated;
 *   - A1: sever bound Subscription(s) → terminated (ended_at=now()) + notify
 *     customer. The terminate cron leaves tencent subscriptions SUSPENDED at
 *     dispatch and never notifies; severance happens ONLY here, after the
 *     upstream slot is confirmed released.
 *
 * Dispatched by BillingTerminateExpiredCommand (safe deadline) and
 * AdminController::terminateInstance for provider=tencent only — the manual
 * flow keeps its existing immediate transitions.
 */
class DestroyVpsJob extends AbstractProvisioningJob
{
    protected function kind(): string
    {
        return 'destroy';
    }

    protected function run($provider, ProvisioningTask $task): array
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            throw new \RuntimeException('VpsInstance not found for task '.$task->id);
        }

        $task->markProgress(20, 'Menghapus server');

        return $provider->destroy($instance, $task);
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            return;
        }

        $this->releaseQuota($instance);

        try {
            app(VpsStateMachine::class)->transition($instance, 'terminated', [
                'reason' => 'Destroy upstream selesai.',
                'actor_type' => 'system',
                'metadata' => ['task_id' => $task->id],
                'allowSame' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.destroy_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }

        // Order follows the upstream reality (admin terminate / cron).
        $order = $instance->order;
        if ($order && $order->status !== 'terminated') {
            try {
                app(\App\Services\OrderStateMachine::class)->transition($order, 'terminated', [
                    'reason' => 'Destroy upstream selesai (terminasi permanen).',
                    'actor_type' => 'system',
                    'metadata' => ['task_id' => $task->id, 'vps_instance_id' => $instance->id],
                ]);
            } catch (\Throwable $e) {
                Log::warning('provisioning.destroy_order_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            }
        }

        // A1: sever the billing subscription ONLY now that the upstream slot
        // is released. The cron leaves it suspended at dispatch; terminated +
        // ended_at + customer notification happen here (manual flow severs
        // immediately in the command and never reaches this job).
        $this->terminateSubscriptions($instance, $task);
    }

    /**
     * Terminate every non-terminal subscription bound to this instance and
     * notify the customer. Idempotent: already terminated/cancelled rows are
     * skipped, so a destroy retry never double-notifies.
     */
    private function terminateSubscriptions(VpsInstance $instance, ProvisioningTask $task): void
    {
        $subs = Subscription::where('vps_instance_id', $instance->id)
            ->whereNotIn('status', ['terminated', 'cancelled'])
            ->with('customer')
            ->get();

        foreach ($subs as $sub) {
            try {
                $sub->update([
                    'status' => 'terminated',
                    'ended_at' => now(),
                ]);

                $sub->customer?->notify(new ServiceTerminatedNotification($sub));
            } catch (\Throwable $e) {
                Log::warning('provisioning.destroy_subscription_failed', [
                    'task_id' => $task->id,
                    'subscription_id' => $sub->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        // Never mark terminated when upstream destruction failed — the slot
        // is still occupied and reconcile keeps tracking it. Alert admin.
        $instance = $task->vpsInstance;

        app(AdminAlertService::class)->digest(
            'Destroy upstream gagal',
            'Instance #'.($instance?->id ?? '?').' ('.($instance?->hostname ?? '-').") gagal dihapus di upstream (task #{$task->id}). "
            ."Slot kuota BELUM dilepas; periksa manual sebelum mencoba ulang.\n\nError: {$error}"
        );
    }

    /** Idempotent used-slot release guarded by provider_meta['quota_released']. */
    private function releaseQuota(VpsInstance $instance): void
    {
        if ($instance->provider !== 'tencent') {
            return;
        }

        $meta = $instance->provider_meta ?? [];
        if (! empty($meta['quota_released'])) {
            return;
        }

        $quota = app(UpstreamQuotaService::class);
        $spec = $instance->order?->vpsSpec;
        $region = $instance->datacenterRegion?->code
            ?: $quota->regionForLocation((string) ($instance->order?->datacenter_location ?? $instance->datacenter_location));

        if (! $spec || ! $spec->upstream_bundle_type || ! $region) {
            return;
        }

        $quota->releaseUsed($region, (string) $spec->upstream_bundle_type);

        $meta['quota_released'] = true;
        $instance->provider_meta = $meta;
        $instance->save();
    }
}