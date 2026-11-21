<?php

namespace App\Services\Provisioning;

use App\Jobs\Provisioning\ProvisionVpsJob;
use App\Models\DatacenterRegion;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\VpsInstance;
use App\Models\VpsStatusHistory;
use App\Services\AdminAlertService;
use App\Services\OrderStateMachine;
use App\Services\RenewalService;
use App\Services\UpstreamQuotaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 entry point for zero-touch provisioning (blueprint §2.1).
 *
 * Called by PaymentCompletionService right after an order becomes paid.
 * Decision chain (all must pass, otherwise the order simply stays in the
 * existing MANUAL flow — zero behavior change while the gate is OFF):
 *
 *   1. ProvisioningGate::allows($spec)   — master switch + mapping + whitelist (D13)
 *   2. UpstreamQuotaService::reserveHard — atomic slot reservation at paid.
 *      Pool full → HOLD the paid order + alert admin. NEVER auto-fallback
 *      to another region (D4); admin decides with the customer.
 *   3. Create VpsInstance + ProvisioningTask, dispatch ProvisionVpsJob.
 *      BalanceGuard::assertSufficient(fresh) runs inside the provider,
 *      immediately before CreateInstances.
 *
 * Idempotent: an order with an existing instance or a live/succeeded
 * provision task is never dispatched twice.
 */
class ProvisioningOrchestrator
{
    public function __construct(
        private readonly ProvisioningGate $gate,
        private readonly UpstreamQuotaService $quota,
        private readonly AdminAlertService $alerts,
        private readonly \App\Services\Provisioning\Stacks\StackRegistry $stacks,
    ) {
    }

