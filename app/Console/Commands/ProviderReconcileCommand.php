<?php

namespace App\Console\Commands;

use App\Models\ProvisioningTask;
use App\Models\SupplierPurchase;
use App\Models\UpstreamQuotaPool;
use App\Models\VpsInstance;
use App\Services\AdminAlertService;
use App\Services\Provisioning\ProviderManager;
use App\Services\Provisioning\Tencent\LighthouseClient;
use App\Services\UpstreamQuotaService;
use App\Services\VpsStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rekonsiliasi berkala: bandingkan status VPS di DB vs status di provider.
 * Kalau divergent, catat ke log dan (opsional) transisi ke status provider.
 *
 * Untuk provider 'manual', ini no-op (status() return status DB).
 *
 * Phase 4D extensions (tencent only, read-only + alert):
 *  - NO-OP total bila tidak ada instance non-manual (tidak ada kerja/API).
 *  - Sync public/private IP dari upstream (IP bisa berubah pasca recovery).
 *  - syncFromProvider(): koreksi kuota `used` + alert drift (dedupe harian).
 *  - Deteksi orphan `crava-*` di upstream yang tidak ada di DB → alert admin,
 *    JANGAN PERNAH auto-terminate (keputusan manusia).
 *  - Deteksi drift supplier_expires_at vs ExpiredTime upstream → alert.
 *  - Instance dengan task lifecycle live (provision/install/renew/destroy)
 *    dilewati supaya auto-correct tidak balapan dengan job.
 */
class ProviderReconcileCommand extends Command
{
    protected $signature = 'provider:reconcile
        {--limit=100 : Maks instance per run}
        {--auto-correct : Kalau divergent, transisi state DB ke status provider}
        {--dry-run}';

    protected $description = 'Rekonsiliasi status VPS antara DB dan provider.';

