<?php

namespace App\Services;

use App\Models\DatacenterRegion;
use App\Models\Order;
use App\Models\UpstreamQuotaPool;
use App\Models\VpsSpec;
use App\Services\Provisioning\Exceptions\PendingOrderLimitException;
use App\Services\Provisioning\Exceptions\QuotaExhaustedException;
use App\Services\Provisioning\Tencent\LighthouseClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Quota Pool Guard (blueprint §2.4, revision 1 anti-DoS).
 *
 * Lifecycle of a slot:
 *   checkout (pending) → NO reservation. Only assertAvailable() + max-2
 *                        pending orders per user. Pending TTL (24h) must
 *                        never hostage a slot.
 *   paid               → reserveHard() atomic conditional UPDATE.
 *                        affected-rows 0 = pool full → hold order + alert
 *                        admin (D4: no silent auto-fallback).
 *   provision success  → commit(): reserved-1, used+1.
 *   permanent failure  → releaseReserved(): reserved-1.
 *   terminate success  → releaseUsed(): used-1.
 *   reconcile          → syncFromProvider(): correct `used` from real
 *                        DescribeInstances counts, alert on drift.
 *
 * Guard mode (setting quota_guard_mode):
 *   observe (Phase 2 default) — evaluate + log, never reject checkout;
 *   enforce                   — revision 1 rejection behavior.
 */
class UpstreamQuotaService
{
    /** orders.datacenter_location → upstream region (blueprint §2.4). */
    public const LOCATION_REGION = [
        'indonesia' => 'ap-jakarta',
        'singapore' => 'ap-singapore',
    ];

    public const MAX_PENDING_PER_USER = 2;

    /** Instance states that still occupy an upstream slot. */
    private const LIVE_STATES = [
        'PENDING', 'STARTING', 'RUNNING', 'REBOOTING', 'STOPPING',
        'STOPPED', 'SHUTDOWN', 'RECOVERING', 'ISOLATING',
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly AdminAlertService $alerts,
        private readonly LighthouseClient $lighthouse,
    ) {
    }

    public function guardMode(): string
    {
        $mode = (string) $this->settings->get('quota_guard_mode', 'observe');

        return in_array($mode, ['observe', 'enforce'], true) ? $mode : 'observe';
    }

    public function regionForLocation(string $location): ?string
    {
        return self::LOCATION_REGION[$location] ?? null;
    }

    public function pool(string $regionCode, string $bundleType): ?UpstreamQuotaPool
    {
        return UpstreamQuotaPool::where('region_code', $regionCode)
            ->where('bundle_type', $bundleType)
            ->first();
    }

    /**
     * @throws QuotaExhaustedException when the pool is missing or full.
     */
    public function assertAvailable(string $regionCode, string $bundleType): void
    {
        $pool = $this->pool($regionCode, $bundleType);

        if (! $pool || $pool->availableSlots() < 1) {
            throw new QuotaExhaustedException(
                $regionCode,
                $bundleType,
                $this->alternativeRegions($bundleType, $regionCode),
            );
        }
    }

    /**
     * @throws PendingOrderLimitException when the user already holds >= max pending orders.
     */
    public function assertPendingLimit(int $userId, int $max = self::MAX_PENDING_PER_USER): void
    {
        $pending = Order::where('customer_id', $userId)
            ->where('status', 'pending')
            ->where('order_type', '!=', 'renewal')
            ->count();

        if ($pending >= $max) {
            throw new PendingOrderLimitException($max);
        }
    }

