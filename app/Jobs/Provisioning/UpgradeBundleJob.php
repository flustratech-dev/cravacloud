<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Models\SupplierPurchase;
use App\Models\VpsSpec;
use App\Notifications\VpsUpgradedNotification;
use App\Services\AdminAlertService;
use App\Services\OrderStateMachine;
use App\Services\Provisioning\Contracts\SupportsUpgrade;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\SettingsService;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5C — apply a paid bundle upgrade upstream (ModifyInstancesBundle).
 *
 * Dispatched by UpgradeService::handleUpgradePayment AFTER the prorata
 * difference invoice is paid. provider->upgradeBundle() validates upgrade-only,
 * calls ModifyInstancesBundle, polls SUCCESS && RUNNING, and re-syncs the
 * instance cpu/ram/disk. On success this job:
 *   - points the original order (and the upgrade order) at the new spec so the
 *     next renewal bills the upgraded plan;
 *   - records one SupplierPurchase for the prorated upstream HPP difference
 *     (idempotent per upgrade order);
 *   - moves the upgrade order provisioning → active, keeps the VPS running,
 *     logs the activity and notifies the customer (white-label).
 *
 * On permanent failure the running server is NOT torn down — the customer keeps
 * service on the old bundle; admin is alerted to reconcile billing/refund.
 */
class UpgradeBundleJob extends AbstractProvisioningJob
{
    protected function kind(): string { return 'upgrade'; }