    public function __construct(
        protected ProviderManager $manager,
        protected VpsStateMachine $vpsStateMachine,
        protected UpstreamQuotaService $quota,
        protected LighthouseClient $lighthouse,
        protected AdminAlertService $alerts,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $autoCorrect = (bool) $this->option('auto-correct');
        $dry = (bool) $this->option('dry-run');

        $instances = VpsInstance::whereNotIn('status', ['terminated'])
            ->orderBy('last_reconciled_at', 'asc')
            ->limit($limit)
            ->get();

        // Phase 4D: tanpa instance non-manual tidak ada yang bisa di-query —
        // no-op penuh (deployment manual murni tidak memanggil API apa pun).
        $hasRemote = $instances->contains(fn (VpsInstance $i) => ($i->provider ?? 'manual') !== 'manual');
        if (! $hasRemote) {
            $this->info('Reconcile no-op: tidak ada instance non-manual.');

            return self::SUCCESS;
        }

        $divergent = 0; $reconciled = 0; $skipped = 0;
        $this->info("Cek {$instances->count()} instance.");

        foreach ($instances as $instance) {
            try {
                // Jangan balapan dengan job lifecycle yang sedang berjalan.
                if ($this->hasLiveLifecycleTask($instance)) {
                    $skipped++;
                    continue;
                }

                $provider = $this->manager->provider($instance->provider ?? 'manual');
                $result = $provider->status($instance);
                $providerStatus = $result['status'] ?? null;
                $meta = $result['meta'] ?? [];

                $updates = [
                    'last_reconciled_at' => now(),
                    'provider_meta' => array_merge($instance->provider_meta ?? [], [
                        'last_reconcile' => $meta,
                    ]),
                ];

                // Phase 4D: re-sync IP upstream (recovery bisa mengubah IP).
                if (($instance->provider ?? 'manual') === 'tencent') {
                    if (! empty($meta['public_ip']) && $meta['public_ip'] !== $instance->public_ip) {
                        $updates['public_ip'] = $meta['public_ip'];
                    }
                    if (! empty($meta['private_ip']) && $meta['private_ip'] !== $instance->private_ip) {
                        $updates['private_ip'] = $meta['private_ip'];
                    }
                }

                $instance->update($updates);

                if ($providerStatus && $providerStatus !== $instance->status) {
                    $divergent++;
                    $this->warn("  ! vps #{$instance->id} DB={$instance->status} provider={$providerStatus}");
                    Log::warning('reconcile.divergent', [
                        'vps_id' => $instance->id,
                        'db_status' => $instance->status,
                        'provider_status' => $providerStatus,
                    ]);

                    if ($autoCorrect && !$dry) {
                        try {
                            $this->vpsStateMachine->transition($instance, $providerStatus, [
                                'reason' => 'Reconcile auto-correct dari provider.',
                                'actor_type' => 'system',
                                'metadata' => ['source' => 'reconcile'],
                            ]);
                            $reconciled++;
                        } catch (\Throwable $e) {
                            Log::warning('reconcile.transition_failed', [
                                'vps_id' => $instance->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                // Phase 4D: drift supplier_expires_at vs ExpiredTime upstream.
                if (($instance->provider ?? 'manual') === 'tencent' && ! $dry) {
                    $this->checkExpiryDrift($instance, $meta['expired_time'] ?? null);
                }
            } catch (\Throwable $e) {
                Log::error('reconcile.error', ['vps_id' => $instance->id, 'error' => $e->getMessage()]);
            }
        }

        // Phase 4D: kuota `used` dikoreksi dari jumlah riil upstream
        // (read-only DescribeInstances; drift → alert dedupe harian).
        if (! $dry) {
            try {
                $this->quota->syncFromProvider();
            } catch (\Throwable $e) {
                Log::error('reconcile.quota_sync_failed', ['error' => $e->getMessage()]);
            }
        }

        // Phase 4D: orphan crava-* — alert saja, JANGAN auto-terminate.
        $orphans = $this->scanOrphans($dry);

        $this->info("Selesai. Divergent: {$divergent}, auto-corrected: {$reconciled}, skipped(live task): {$skipped}, orphan: {$orphans}.");
        return self::SUCCESS;
    }

    private function hasLiveLifecycleTask(VpsInstance $instance): bool
    {
        return ProvisioningTask::where('vps_instance_id', $instance->id)
            ->whereIn('kind', ['provision', 'install_stack', 'renew', 'destroy', 'stop', 'start'])
            ->whereIn('status', ['pending', 'running'])
            ->exists();
    }

    /**
     * Bandingkan supplier_expires_at pembelian terakhir dengan ExpiredTime
     * riil upstream. Toleransi 2 hari (pembulatan/timezone); lebih dari itu
     * dianggap drift → alert admin (tanpa tulis otomatis).
     */
    private function checkExpiryDrift(VpsInstance $instance, ?string $expiredTime): void
    {
        if (! $expiredTime) {
            return;
        }

        $purchase = SupplierPurchase::where('vps_instance_id', $instance->id)
            ->whereNotNull('supplier_expires_at')
            ->orderByDesc('supplier_expires_at')
            ->first();

        if (! $purchase) {
            return;
        }

        try {
            $upstream = Carbon::parse($expiredTime);
        } catch (\Throwable $e) {
            return;
        }

        $diffDays = abs($purchase->supplier_expires_at->diffInDays($upstream, false));
        if ($diffDays <= 2) {
            return;
        }

        Log::warning('reconcile.expiry_drift', [
            'vps_id' => $instance->id,
            'supplier_expires_at' => $purchase->supplier_expires_at->toIso8601String(),
            'upstream_expired_time' => $expiredTime,
        ]);

        $this->alertOnceDaily(
            "reconcile:expiry:{$instance->id}",
            'Drift masa aktif upstream',
            "VPS #{$instance->id} ({$instance->hostname}): supplier_expires_at tercatat "
            .$purchase->supplier_expires_at->format('d M Y H:i').", upstream {$upstream->format('d M Y H:i')} "
            ."selisih {$diffDays} hari. Periksa renewal/terminasi di console upstream."
        );
    }

    /**
     * Pindai semua region pool: instance upstream bernama crava-* yang TIDAK
     * ada di DB (provider_resource_id) = orphan. Alert admin per instance
     * (dedupe harian). TIDAK PERNAH auto-terminate — keputusan manusia.
     */
    private function scanOrphans(bool $dry): int
    {
        $known = array_flip(
            VpsInstance::whereNotNull('provider_resource_id')->pluck('provider_resource_id')->all()
        );

        $regions = UpstreamQuotaPool::distinct()->pluck('region_code')->all();
        $orphans = 0;

        foreach ($regions as $region) {
            $offset = 0;

            do {
                try {
                    $resp = $this->lighthouse->describeInstances($region, [], [], 100, $offset);
                } catch (\Throwable $e) {
                    Log::error('reconcile.orphan_scan_failed', ['region' => $region, 'error' => $e->getMessage()]);
                    break;
                }

                foreach ($resp['InstanceSet'] ?? [] as $remote) {
                    $id = (string) ($remote['InstanceId'] ?? '');
                    $name = (string) ($remote['InstanceName'] ?? '');
                    $state = strtoupper((string) ($remote['InstanceState'] ?? ''));

                    if ($id === '' || ! str_starts_with($name, 'crava-') || isset($known[$id]) || $state === 'TERMINATED') {
                        continue;
                    }

                    $orphans++;
                    $this->warn("  ! orphan upstream {$id} ({$name}, {$state}) tidak ada di DB — TIDAK di-terminate otomatis.");
                    Log::warning('reconcile.orphan', [
                        'region' => $region,
                        'upstream_id' => $id,
                        'name' => $name,
                        'state' => $state,
                    ]);

                    if (! $dry) {
                        $this->alertOnceDaily(
                            "reconcile:orphan:{$id}",
                            'Instance upstream yatim (crava-*)',
                            "Ditemukan instance {$id} bernama {$name} (region {$region}, state {$state}) yang tidak terdaftar di DB CravaCloud. "
                            .'Kemungkinan sisa provisioning gagal. VERIFIKASI manual di console upstream sebelum tindakan apa pun — tidak ada auto-terminate.'
                        );
                    }
                }

                $total = (int) ($resp['TotalCount'] ?? 0);
                $offset += 100;
            } while ($offset < $total);
        }

        return $orphans;
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
            Log::warning('reconcile.alert_failed', ['key' => $cacheKey, 'error' => $e->getMessage()]);
        }
    }
}