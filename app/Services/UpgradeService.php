<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\TaxRate;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Jobs\Provisioning\UpgradeBundleJob;
use App\Services\Provisioning\Exceptions\QuotaExhaustedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5C — self-service bundle upgrade (prorata difference billing).
 *
 * The customer pays ONLY the sell-price difference between the target plan and
 * the current plan, prorated for the remaining days of the active period. The
 * upstream cost (HPP) difference is prorated the same way; the margin is the
 * sell difference minus the HPP difference. Nothing here mutates upstream —
 * the actual ModifyInstancesBundle call happens in UpgradeBundleJob AFTER the
 * difference invoice is paid (hooked via PaymentCompletionService).
 *
 * White-label: every customer-facing string uses the CravaCloud plan names;
 * upstream bundle ids / vendor names never leave the admin side.
 */
class UpgradeService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * Validate + price an upgrade. Returns a structured preview the controller
     * uses both for the preview response and (when ok) to create the invoice.
     *
     * @return array{
     *   ok: bool, code: ?string, error: ?string, replacement_spec_id: ?int,
     *   current_spec_id: ?int, target_spec_id: int, remaining_days: int,
     *   cycle_days: int, monthly_difference: float, prorata_amount: float,
     *   tax_amount: float, total: float, hpp_prorata: float, margin: float,
     *   currency: string
     * }
     */
    public function preview(VpsInstance $instance, VpsSpec $targetSpec): array
    {
        $currentSpec = $instance->order?->vpsSpec;
        $replacementId = $currentSpec?->replacement_spec_id;

        $base = [
            'ok' => false,
            'code' => null,
            'error' => null,
            'replacement_spec_id' => $replacementId,
            'current_spec_id' => $currentSpec?->id,
            'target_spec_id' => $targetSpec->id,
            'remaining_days' => 0,
            'cycle_days' => 0,
            'monthly_difference' => 0.0,
            'prorata_amount' => 0.0,
            'tax_amount' => 0.0,
            'total' => 0.0,
            'hpp_prorata' => 0.0,
            'margin' => 0.0,
            'currency' => 'IDR',
        ];

        if (! $currentSpec) {
            return array_merge($base, [
                'code' => 'no_current_spec',
                'error' => 'Paket server saat ini tidak dikenali. Hubungi support untuk upgrade.',
            ]);
        }

        if ($currentSpec->id === $targetSpec->id) {
            return array_merge($base, [
                'code' => 'same_spec',
                'error' => 'Server Anda sudah memakai paket ini.',
            ]);
        }

        if (! $targetSpec->hasUpstreamMapping()) {
            return array_merge($base, [
                'code' => 'no_mapping',
                'error' => 'Paket tujuan belum tersedia untuk upgrade otomatis. Hubungi support.',
            ]);
        }

        // Upgrade-only: every resource dimension must be >= current.
        if ((int) $targetSpec->cpu < (int) $currentSpec->cpu
            || (int) $targetSpec->ram < (int) $currentSpec->ram
            || (int) $targetSpec->disk < (int) $currentSpec->disk) {
            return array_merge($base, [
                'code' => 'downgrade',
                'error' => 'Downgrade tidak dapat dilakukan otomatis. '
                    .($replacementId
                        ? 'Gunakan paket pengganti yang direkomendasikan atau hubungi support.'
                        : 'Hubungi support untuk perubahan paket.'),
            ]);
        }

        if ($instance->status !== 'running') {
            return array_merge($base, [
                'code' => 'not_running',
                'error' => 'Upgrade hanya dapat dilakukan saat server aktif (running).',
            ]);
        }

        // Guard: tolak bila masih ada order upgrade yang belum selesai.
        if (Order::where('vps_instance_id', $instance->id)
            ->where('order_type', 'upgrade')
            ->whereIn('status', ['pending', 'provisioning'])
            ->exists()) {
            return array_merge($base, [
                'code' => 'upgrade_in_progress',
                'error' => 'Masih ada permintaan upgrade yang sedang diproses untuk server ini.',
            ]);
        }

        // Guard cross-pool: bila tipe bundle berbeda (Starter vs General), pastikan pool tujuan masih punya slot.
        if ($targetSpec->upstream_bundle_type && $currentSpec->upstream_bundle_type
            && $targetSpec->upstream_bundle_type !== $currentSpec->upstream_bundle_type) {
            $quota = app(UpstreamQuotaService::class);
            $region = $instance->datacenterRegion?->code
                ?: $quota->regionForLocation((string) ($instance->order?->datacenter_location ?? $instance->datacenter_location));

            if ($region) {
                try {
                    $quota->assertAvailable($region, (string) $targetSpec->upstream_bundle_type);
                } catch (QuotaExhaustedException $e) {
                    return array_merge($base, [
                        'code' => 'quota_exhausted',
                        'error' => $e->customerMessage(),
                    ]);
                }
            }
        }

        $monthlyDifference = $this->round((float) $targetSpec->sell_price - (float) $currentSpec->sell_price);
        if ($monthlyDifference <= 0) {
            return array_merge($base, [
                'code' => 'downgrade',
                'error' => 'Paket tujuan tidak lebih tinggi dari paket saat ini.',
            ]);
        }

        $cycleDays = $this->cycleDays((string) ($instance->billing_cycle ?: 'monthly'));
        $remainingDays = $instance->expires_at
            ? max(0, (int) floor(now()->diffInDays($instance->expires_at, false)))
            : $cycleDays;

        // Guard Rp0 prorata saat siklus sudah berakhir hari ini.
        if ($instance->expires_at && $remainingDays < 1) {
            return array_merge($base, [
                'code' => 'cycle_ending',
                'error' => 'Masa aktif server berakhir hari ini. Perpanjang layanan terlebih dahulu sebelum melakukan upgrade.',
            ]);
        }

        $factor = $cycleDays > 0 ? min(1.0, $remainingDays / $cycleDays) : 1.0;

        $prorataAmount = $this->round($monthlyDifference * $factor);

        $customer = $instance->customer;
        $taxRate = TaxRate::defaultForCountry($customer->country ?? 'ID');
        $taxRateValue = ($taxRate && $taxRate->is_active) ? (float) $taxRate->rate : 0.0;
        $taxAmount = $this->round($prorataAmount * $taxRateValue);
        $total = $this->round($prorataAmount + $taxAmount);

        // Upstream HPP difference (prorated) — admin-only margin computation.
        $rate = max(1, $this->settings->int('tencent_usd_idr', 16500));
        $currentUsd = (float) config('services.tencent.bundle_prices_usd.'.$currentSpec->upstream_bundle_id, 0);
        $targetUsd = (float) config('services.tencent.bundle_prices_usd.'.$targetSpec->upstream_bundle_id, 0);
        $usdDiff = max(0.0, $targetUsd - $currentUsd);
        $hppProrata = $this->round($usdDiff * $rate * $factor);
        $margin = $this->round($prorataAmount - $hppProrata);

        return array_merge($base, [
            'ok' => true,
            'remaining_days' => $remainingDays,
            'cycle_days' => $cycleDays,
            'monthly_difference' => $monthlyDifference,
            'prorata_amount' => $prorataAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'hpp_prorata' => $hppProrata,
            'margin' => $margin,
            'tax_rate' => $taxRateValue,
            'tax' => ($taxRate && $taxRate->is_active) ? [
                'id' => $taxRate->id,
                'code' => $taxRate->code,
                'name' => $taxRate->name,
                'rate' => $taxRateValue,
            ] : null,
            'current_spec_name' => $currentSpec->name,
            'target_spec_name' => $targetSpec->name,
        ]);
    }

    /**
     * Create the upgrade Order (order_type=upgrade) + prorata difference Invoice.
     * Throws InvalidArgumentException when the preview is not ok.
     *
     * @return array{order: Order, invoice: \App\Models\Invoice, preview: array}
     */
    public function createUpgradeOrder(VpsInstance $instance, VpsSpec $targetSpec): array
    {
        $preview = $this->preview($instance, $targetSpec);
        if (! $preview['ok']) {
            throw new \InvalidArgumentException($preview['error'] ?? 'Upgrade tidak dapat diproses.');
        }

        $currentSpec = $instance->order?->vpsSpec;

        return DB::transaction(function () use ($instance, $targetSpec, $preview, $currentSpec) {
            $order = Order::create([
                'customer_id' => $instance->customer_id,
                'organization_id' => $instance->organization_id,
                'vps_spec_id' => $targetSpec->id,
                'vps_instance_id' => $instance->id,
                'subscription_id' => $instance->subscription?->id,
                'control_panel' => $instance->control_panel ?: 'none',
                'datacenter_location' => $instance->datacenter_location ?: 'indonesia',
                'datacenter_region_id' => $instance->datacenter_region_id,
                'os' => $instance->os ?: 'ubuntu2404',
                'hostname' => $instance->hostname,
                'billing_cycle' => $instance->billing_cycle ?: 'monthly',
                'order_type' => 'upgrade',
                'status' => 'pending',
                'channel' => 'website',
                'payment_method' => 'midtrans_snap',
                'amount' => $preview['total'],
                'currency' => 'IDR',
                'setup_fee' => 0,
                'last_status_change_at' => now(),
                'metadata' => [
                    'upgrade' => [
                        'from_spec_id' => $currentSpec?->id,
                        'to_spec_id' => $targetSpec->id,
                        'remaining_days' => $preview['remaining_days'],
                        'cycle_days' => $preview['cycle_days'],
                        'prorata_amount' => $preview['prorata_amount'],
                        'hpp_prorata' => $preview['hpp_prorata'],
                        'margin' => $preview['margin'],
                    ],
                ],
            ]);

            $invoice = $this->billing->generateInvoice($order, $this->breakdown($preview), [
                'subscription_id' => $order->subscription_id,
                'period_start' => now(),
                'period_end' => $instance->expires_at,
            ]);

            return ['order' => $order, 'invoice' => $invoice, 'preview' => $preview];
        });
    }

    /**
     * Called by PaymentCompletionService once the upgrade difference invoice is
     * paid. Dispatches UpgradeBundleJob (idempotent per order). The upstream
     * ModifyInstancesBundle call only happens here — never at preview/checkout.
     */
    public function handleUpgradePayment(Order $order): void
    {
        if (! $order->isUpgrade() || ! $order->paid_at) {
            return;
        }

        $meta = $order->metadata ?? [];
        if (! empty($meta['upgrade_dispatched'])) {
            return;
        }
        if ($order->provisioningTasks()
            ->where('kind', 'upgrade')
            ->whereIn('status', ['pending', 'running', 'succeeded'])
            ->exists()) {
            return;
        }

        $instance = $order->vps_instance_id ? VpsInstance::find($order->vps_instance_id) : null;
        $targetSpec = $order->vpsSpec;
        if (! $instance || ! $targetSpec) {
            Log::error('upgrade.dispatch_missing_context', ['order_id' => $order->id]);

            return;
        }

        try {
            $task = DB::transaction(function () use ($order, $instance, $targetSpec) {
                /** @var Order $locked */
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                if (! empty(($locked->metadata ?? [])['upgrade_dispatched'])) {
                    throw new \RuntimeException('Upgrade already dispatched.');
                }

                app(OrderStateMachine::class)->transition($locked, 'provisioning', [
                    'reason' => 'Upgrade dimulai setelah invoice selisih lunas.',
                    'actor_type' => 'system',
                    'metadata' => ['vps_instance_id' => $instance->id, 'target_spec_id' => $targetSpec->id],
                    'onLocked' => function (Order $o) {
                        $o->metadata = array_merge($o->metadata ?? [], ['upgrade_dispatched' => true]);
                        $o->save();
                    },
                ]);

                return ProvisioningTask::create([
                    'order_id' => $locked->id,
                    'vps_instance_id' => $instance->id,
                    'kind' => 'upgrade',
                    'status' => 'pending',
                    'provider' => $instance->provider ?: 'tencent',
                    'progress' => 0,
                    'current_step' => 'Menunggu giliran upgrade',
                    'input' => [
                        'target_spec_id' => $targetSpec->id,
                        'from_spec_id' => $instance->order?->vps_spec_id,
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('upgrade.dispatch_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return;
        }

        UpgradeBundleJob::dispatch($task->id);

        Log::info('upgrade.dispatched', ['order_id' => $order->id, 'task_id' => $task->id]);
    }

    /** Build the BillingService breakdown for the prorata difference invoice. */
    private function breakdown(array $preview): array
    {
        $prorata = $preview['prorata_amount'];
        $taxAmount = $preview['tax_amount'];
        $taxInfo = $preview['tax'] ?? null;

        $items = [[
            'type' => 'service',
            'description' => "Upgrade {$preview['current_spec_name']} → {$preview['target_spec_name']} "
                ."(prorata {$preview['remaining_days']} hari)",
            'quantity' => 1,
            'unit_price' => $prorata,
            'subtotal' => $prorata,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => $prorata,
            'vps_spec_id' => $preview['target_spec_id'],
            'metadata' => [
                'kind' => 'upgrade_prorata',
                'remaining_days' => $preview['remaining_days'],
                'cycle_days' => $preview['cycle_days'],
            ],
        ]];

        if ($taxAmount > 0 && $taxInfo) {
            $items[] = [
                'type' => 'tax',
                'description' => $taxInfo['name'],
                'quantity' => 1,
                'unit_price' => $taxAmount,
                'subtotal' => $taxAmount,
                'tax_rate' => $preview['tax_rate'],
                'tax_amount' => $taxAmount,
                'total' => $taxAmount,
                'metadata' => ['kind' => 'tax'],
            ];
        }

        return [
            'base_monthly' => $preview['monthly_difference'],
            'months' => 1,
            'cycle_discount_percent' => 0,
            'gross' => $prorata,
            'cycle_discount' => 0.0,
            'coupon_discount' => 0.0,
            'subtotal_after_discount' => $prorata,
            'tax_rate' => $preview['tax_rate'],
            'tax_amount' => $taxAmount,
            'credit_available' => 0.0,
            'credit_applied' => 0.0,
            'total' => $preview['total'],
            'effective_monthly' => $preview['total'],
            'coupon' => null,
            'tax' => $taxInfo,
            'currency' => 'IDR',
            'items' => $items,
        ];
    }

    private function cycleDays(string $cycle): int
    {
        $months = BillingService::CYCLE_CONFIG[$cycle]['months'] ?? 1;

        return $months * 30;
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}