    /**
     * Checkout guard for NEW orders of mapped specs.
     *
     * $canReject=false (marketplace channels: Shopee/Lynk — payment already
     * captured externally) always behaves as observe: log + alert, never
     * block order recording. The hard enforcement point for those channels
     * is reserveHard() at `paid`.
     *
     * @throws QuotaExhaustedException|PendingOrderLimitException only in enforce mode with $canReject=true.
     */
    public function guardCheckout(VpsSpec $spec, string $location, ?int $userId, bool $canReject = true): void
    {
        if (! $spec->hasUpstreamMapping()) {
            return; // unmapped (ai_combo/managed_db HOLD, D3) → manual flow, no guard
        }

        $region = $this->regionForLocation($location);
        if (! $region) {
            return;
        }

        $enforce = $canReject && $this->guardMode() === 'enforce';
        $problems = [];

        try {
            $this->assertAvailable($region, $spec->upstream_bundle_type);
        } catch (QuotaExhaustedException $e) {
            $problems[] = 'pool_full';
            if ($enforce) {
                throw $e;
            }
        }

        if ($userId) {
            try {
                $this->assertPendingLimit($userId);
            } catch (PendingOrderLimitException $e) {
                $problems[] = 'pending_limit';
                if ($enforce) {
                    throw $e;
                }
            }
        }

        if ($problems !== []) {
            Log::warning('quota.checkout_observe', [
                'spec_id' => $spec->id,
                'region' => $region,
                'bundle_type' => $spec->upstream_bundle_type,
                'user_id' => $userId,
                'problems' => $problems,
                'mode' => $this->guardMode(),
                'can_reject' => $canReject,
            ]);
        }
    }

