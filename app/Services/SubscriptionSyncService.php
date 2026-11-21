<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Single Source of Truth sinkronisasi tabel subscriptions <-> vps_instances (1:1).
 *
 * Memastikan setiap server yang aktif selalu memiliki entri subscription sehingga
 * cron billing SaaS (billing:renew, billing:enter-grace, billing:suspend-overdue,
 * billing:terminate-expired, billing:check-due) beroperasi pada layanan nyata.
 */
class SubscriptionSyncService
{
    public function ensureForInstance(VpsInstance $instance): ?Subscription
    {
        $instance->loadMissing(['order.vpsSpec', 'subscription']);

        $spec = $instance->renewal_spec ?? $instance->order?->vpsSpec;
        if (! $spec) {
            Log::warning('subscription_sync.missing_spec', ['vps_instance_id' => $instance->id]);

            return null;
        }

        $existing = $instance->subscription;
        $status = $this->mapInstanceStatus((string) $instance->status);
        $startsAt = $instance->starts_at ?? $instance->created_at ?? now();
        $expiresAt = $instance->expires_at ?? $startsAt->copy()->addMonth();

        $payload = [
            'customer_id' => $instance->customer_id,
            'organization_id' => $instance->organization_id,
            'vps_spec_id' => $spec->id,
            'order_id' => $instance->order_id,
            'status' => $status,
            'billing_cycle' => 'monthly',
            'unit_amount' => $instance->renewal_price > 0 ? $instance->renewal_price : (float) $spec->sell_price,
            'currency' => 'IDR',
            'auto_renew' => $existing ? $existing->auto_renew : (bool) ($instance->auto_renew ?? true),
            'current_period_start' => $startsAt,
            'current_period_end' => $expiresAt,
            'next_billing_at' => $expiresAt,
            'grace_period_ends_at' => $instance->grace_period_ends_at,
        ];

        if ($status === 'terminated') {
            $payload['ended_at'] = $existing?->ended_at ?? now();
        }

        return Subscription::updateOrCreate(
            ['vps_instance_id' => $instance->id],
            $payload
        );
    }

    public function syncFromRenewal(VpsInstance $instance, Carbon $periodStart, Carbon $newExpiresAt): ?Subscription
    {
        $sub = $this->ensureForInstance($instance);
        if (! $sub) {
            return null;
        }

        $spec = $instance->renewal_spec ?? $instance->order?->vpsSpec;
        $sub->update([
            'status' => 'active',
            'vps_spec_id' => $spec?->id ?? $sub->vps_spec_id,
            'unit_amount' => $instance->renewal_price > 0 ? $instance->renewal_price : $sub->unit_amount,
            'billing_cycle' => 'monthly',
            'current_period_start' => $periodStart,
            'current_period_end' => $newExpiresAt,
            'next_billing_at' => $newExpiresAt,
            'grace_period_ends_at' => null,
            'renewal_failures' => 0,
            'last_renewal_error' => null,
        ]);

        return $sub->fresh();
    }

    public function syncFromUpgrade(VpsInstance $instance, VpsSpec $targetSpec): ?Subscription
    {
        $sub = $this->ensureForInstance($instance);
        if (! $sub) {
            return null;
        }

        $sub->update([
            'vps_spec_id' => $targetSpec->id,
            'unit_amount' => $instance->renewal_price > 0 ? $instance->renewal_price : (float) $targetSpec->sell_price,
            'billing_cycle' => 'monthly',
        ]);

        return $sub->fresh();
    }

    private function mapInstanceStatus(string $instanceStatus): string
    {
        return match ($instanceStatus) {
            'suspended' => 'suspended',
            'terminated' => 'terminated',
            default => 'active',
        };
    }
}
