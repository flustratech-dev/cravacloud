<?php

namespace App\Services\Provisioning;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\SupplierPurchase;
use App\Models\VpsInstance;
use App\Notifications\AdminProvisioningFailedNotification;
use App\Notifications\VpsProvisionedNotification;
use App\Services\OrderStateMachine;
use App\Services\Provisioning\Stacks\StackRegistry;
use App\Services\SettingsService;
use App\Services\UpstreamQuotaService;
use App\Services\VpsStateMachine;
use App\Support\MailIdentity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Shared finalization for the two-stage provisioning flow (D11):
 *   ProvisionVpsJob (infra) ─┬─ stack pending → InstallStackJob ─→ finalize()
 *                            └─ no stack ────────────────────────→ finalize()
 *
 * finalize(): persist server details, record SupplierPurchase (D9, USD HPP ×
 * admin-managed rate), commit the quota reservation, transition VPS→running
 * and Order→active, notify the customer. Everything customer-facing stays
 * white-label ('cravacloud'); the vendor name never leaves the admin side.
 *
 * fail(): VPS→error, Order→failed, admin alert, and quota settlement:
 *   - upstream instance EXISTS (provider_resource_id) → commit() — the slot
 *     is genuinely occupied; syncFromProvider() reconciles later.
 *   - no instance → releaseReserved().
 * Both paths are idempotent via order.metadata['quota_settled'] (A3).
 */
class ProvisioningFinalizer
{
    public function __construct(
        private readonly UpstreamQuotaService $quota,
        private readonly SettingsService $settings,
        private readonly StackRegistry $stacks,
    ) {
    }

    /** Persist provider output onto the instance (used by both stages). */
    public function applyInstanceDetails(VpsInstance $instance, array $output): void
    {
        $instance->fill([
            'public_ip' => $output['public_ip'] ?? $instance->public_ip,
            'private_ip' => $output['private_ip'] ?? $instance->private_ip,
            'ssh_port' => $output['ssh_port'] ?? $instance->ssh_port,
            'ssh_username' => $output['ssh_username'] ?? $instance->ssh_username,
            'initial_root_password' => $output['root_password'] ?? $instance->initial_root_password,
            'provider_resource_id' => $output['resource_id'] ?? $instance->provider_resource_id,
            'provider_meta' => array_merge($instance->provider_meta ?? [], $output['meta'] ?? []),
        ])->save();
    }