    /**
     * Atomic hard reservation at `paid`. Returns false when the pool is full
     * (caller holds the order + alerts admin; never auto-fallback — D4).
     */
    public function reserveHard(string $regionCode, string $bundleType): bool
    {
        $affected = DB::table('upstream_quota_pools')
            ->where('region_code', $regionCode)
            ->where('bundle_type', $bundleType)
            ->whereRaw('reserved + used < capacity')
            ->update([
                'reserved' => DB::raw('reserved + 1'),
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            $this->warnIfHighUsage($regionCode, $bundleType);
        }

        return $affected > 0;
    }

    /** Provisioning succeeded: reserved → used. */
    public function commit(string $regionCode, string $bundleType): void
    {
        DB::table('upstream_quota_pools')
            ->where('region_code', $regionCode)
            ->where('bundle_type', $bundleType)
            ->update([
                'reserved' => DB::raw('CASE WHEN reserved > 0 THEN reserved - 1 ELSE 0 END'),
                'used' => DB::raw('used + 1'),
                'updated_at' => now(),
            ]);

        $this->warnIfHighUsage($regionCode, $bundleType);
    }

    /** Permanent provisioning failure / cancelled-after-paid: free the reservation. */
    public function releaseReserved(string $regionCode, string $bundleType): void
    {
        DB::table('upstream_quota_pools')
            ->where('region_code', $regionCode)
            ->where('bundle_type', $bundleType)
            ->update([
                'reserved' => DB::raw('CASE WHEN reserved > 0 THEN reserved - 1 ELSE 0 END'),
                'updated_at' => now(),
            ]);
    }

    /** Terminate succeeded upstream: free the used slot. */
    public function releaseUsed(string $regionCode, string $bundleType): void
    {
        DB::table('upstream_quota_pools')
            ->where('region_code', $regionCode)
            ->where('bundle_type', $bundleType)
            ->update([
                'used' => DB::raw('CASE WHEN used > 0 THEN used - 1 ELSE 0 END'),
                'updated_at' => now(),
            ]);
    }

    /**
     * Correct `used` from real upstream instance counts (read-only API).
     * Returns per-pool summary; alerts admin on drift.
     *
     * @return array<int, array{region: string, bundle_type: string, used_before: int, used_real: int, drift: bool}>
     */
    public function syncFromProvider(?string $regionCode = null): array
    {
        $regions = $regionCode
            ? [$regionCode]
            : UpstreamQuotaPool::distinct()->pluck('region_code')->all();

        $summary = [];

        foreach ($regions as $region) {
            $counts = ['STARTER_BUNDLE' => 0, 'GENERAL_BUNDLE' => 0];
            $offset = 0;

            do {
                $resp = $this->lighthouse->describeInstances($region, [], [], 100, $offset);
                foreach ($resp['InstanceSet'] ?? [] as $instance) {
                    $state = strtoupper((string) ($instance['InstanceState'] ?? ''));
                    if (! in_array($state, self::LIVE_STATES, true)) {
                        continue;
                    }
                    $type = $this->bundleTypeOf((string) ($instance['BundleId'] ?? ''));
                    if ($type === null) {
                        Log::warning('quota.sync_unknown_bundle', [
                            'region' => $region,
                            'instance_id' => $instance['InstanceId'] ?? null,
                            'bundle_id' => $instance['BundleId'] ?? null,
                        ]);
                        continue;
                    }
                    $counts[$type]++;
                }
                $total = (int) ($resp['TotalCount'] ?? 0);
                $offset += 100;
            } while ($offset < $total);

            foreach ($counts as $type => $real) {
                $pool = $this->pool($region, $type);
                if (! $pool) {
                    continue;
                }

                $before = $pool->used;
                $drift = $before !== $real;

                if ($drift) {
                    $pool->update(['used' => $real]);
                    Log::warning('quota.sync_drift', [
                        'region' => $region,
                        'bundle_type' => $type,
                        'used_before' => $before,
                        'used_real' => $real,
                    ]);
                    $this->alertOnceDaily(
                        "quota:drift:{$region}:{$type}",
                        'Kuota upstream tidak sinkron',
                        "Pool {$region}/{$type}: tercatat {$before}, riil {$real}. Kolom used sudah dikoreksi otomatis. Mohon cek instance yatim/drift di console upstream.",
                    );
                }

                $summary[] = [
                    'region' => $region,
                    'bundle_type' => $type,
                    'used_before' => $before,
                    'used_real' => $real,
                    'drift' => $drift,
                ];

                $this->warnIfHighUsage($region, $type);
            }
        }

        return $summary;
    }

    /**
     * Active alternative regions with free slots for the same bundle family
     * (D4: offered as an explicit customer choice, never auto-applied).
     *
     * @return array<int, array{region_code: string, label: string, available: int}>
     */
    public function alternativeRegions(string $bundleType, string $excludeRegion): array
    {
        $regions = DatacenterRegion::where('provider', 'tencent')
            ->where('is_active', true)
            ->where('code', '!=', $excludeRegion)
            ->get();

        $out = [];
        foreach ($regions as $region) {
            $pool = $this->pool($region->code, $bundleType);
            if ($pool && $pool->availableSlots() > 0) {
                $out[] = [
                    'region_code' => $region->code,
                    'label' => $region->name, // neutral DC name, no vendor
                    'available' => $pool->availableSlots(),
                ];
            }
        }

        return $out;
    }

    private function bundleTypeOf(string $bundleId): ?string
    {
        return match (true) {
            str_starts_with($bundleId, 'bundle_starter_') => 'STARTER_BUNDLE',
            str_starts_with($bundleId, 'bundle_gen_') => 'GENERAL_BUNDLE',
            default => null,
        };
    }

    private function warnIfHighUsage(string $regionCode, string $bundleType): void
    {
        $pool = $this->pool($regionCode, $bundleType);
        if (! $pool) {
            return;
        }

        $pct = (int) config('services.tencent.quota_warn_pct', 80);
        if ($pool->usagePercent() < $pct) {
            return;
        }

        $this->alertOnceDaily(
            "quota:warn:{$regionCode}:{$bundleType}",
            'Kuota upstream hampir penuh',
            "Pool {$regionCode}/{$bundleType} terpakai {$pool->usagePercent()}% ({$pool->reserved} reserved + {$pool->used} used dari {$pool->capacity}). Segera tambah kapasitas atau siapkan region alternatif.",
        );
    }

    private function alertOnceDaily(string $cacheKey, string $subject, string $text): void
    {
        $key = $cacheKey.':'.now()->format('Ymd');
        if (! Cache::add($key, true, now()->addDay())) {
            return; // already alerted today
        }

        try {
            $this->alerts->digest($subject, $text);
        } catch (\Throwable $e) {
            Log::warning('quota.alert_failed', ['key' => $cacheKey, 'error' => $e->getMessage()]);
        }
    }
}