    protected function run($provider, ProvisioningTask $task): array
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            throw new ProviderException('Instance tidak ditemukan untuk upgrade.', false);
        }
        if (! $provider instanceof SupportsUpgrade) {
            throw new ProviderException('Provider tidak mendukung upgrade otomatis.', false);
        }

        $targetSpec = VpsSpec::find($task->input['target_spec_id'] ?? null);
        if (! $targetSpec) {
            throw new ProviderException('Paket tujuan upgrade tidak ditemukan.', false);
        }

        $task->markProgress(20, 'Menyiapkan upgrade sumber daya');
        $result = $provider->upgradeBundle($instance, $task, $targetSpec);
        $task->markProgress(90, 'Upgrade hampir selesai');

        return $result;
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        $instance = $task->vpsInstance;
        if (! $instance) return;

        $instance->refresh();
        $targetSpec = VpsSpec::find($task->input['target_spec_id'] ?? null);
        $upgradeOrder = $task->order;

        // Point the original order at the new spec (renewal bills the new plan).
        $originalOrder = $instance->order;
        $fromSpec = $originalOrder?->vpsSpec ?? VpsSpec::find($task->input['from_spec_id'] ?? null);
        if ($originalOrder && $targetSpec && $originalOrder->id !== $upgradeOrder?->id) {
            $originalOrder->update(['vps_spec_id' => $targetSpec->id]);
        }
        if ($upgradeOrder && $targetSpec) {
            $upgradeOrder->update(['vps_spec_id' => $targetSpec->id]);
        }

        // Cross-pool quota migration (Starter -> General) once per upgrade order.
        if ($fromSpec && $targetSpec
            && $fromSpec->upstream_bundle_type && $targetSpec->upstream_bundle_type
            && $fromSpec->upstream_bundle_type !== $targetSpec->upstream_bundle_type
            && empty(($upgradeOrder?->metadata ?? [])['quota_migrated'])) {
            $quota = app(\App\Services\UpstreamQuotaService::class);
            $region = $instance->datacenterRegion?->code
                ?: $quota->regionForLocation((string) ($originalOrder?->datacenter_location ?? $instance->datacenter_location));
            if ($region) {
                $quota->migrateUsedSlot($region, (string) $fromSpec->upstream_bundle_type, (string) $targetSpec->upstream_bundle_type);
                if ($upgradeOrder) {
                    $meta = $upgradeOrder->metadata ?? [];
                    $meta['quota_migrated'] = true;
                    $upgradeOrder->update(['metadata' => $meta]);
                }
            }
        }

        $this->recordPurchase($instance, $upgradeOrder, $targetSpec, $output);

        if ($targetSpec) {
            try {
                app(\App\Services\SubscriptionSyncService::class)->syncFromUpgrade($instance->fresh(), $targetSpec);
            } catch (\Throwable $e) {
                Log::warning('upgrade.subscription_sync_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
            }
        }

        if ($upgradeOrder) {
            try {
                app(OrderStateMachine::class)->transition($upgradeOrder, 'active', [
                    'reason' => 'Upgrade sumber daya selesai.',
                    'actor_type' => 'system',
                    'metadata' => ['task_id' => $task->id, 'vps_instance_id' => $instance->id],
                ]);
            } catch (\Throwable $e) {
                Log::warning('upgrade.order_transition_failed', ['order_id' => $upgradeOrder->id, 'error' => $e->getMessage()]);
            }
        }

        // Provider already polled RUNNING; keep the state machine consistent.
        try {
            app(VpsStateMachine::class)->transition($instance, 'running', [
                'reason' => 'Upgrade selesai, server tetap running.',
                'actor_type' => 'system',
                'allowSame' => true,
                'metadata' => ['task_id' => $task->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('upgrade.vps_transition_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }

        try {
            $instance->logActivity('upgrade', 'Server di-upgrade ke '.($targetSpec?->name ?? 'paket baru').'.', 'completed');
        } catch (\Throwable $e) {
            Log::warning('upgrade.log_activity_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }

        try {
            $customer = $instance->customer;
            if ($customer) {
                $customer->notify(new VpsUpgradedNotification($instance->fresh(), $targetSpec));
            }
        } catch (\Throwable $e) {
            Log::error('upgrade.notify_customer_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        $instance = $task->vpsInstance;
        $upgradeOrder = $task->order;

        if ($instance) {
            try {
                $instance->logActivity('upgrade', 'Upgrade gagal: '.$error, 'failed');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($upgradeOrder) {
            try {
                app(OrderStateMachine::class)->transition($upgradeOrder, 'failed', [
                    'reason' => 'Upgrade gagal setelah semua retry.',
                    'actor_type' => 'system',
                    'metadata' => ['task_id' => $task->id, 'error' => $error],
                    'onLocked' => function ($locked) use ($error) {
                        $locked->failure_reason = mb_substr($error, 0, 500);
                        $locked->save();
                    },
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // The running server is left intact; admin reconciles billing/refund.
        try {
            app(AdminAlertService::class)->alert(
                'provisioning.failed',
                [
                    'title' => 'Upgrade server gagal',
                    'order_id' => $upgradeOrder?->id,
                    'details' => [
                        'Task' => "#{$task->id} (upgrade)",
                        'Order' => '#' . ($upgradeOrder?->id ?? '?'),
                        'Instance' => $instance?->hostname ?? '?',
                    ],
                    'body' => 'Order upgrade #' . ($upgradeOrder?->id ?? '?') . ' untuk server '
                        . ($instance?->hostname ?? '?') . ' gagal diproses otomatis: ' . $error
                        . ' Server tetap berjalan pada paket lama. Mohon rekonsiliasi billing/refund manual.',
                    'action_url' => url('/admin/monitoring?tab=tasks&status=failed'),
                ],
                dedupeKey: 'task-' . $task->id,
                cooldownSeconds: 3600
            );
        } catch (\Throwable $e) {
            Log::error('upgrade.notify_admin_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * One SupplierPurchase per upgrade order for the prorated upstream HPP
     * difference. Idempotent per upstream_reference. Admin-only record.
     */
    private function recordPurchase($instance, $upgradeOrder, ?VpsSpec $targetSpec, array $output): void
    {
        if (! $upgradeOrder) return;

        $resourceId = (string) ($instance->provider_resource_id ?? '');
        $reference = $resourceId !== '' ? $resourceId.'-UPG-'.$upgradeOrder->id : 'UPG-'.$upgradeOrder->id;

        if (SupplierPurchase::where('vps_instance_id', $instance->id)
            ->where('upstream_reference', $reference)
            ->exists()) {
            return;
        }

        $meta = ($upgradeOrder->metadata ?? [])['upgrade'] ?? [];
        $rate = max(1, app(SettingsService::class)->int('tencent_usd_idr', 16500));

        SupplierPurchase::create([
            'order_id' => $upgradeOrder->id,
            'vps_instance_id' => $instance->id,
            'supplier' => 'tencent', // admin-only record
            'supplier_order_no' => 'UPG-'.$upgradeOrder->id.'-'.($resourceId ?: 'local'),
            'actual_cost' => round((float) ($meta['hpp_prorata'] ?? 0), 2),
            'currency' => 'IDR',
            'exchange_rate' => $rate,
            'upstream_reference' => $reference,
            'purchased_at' => now(),
            'supplier_expires_at' => $instance->expires_at ?? now()->addMonth(),
            'notes' => 'Upgrade bundle (selisih prorata).',
            'metadata' => [
                'kind' => 'upgrade',
                'bundle_id' => $output['bundle_id'] ?? $targetSpec?->upstream_bundle_id,
                'from_spec_id' => $meta['from_spec_id'] ?? null,
                'to_spec_id' => $targetSpec?->id,
                'margin' => $meta['margin'] ?? null,
            ],
        ]);
    }
}