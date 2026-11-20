<?php

namespace App\Console\Commands;

use App\Services\UpstreamQuotaService;
use Illuminate\Console\Command;

/**
 * Read-only quota reconciliation: corrects upstream_quota_pools.used from
 * real DescribeInstances counts (Rp0). Scheduled every 15 minutes; also
 * runnable manually. Alerts admin on drift (deduped daily).
 */
class TencentSyncQuotaCommand extends Command
{
    protected $signature = 'tencent:sync-quota {--region= : Sync satu region saja (default: semua region terdaftar)}';

    protected $description = 'Sinkronkan kuota pool upstream dengan jumlah instance riil (read-only)';

    public function handle(UpstreamQuotaService $quota): int
    {
        $region = $this->option('region') ?: null;

        try {
            $summary = $quota->syncFromProvider($region);
        } catch (\Throwable $e) {
            $this->error('Sync gagal: '.mb_substr($e->getMessage(), 0, 300));

            return self::FAILURE;
        }

        if ($summary === []) {
            $this->warn('Tidak ada pool terdaftar untuk region yang diminta.');

            return self::SUCCESS;
        }

        $this->table(
            ['Region', 'Bundle Type', 'Used (DB)', 'Used (riil)', 'Drift'],
            array_map(fn ($r) => [
                $r['region'],
                $r['bundle_type'],
                $r['used_before'],
                $r['used_real'],
                $r['drift'] ? 'YA (dikoreksi)' : 'tidak',
            ], $summary),
        );

        $this->info('Sync selesai — '.count($summary).' pool diperiksa.');

        return self::SUCCESS;
    }
}