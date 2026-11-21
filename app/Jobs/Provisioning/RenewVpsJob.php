<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningTask;
use App\Models\SupplierPurchase;
use App\Models\VpsInstance;
use App\Services\AdminAlertService;
use App\Services\Provisioning\Contracts\SupportsRenewal;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\ProvisioningFinalizer;
use App\Services\SettingsService;
use App\Services\VpsStateMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Phase 4B — upstream renewal for auto-provisioned (tencent) instances.
 * Dispatched by RenewalService AFTER the customer-side renewal committed,
 * so the customer validity period is always safe regardless of upstream
 * outcome.
 *
 * Success: re-sync IPs (recycle-bin recovery may change them), VPS→running,
 * record ONE renewal SupplierPurchase (idempotent per renewal order) and
 * refresh supplier_expires_at from the real upstream ExpiredTime.
 *
 * InsufficientBalance: provider_meta['renew_pending'] = true + admin alert;
 * the instance keeps its state and the customer period stays intact.
 */
class RenewVpsJob extends AbstractProvisioningJob
{
    protected function kind(): string
    {
        return 'renew';
    }

    protected function run($provider, ProvisioningTask $task): array
    {
        if (! $provider instanceof SupportsRenewal) {
            throw new ProviderException('Provider does not support upstream renewal.', false, 'UnsupportedRenewal');
        }

        $instance = $task->vpsInstance;
        if (! $instance) {
            throw new \RuntimeException('VpsInstance not found for task '.$task->id);
        }

        $months = max(1, (int) ($task->input['months'] ?? 1));
        $task->markProgress(20, 'Memperpanjang masa aktif');

        return $provider->renew($instance, $task, $months);
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            return;
        }

        // Re-sync public/private IP + provider meta (Revisi 4).
        app(ProvisioningFinalizer::class)->applyInstanceDetails($instance, $output);

        try {
            app(VpsStateMachine::class)->transition($instance, 'running', [
                'reason' => 'Perpanjangan upstream selesai.',
                'actor_type' => 'system',
                'metadata' => ['task_id' => $task->id],
                'allowSame' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('provisioning.renew_transition_failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
        }

        $this->recordRenewalPurchase($task, $instance, $output);

        // Clear a pending flag left by an earlier balance failure.
        $meta = $instance->provider_meta ?? [];
        if (! empty($meta['renew_pending'])) {
            unset($meta['renew_pending']);
            $instance->provider_meta = $meta;
            $instance->save();
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        $instance = $task->vpsInstance;
        if (! $instance) {
            return;
        }

        if (str_contains(strtolower($error), 'insufficient')) {
            // Customer validity was already extended in the DB — keep it.
            // Flag for admin: top up the upstream deposit, then re-run.
            $meta = $instance->provider_meta ?? [];
            $meta['renew_pending'] = true;
            $instance->provider_meta = $meta;
            $instance->save();

            app(AdminAlertService::class)->alert(
                'provisioning.failed',
                [
                    'title' => 'Renewal upstream tertunda — saldo tidak cukup',
                    'order_id' => $task->order_id,
                    'details' => [
                        'Task' => "#{$task->id} (renew)",
                        'Instance' => "#{$instance->id} ({$instance->hostname})",
                        'Status' => 'Masa aktif pelanggan TETAP aman (renew_pending)',
                    ],
                    'body' => "Instance #{$instance->id} ({$instance->hostname}) gagal diperpanjang di upstream karena saldo tidak cukup. "
                        . "Masa aktif pelanggan TETAP aman. Top up saldo upstream lalu jalankan ulang task renew #{$task->id}.\n\nError: {$error}",
                    'action_url' => url('/admin/monitoring?tab=tasks&status=failed'),
                ],
                dedupeKey: 'task-' . $task->id,
                cooldownSeconds: 3600
            );

            return; // never flip the instance to error for a balance issue
        }

        // Other permanent failures: alert admin, keep the instance state
        // (no destructive transition — reconcile will re-check upstream).
        app(AdminAlertService::class)->alert(
            'provisioning.failed',
            [
                'title' => 'Renewal upstream gagal',
                'order_id' => $task->order_id,
                'details' => [
                    'Task' => "#{$task->id} (renew)",
                    'Instance' => "#{$instance->id} ({$instance->hostname})",
                    'Error' => \Illuminate\Support\Str::limit($error, 160),
                ],
                'body' => "Instance #{$instance->id} ({$instance->hostname}) gagal diperpanjang di upstream (task #{$task->id}). "
                    . "Masa aktif pelanggan tetap aman; periksa manual.\n\nError: {$error}",
                'action_url' => url('/admin/monitoring?tab=tasks&status=failed'),
            ],
            dedupeKey: 'task-' . $task->id,
            cooldownSeconds: 3600
        );
    }

    /**
     * D9: one renewal SupplierPurchase per renewal ORDER. The upstream
     * reference is suffixed with the order id so it never collides with the
     * original provisioning purchase (same InstanceId) and re-running the
     * job cannot double-record.
     */
    private function recordRenewalPurchase(ProvisioningTask $task, VpsInstance $instance, array $output): void
    {
        if ($task->provider !== 'tencent') {
            return;
        }

        $resourceId = (string) ($output['resource_id'] ?? $instance->provider_resource_id);
        if ($resourceId === '') {
            return;
        }

        $reference = $resourceId.'-RENEW-'.$task->order_id;
        if (SupplierPurchase::where('vps_instance_id', $instance->id)
            ->where('upstream_reference', $reference)
            ->exists()) {
            return;
        }

        $months = max(1, (int) ($task->input['months'] ?? 1));
        $meta = array_merge($instance->provider_meta ?? [], $output['meta'] ?? []);
        $bundleId = (string) ($meta['bundle_id'] ?? '');
        $usd = (float) config("services.tencent.bundle_prices_usd.{$bundleId}", 0) * $months;
        $rate = max(1, app(SettingsService::class)->int('tencent_usd_idr', 16500));

        // Prefer the REAL upstream expiry; fall back to the latest purchase
        // expiry (or now) + months when the API did not return ExpiredTime.
        $expires = null;
        if (! empty($meta['upstream_expired_time'])) {
            try {
                $expires = Carbon::parse($meta['upstream_expired_time']);
            } catch (\Throwable $e) {
                $expires = null;
            }
        }
        if (! $expires) {
            $last = SupplierPurchase::where('vps_instance_id', $instance->id)
                ->orderByDesc('supplier_expires_at')
                ->first();
            $base = $last?->supplier_expires_at && $last->supplier_expires_at->isFuture()
                ? $last->supplier_expires_at->copy()
                : now();
            $expires = $base->addMonths($months);
        }

        SupplierPurchase::create([
            'order_id' => $task->order_id,
            'vps_instance_id' => $instance->id,
            'supplier' => 'tencent', // admin-only record
            'supplier_order_no' => 'AUTO-'.$reference,
            'actual_cost' => round($usd * $rate, 2),
            'currency' => 'IDR',
            'exchange_rate' => $rate,
            'upstream_reference' => $reference,
            'purchased_at' => now(),
            'supplier_expires_at' => $expires,
            'notes' => 'Auto-renewal (upstream).',
            'metadata' => [
                'bundle_id' => $bundleId,
                'cost_usd' => $usd,
                'months' => $months,
                'renewal' => true,
            ],
        ]);
    }
}