    public function finalize(ProvisioningTask $task, VpsInstance $instance, array $output): void
    {
        $this->applyInstanceDetails($instance, $output);
        $this->applyAppUrl($instance, $output);

        $isTencent = $task->provider === 'tencent';

        if ($isTencent) {
            $this->recordSupplierPurchase($instance, $output);
            $this->settleQuota($task, $instance, commit: true);
        }

        try {
            app(VpsStateMachine::class)->transition($instance, 'running', [
                'reason' => 'Provisioning selesai (job sukses).',
                'actor_type' => 'system',
                'metadata' => ['task_id' => $task->id, 'provider' => $task->provider],
            ]);
        } catch (InvalidStateTransitionException $e) {
            Log::warning('provisioning.vps_transition_failed_on_success', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }

        $order = $task->order;
        if ($order) {
            try {
                app(OrderStateMachine::class)->transition($order, 'active', [
                    'reason' => 'Provisioning sukses.',
                    'actor_type' => 'system',
                    'metadata' => ['task_id' => $task->id, 'vps_instance_id' => $instance->id],
                    'onLocked' => function (Order $locked) use ($instance) {
                        $locked->starts_at = $locked->starts_at ?? $locked->activationStartsAt();
                        $locked->expires_at = $locked->expires_at ?? $instance->expires_at;
                        $locked->grace_period_ends_at = $locked->grace_period_ends_at ?? $instance->grace_period_ends_at;
                        $locked->save();
                    },
                ]);
            } catch (InvalidStateTransitionException $e) {
                Log::warning('provisioning.order_transition_failed_on_success', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $instance->logActivity(
                action: 'provision',
                description: "VPS {$instance->hostname} berhasil disiapkan otomatis.",
                status: 'completed',
            );
        } catch (\Throwable $e) {
            Log::warning('provisioning.log_activity_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }

        try {
            app(\App\Services\SubscriptionSyncService::class)->ensureForInstance($instance->fresh());
        } catch (\Throwable $e) {
            Log::warning('provisioning.subscription_sync_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }

        // Kirim notifikasi kredensial server VPS ke pelanggan (white-label).
        try {
            $customer = $instance->customer;
            if ($customer) {
                $customer->notify(new VpsProvisionedNotification($instance));
            }
        } catch (\Throwable $e) {
            Log::error('provisioning.notify_customer_failed', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function fail(ProvisioningTask $task, string $error): void
    {
        $instance = $task->vpsInstance;

        if ($instance) {
            if ($task->provider === 'tencent') {
                // Slot occupied upstream? commit : release (idempotent).
                $this->settleQuota($task, $instance, commit: (bool) $instance->provider_resource_id);
            }

            try {
                app(VpsStateMachine::class)->transition($instance, 'error', [
                    'reason' => 'Provisioning gagal: '.$error,
                    'actor_type' => 'system',
                    'metadata' => ['task_id' => $task->id],
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $order = $task->order;
        if ($order) {
            try {
                app(OrderStateMachine::class)->transition($order, 'failed', [
                    'reason' => 'Provisioning gagal setelah semua retry.',
                    'actor_type' => 'system',
                    'metadata' => ['task_id' => $task->id, 'error' => $error],
                    'onLocked' => function (Order $locked) use ($error) {
                        $locked->failure_reason = mb_substr($error, 0, 500);
                        $locked->save();
                    },
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Kirim alert darurat (Email + WA) ke admin jika provisioning otomatis gagal.
        try {
            foreach (MailIdentity::adminRecipients() as $adminEmail) {
                Notification::route('mail', $adminEmail)
                    ->notify(new AdminProvisioningFailedNotification($task, $error));
            }

            app(\App\Services\AdminAlertService::class)->alert(
                'provisioning.failed',
                [
                    'title' => "Provisioning Gagal — Task #{$task->id}",
                    'order_id' => $order?->id,
                    'send_email' => false,
                    'email_sent_override' => true,
                    'details' => [
                        'Task' => "#{$task->id} ({$task->kind})",
                        'Order' => '#' . ($order?->id ?? '-'),
                        'Instance' => $instance?->hostname ?? '-',
                        'Error' => \Illuminate\Support\Str::limit($error, 160),
                    ],
                    'body' => $error,
                    'action_url' => url('/admin/monitoring?tab=tasks&status=failed'),
                ],
                dedupeKey: 'task-' . $task->id,
                cooldownSeconds: 3600
            );
        } catch (\Throwable $e) {
            Log::error('provisioning.notify_admin_failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =====================================================================
    // Internals
    // =====================================================================

    /** Panel first-run URL from the stack template (no vendor wording). */
    private function applyAppUrl(VpsInstance $instance, array $output): void
    {
        if ($instance->app_url || ! $instance->public_ip) {
            return;
        }

        $stackKey = (string) ($output['meta']['stack'] ?? $instance->control_panel ?: 'none');
        $definition = $this->stacks->get($stackKey);

        if ($definition?->appUrlTemplate) {
            $instance->app_url = str_replace('{ip}', (string) $instance->public_ip, $definition->appUrlTemplate);
            $instance->save();
        }
    }

    /**
     * D9: one SupplierPurchase row per upstream purchase period.
     * actual_cost = bundle USD price × setting tencent_usd_idr (admin-managed).
     * Idempotent per upstream_reference (InstanceId).
     */
    private function recordSupplierPurchase(VpsInstance $instance, array $output): void
    {
        $resourceId = (string) ($output['resource_id'] ?? $instance->provider_resource_id);
        if ($resourceId === '') {
            return;
        }

        if (SupplierPurchase::where('vps_instance_id', $instance->id)
            ->where('upstream_reference', $resourceId)
            ->exists()) {
            return;
        }

        $meta = array_merge($instance->provider_meta ?? [], $output['meta'] ?? []);
        $bundleId = (string) ($meta['bundle_id'] ?? '');
        $usd = (float) config("services.tencent.bundle_prices_usd.{$bundleId}", 0);
        $rate = max(1, $this->settings->int('tencent_usd_idr', 16500));

        SupplierPurchase::create([
            'order_id' => $instance->order_id,
            'vps_instance_id' => $instance->id,
            'supplier' => 'tencent', // admin-only record
            'supplier_order_no' => 'AUTO-'.$resourceId, // upstream purchase reference
            'actual_cost' => round($usd * $rate, 2),
            'currency' => 'IDR',
            'exchange_rate' => $rate,
            'upstream_reference' => $resourceId,
            'purchased_at' => now(),
            'supplier_expires_at' => now()->addMonth(), // prepaid 1 month upstream
            'notes' => 'Auto-provisioning (canary).',
            'metadata' => [
                'bundle_id' => $bundleId,
                'cost_usd' => $usd,
                'region' => $meta['region'] ?? null,
            ],
        ]);
    }

    /**
     * Idempotent quota settlement. The flag lives on ORDER.metadata (review
     * patch A3) so a NEW retry task for the same order cannot double-commit;
     * task.output['quota_settled'] is still read for backward compatibility
     * with Phase 3 tasks.
     */
    private function settleQuota(ProvisioningTask $task, VpsInstance $instance, bool $commit): void
    {
        $order = $task->order;
        $output = $task->output ?? [];
        $orderMeta = $order?->metadata ?? [];

        if (! empty($orderMeta['quota_settled']) || ! empty($output['quota_settled'])) {
            return;
        }

        $spec = $order?->vpsSpec;
        $region = $instance->datacenterRegion?->code
            ?: $this->quota->regionForLocation((string) ($order?->datacenter_location ?? $instance->datacenter_location));

        if (! $spec || ! $spec->upstream_bundle_type || ! $region) {
            return;
        }

        if ($commit) {
            $this->quota->commit($region, (string) $spec->upstream_bundle_type);
        } else {
            $this->quota->releaseReserved($region, (string) $spec->upstream_bundle_type);
        }

        if ($order) {
            $orderMeta['quota_settled'] = true;
            $order->update(['metadata' => $orderMeta]);
        } else {
            // No order context (edge): fall back to the task-level flag.
            $output['quota_settled'] = true;
            $task->update(['output' => $output]);
        }
    }
}