    public function maybeAutoProvision(Order $order): void
    {
        if (! $order->isNew()) {
            return; // renewals are handled by RenewalService (Phase 4).
        }

        if (! empty($order->metadata['manual_fulfillment'])) {
            return; // explicitly pinned to manual fulfillment
        }

        $spec = $order->vpsSpec;
        if (! $spec || ! $this->gate->allows($spec)) {
            return; // gate OFF / unmapped (D3) / not whitelisted → manual flow.
        }

        // Idempotency: never double-dispatch for the same order.
        if ($order->vpsInstance()->exists()) {
            return;
        }
        if ($order->provisioningTasks()
            ->where('kind', 'provision')
            ->whereIn('status', ['pending', 'running', 'succeeded'])
            ->exists()) {
            return;
        }

        // Review patch A3: validate stack + OS compatibility BEFORE taking a
        // quota reservation — unsupported combos stay manual, no false failure.
        $stackKey = (string) ($order->control_panel ?: 'none');
        $os = (string) ($order->os ?: 'ubuntu2404');
        if (! $this->stacks->has($stackKey) || ! $this->stacks->supportsOs($os, $stackKey)) {
            Log::info('provisioning.auto_skip_unsupported_stack', [
                'order_id' => $order->id,
                'stack' => $stackKey,
                'os' => $os,
            ]);

            return;
        }

        $region = $order->datacenterRegion?->code
            ?: $this->quota->regionForLocation((string) $order->datacenter_location);

        if (! $region) {
            $this->hold($order, 'Region pesanan tidak dikenali untuk provisioning otomatis.');

            return;
        }

        if (! $this->quota->reserveHard($region, (string) $spec->upstream_bundle_type)) {
            // D4: pool full → hold paid order + alert. No silent auto-fallback.
            Log::warning('provisioning.auto_hold_pool_full', [
                'order_id' => $order->id,
                'region' => $region,
                'bundle_type' => $spec->upstream_bundle_type,
            ]);
            $this->alerts->alert('upstream.quota_hold', [
                'title' => 'Auto-provisioning ditahan: pool penuh',
                'order_id' => $order->id,
                'details' => [
                    'Order' => "#{$order->id} ({$spec->name})",
                    'Pool' => "{$region} / {$spec->upstream_bundle_type}",
                    'Status' => 'Ditahan di paid (tidak ada fallback region)',
                ],
                'body' => "Order #{$order->id} ({$spec->name}) sudah LUNAS tetapi pool {$region}/{$spec->upstream_bundle_type} penuh. "
                    .'Order ditahan di status paid — tidak ada fallback otomatis (D4). '
                    .'Mohon koordinasi dengan pelanggan: tambah kapasitas, pindah region (persetujuan pelanggan), atau refund.',
                'action_url' => url('/admin/monitoring?tab=upstream'),
            ], dedupeKey: 'quota-hold-' . $order->id, cooldownSeconds: 86400);

            return;
        }

        try {
            $task = DB::transaction(function () use ($order, $spec, $region) {
                /** @var Order $locked */
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                if ($locked->status !== 'paid') {
                    // Admin (or another path) already moved the order — abort.
                    throw new \RuntimeException('Order no longer paid; auto-provision aborted.');
                }

                app(OrderStateMachine::class)->transition($locked, 'provisioning', [
                    'reason' => 'Auto-provisioning dimulai (canary).',
                    'actor_type' => 'system',
                    'onLocked' => function (Order $o) {
                        $o->provisioning_attempts = ($o->provisioning_attempts ?? 0) + 1;
                        $o->failure_reason = null;
                        $o->save();
                    },
                ]);

                $instance = $this->createInstance($locked, $region);

                return ProvisioningTask::create([
                    'order_id' => $locked->id,
                    'vps_instance_id' => $instance->id,
                    'kind' => 'provision',
                    'status' => 'pending',
                    'provider' => 'tencent',
                    'progress' => 0,
                    'current_step' => 'Menunggu giliran provisioning',
                    'input' => [
                        'stack' => (string) ($locked->control_panel ?: 'none'),
                        'os' => (string) ($locked->os ?: 'ubuntu2404'),
                        'region' => $region,
                        'quota_reserved' => true,
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            // Reservation taken but dispatch setup failed → free the slot,
            // leave the order in the manual flow, alert admin.
            $this->quota->releaseReserved($region, (string) $spec->upstream_bundle_type);
            Log::error('provisioning.auto_dispatch_failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            $this->alerts->alert('provisioning.failed', [
                'title' => 'Auto-provisioning gagal dimulai',
                'order_id' => $order->id,
                'details' => [
                    'Order' => "#{$order->id}",
                    'Error' => \Illuminate\Support\Str::limit($e->getMessage(), 160),
                ],
                'body' => "Order #{$order->id}: reservasi kuota sudah dilepas, order kembali ke alur manual. Error: {$e->getMessage()}",
                'action_url' => url('/admin/monitoring?tab=tasks&status=failed'),
            ], dedupeKey: 'auto-dispatch-fail-' . $order->id, cooldownSeconds: 3600);

            return;
        }

        ProvisionVpsJob::dispatch($task->id);

        Log::info('provisioning.auto_dispatched', [
            'order_id' => $order->id,
            'task_id' => $task->id,
            'region' => $region,
        ]);
    }

    public function retryFailedOrder(Order $order): ProvisioningTask
    {
        $spec = $order->vpsSpec;
        if (! $spec || ! $this->gate->allows($spec)) {
            throw new \RuntimeException('Order ini tidak memenuhi syarat auto-provisioning (gate OFF / spec tidak terpetakan).');
        }

        $region = $order->datacenterRegion?->code
            ?: $this->quota->regionForLocation((string) $order->datacenter_location);

        if (! $region) {
            throw new \RuntimeException('Region upstream pesanan tidak dikenali.');
        }

        $existingInstance = $order->vpsInstance;

        // If VM already exists upstream (e.g. failed at stack installation stage), retry InstallStackJob only.
        if ($existingInstance && $existingInstance->provider_resource_id) {
            $task = DB::transaction(function () use ($order, $existingInstance) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'failed') {
                    app(OrderStateMachine::class)->transition($locked, 'provisioning', [
                        'reason' => 'Retry instalasi stack oleh Admin.',
                        'actor_user_id' => auth()->id(),
                        'actor_type' => 'admin',
                        'onLocked' => function (Order $o) {
                            $o->provisioning_attempts = ($o->provisioning_attempts ?? 0) + 1;
                            $o->failure_reason = null;
                            $o->save();
                        },
                    ]);
                }

                app(VpsStateMachine::class)->transition($existingInstance, 'provisioning', [
                    'reason' => 'Retry instalasi stack ulang.',
                    'actor_type' => 'admin',
                    'allowSame' => true,
                ]);

                return ProvisioningTask::create([
                    'order_id' => $locked->id,
                    'vps_instance_id' => $existingInstance->id,
                    'kind' => 'install_stack',
                    'status' => 'pending',
                    'provider' => 'tencent',
                    'progress' => 65,
                    'current_step' => 'Menjalankan ulang instalasi stack',
                    'actor_user_id' => auth()->id(),
                    'input' => [
                        'stack' => (string) ($locked->control_panel ?: 'none'),
                    ],
                ]);
            });

            \App\Jobs\Provisioning\InstallStackJob::dispatch($task->id);

            return $task;
        }

        // VM not yet created upstream → reserve pool slot and dispatch ProvisionVpsJob.
        if (! $this->quota->reserveHard($region, (string) $spec->upstream_bundle_type)) {
            throw new \RuntimeException("Pool kuota region {$region} ({$spec->upstream_bundle_type}) sedang penuh.");
        }

        try {
            $task = DB::transaction(function () use ($order, $existingInstance, $region) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'failed') {
                    app(OrderStateMachine::class)->transition($locked, 'provisioning', [
                        'reason' => 'Retry auto-provisioning oleh Admin.',
                        'actor_user_id' => auth()->id(),
                        'actor_type' => 'admin',
                        'onLocked' => function (Order $o) {
                            $o->provisioning_attempts = ($o->provisioning_attempts ?? 0) + 1;
                            $o->failure_reason = null;
                            $o->save();
                        },
                    ]);
                }

                $instance = $existingInstance ?: $this->createInstance($locked, $region);
                if ($existingInstance && $existingInstance->status !== 'provisioning') {
                    app(VpsStateMachine::class)->transition($existingInstance, 'provisioning', [
                        'reason' => 'Retry auto-provisioning ulang.',
                        'actor_type' => 'admin',
                        'allowSame' => true,
                    ]);
                }

                return ProvisioningTask::create([
                    'order_id' => $locked->id,
                    'vps_instance_id' => $instance->id,
                    'kind' => 'provision',
                    'status' => 'pending',
                    'provider' => 'tencent',
                    'progress' => 0,
                    'current_step' => 'Menunggu retry provisioning',
                    'actor_user_id' => auth()->id(),
                    'input' => [
                        'stack' => (string) ($locked->control_panel ?: 'none'),
                        'os' => (string) ($locked->os ?: 'ubuntu2404'),
                        'region' => $region,
                        'quota_reserved' => true,
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            $this->quota->releaseReserved($region, (string) $spec->upstream_bundle_type);
            throw $e;
        }

        ProvisionVpsJob::dispatch($task->id);

        return $task;
    }

    private function createInstance(Order $order, string $region): VpsInstance
    {
        $spec = $order->vpsSpec;

        // Same date rules as the admin manual flow (SLA compensation included).
        $startsAt = $order->provisioningStartsAt();
        $months = match ($order->billing_cycle) {
            'quarterly' => 3,
            'semi_annual' => 6,
            'annual' => 12,
            default => 1,
        };
        $expiresAt = $startsAt->copy()->addMonths($months);
        $graceEndsAt = $expiresAt->copy()->addDays(app(RenewalService::class)->graceDaysForOrder($order));

        $regionModel = $order->datacenterRegion
            ?: DatacenterRegion::where('provider', 'tencent')->where('code', $region)->first();

        $instance = VpsInstance::create([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'organization_id' => $order->organization_id,
            'hostname' => $order->hostname ?: ('vps-'.$order->id),
            'ssh_port' => 22,
            'os' => $order->os ?: 'ubuntu2404',
            'datacenter_location' => $order->datacenter_location ?? 'indonesia',
            'datacenter_region_id' => $regionModel?->id,
            'status' => 'provisioning',
            'cpu' => $spec->cpu ?? 2,
            'ram' => $spec->ram ?? 4,
            'disk' => $spec->disk ?? 60,
            'control_panel' => $order->control_panel ?: 'none',
            'provider' => 'tencent', // internal only; customer label stays cravacloud
            'uptime_percent' => 99.98,
            'billing_cycle' => $order->billing_cycle ?? 'monthly',
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'grace_period_ends_at' => $graceEndsAt,
            'auto_renew' => true,
        ]);

        VpsStatusHistory::create([
            'vps_instance_id' => $instance->id,
            'from_status' => null,
            'to_status' => 'provisioning',
            'reason' => 'VPS instance dibuat (auto-provisioning).',
            'actor_type' => 'system',
            'metadata' => ['order_id' => $order->id],
            'created_at' => now(),
        ]);

        return $instance;
    }

    private function hold(Order $order, string $reason): void
    {
        Log::warning('provisioning.auto_hold', ['order_id' => $order->id, 'reason' => $reason]);
        $this->alerts->alert('provisioning.failed', [
            'title' => 'Auto-provisioning ditahan',
            'order_id' => $order->id,
            'details' => [
                'Order' => "#{$order->id}",
                'Alasan' => $reason,
            ],
            'body' => "Order #{$order->id} sudah lunas tetapi tidak bisa diproses otomatis: {$reason} Order tetap di alur manual.",
            'action_url' => url('/admin/orders?status=needs_provision'),
        ], dedupeKey: 'hold-' . $order->id, cooldownSeconds: 3600);
    }
}