<?php

namespace App\Console\Commands;

use App\Services\Provisioning\Tencent\BillingClient;
use App\Services\Provisioning\Tencent\LighthouseClient;
use App\Services\Provisioning\Tencent\TatClient;
use App\Services\Provisioning\Tencent\Tc3Signer;
use App\Services\Provisioning\Tencent\TencentErrorMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 sanity probe for the Tencent Cloud integration.
 *
 *   php artisan tencent:probe            → dry-run (offline checks only)
 *   php artisan tencent:probe --live     → read-only live API checks (Rp0)
 *
 * SECURITY: never prints SecretId/SecretKey values — only SET/MISSING.
 * LIVE mode only calls Describe* actions; it never creates/modifies anything.
 */
class TencentProbeCommand extends Command
{
    protected $signature = 'tencent:probe
        {--live : Jalankan cek API live read-only (Describe* saja, Rp0)}
        {--region= : Override region (default: TENCENT_REGION / ap-jakarta)}';

    protected $description = 'Probe kesiapan integrasi Tencent Cloud (dry-run / live read-only)';

    private array $results = [];

    public function handle(): int
    {
        $live = (bool) $this->option('live');
        $region = $this->option('region') ?: (string) config('services.tencent.region', 'ap-jakarta');

        $this->info($live
            ? "== Tencent Probe — LIVE READ-ONLY (region: {$region}) =="
            : '== Tencent Probe — DRY RUN (tanpa panggilan API) ==');
        $this->newLine();

        $this->checkCredentials();
        $this->checkConfig();
        $this->checkSigner();
        $this->checkDatabase();
        $this->checkQueue();

        if ($live) {
            if (! $this->credentialsPresent()) {
                $this->row('live', 'SKIP', 'Kredensial tidak lengkap — mode live dilewati.');
            } else {
                $this->probeLive($region);
            }
        }

        $this->newLine();
        $this->table(['Check', 'Status', 'Detail'], $this->results);

        $failed = collect($this->results)->contains(fn ($r) => $r[1] === 'FAIL');
        $this->newLine();
        $this->line($failed ? '<fg=red>HASIL: FAILURE — ada cek yang gagal.</>' : '<fg=green>HASIL: PASS — semua cek lolos.</>');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function credentialsPresent(): bool
    {
        return filled(config('services.tencent.secret_id'))
            && filled(config('services.tencent.secret_key'));
    }

    private function checkCredentials(): void
    {
        // NEVER print the values — presence only.
        $id = filled(config('services.tencent.secret_id')) ? 'SET' : 'MISSING';
        $key = filled(config('services.tencent.secret_key')) ? 'SET' : 'MISSING';
        $this->row('credentials', ($id === 'SET' && $key === 'SET') ? 'PASS' : 'WARN',
            "TENCENT_SECRET_ID={$id}, TENCENT_SECRET_KEY={$key} (nilai tidak pernah ditampilkan)");
    }

    private function checkConfig(): void
    {
        $cfg = config('services.tencent');
        foreach (['lighthouse', 'tat', 'billing'] as $service) {
            $host = $cfg['endpoints'][$service] ?? null;
            $version = $cfg['versions'][$service] ?? null;
            $ok = filled($host) && filled($version);
            $this->row("config.{$service}", $ok ? 'PASS' : 'FAIL',
                $ok ? "{$host} v{$version}" : 'endpoint/version tidak dikonfigurasi');
        }

        $blueprints = (array) ($cfg['blueprints'] ?? []);
        $expected = [
            'ubuntu2404', 'ubuntu2404_docker', 'ubuntu2204', 'ubuntu2604', 'ubuntu2004',
            'debian13', 'debian13_docker', 'debian12', 'debian11',
            'rocky10', 'rocky94', 'centos_stream9', 'centos_stream9_docker',
        ];
        $missing = array_diff($expected, array_keys(array_filter($blueprints)));
        $this->row('config.blueprints', empty($missing) ? 'PASS' : 'FAIL',
            empty($missing)
                ? count($blueprints).' blueprint terkonfigurasi: '.implode(', ', array_keys($blueprints))
                : 'Blueprint hilang: '.implode(', ', $missing));

        $regionless = (array) ($cfg['regionless_services'] ?? []);
        $this->row('config.regionless', in_array('billing', $regionless) ? 'PASS' : 'FAIL',
            'billing tanpa X-TC-Region: '.(in_array('billing', $regionless) ? 'ya' : 'TIDAK'));
    }

    private function checkSigner(): void
    {
        // Known-answer test against the official TC3-HMAC-SHA256 canonical vector
        // with non-secret fixture keys (avoids AKID... secret-scanning false positives).
        $signer = new Tc3Signer('test-secret-id', 'test-secret-key');
        $payload = '{"Limit": 1, "Filters": [{"Values": ["\u672a\u547d\u540d"], "Name": "instance-name"}]}';
        $expectedCanonical = '7019a55be8395899b900fb5564e4200d984910f34794a27cb3fb7d10ff6a1e84';
        $expectedSignature = '99f8790e445f249f002afeffee9d8517ca11199c00cc10167fa4a443ba2c1d5b';

        $canonical = $signer->canonicalRequestHash('cvm.tencentcloudapi.com', 'DescribeInstances', $payload);
        $this->row('signer.canonical_hash', $canonical === $expectedCanonical ? 'PASS' : 'FAIL',
            $canonical === $expectedCanonical ? 'cocok dengan vektor resmi TC3' : 'TIDAK cocok dengan vektor resmi');

        $signed = $signer->sign('cvm', 'cvm.tencentcloudapi.com', 'DescribeInstances', $payload, 1551113065);
        $this->row('signer.signature', ($signed['signature'] ?? '') === $expectedSignature ? 'PASS' : 'FAIL',
            ($signed['signature'] ?? '') === $expectedSignature
                ? 'signature TC3 tereproduksi (fixture key non-rahasia)'
                : 'signature TIDAK cocok dengan fixture TC3');

        $this->row('error_mapper', TencentErrorMapper::isRetryable('RequestLimitExceeded')
            && ! TencentErrorMapper::isRetryable('InsufficientBalance')
            && ! TencentErrorMapper::isRetryable('SomeUnknownCode') ? 'PASS' : 'FAIL',
            'retryable: RequestLimitExceeded; non-retryable: InsufficientBalance & unknown (konservatif)');
    }

    private function checkDatabase(): void
    {
        try {
            $count = DB::table('vps_specs')->where('is_active', true)->where('category', 'vps')->count();
            $this->row('db.vps_specs', 'PASS', "{$count} paket Cloud VPS aktif (kandidat auto-provision: 8)");
        } catch (\Throwable $e) {
            $this->row('db.vps_specs', 'WARN', 'DB tidak terjangkau dari probe: '.$e->getMessage());
        }
    }

    private function checkQueue(): void
    {
        $driver = config('queue.default');
        $this->row('queue.driver', 'PASS',
            "driver={$driver}".($driver !== 'redis' ? ' (D7: wajib pindah Redis+Supervisor sebelum canary Phase 3)' : ''));
    }

    private function probeLive(string $region): void
    {
        /** @var LighthouseClient $lh */
        $lh = app(LighthouseClient::class);
        /** @var TatClient $tat */
        $tat = app(TatClient::class);
        /** @var BillingClient $billing */
        $billing = app(BillingClient::class);

        // 1) Regions — verify ap-jakarta available.
        try {
            $resp = $lh->describeRegions();
            $regions = collect($resp['RegionSet'] ?? [])->pluck('Region')->all();
            $ok = in_array($region, $regions);
            $this->row('live.DescribeRegions', $ok ? 'PASS' : 'FAIL',
                $ok ? "{$region} tersedia (".count($regions).' region total)' : "{$region} TIDAK ada di RegionSet");
        } catch (\Throwable $e) {
            $this->row('live.DescribeRegions', 'FAIL', $this->safeError($e));
        }

        // 2) Bundles — verify the 8 mapped bundle IDs exist in the region.
        $expectedBundles = [
            'bundle_starter_nmc_lin_med2_01', 'bundle_starter_nmc_lin_med4_01',
            'bundle_starter_nmc_lin_med8_01', 'bundle_starter_nmc_lin_med8_02',
            'bundle_gen_nmc_lin_lg8_01', 'bundle_gen_nmc_lin_lg16_01',
            'bundle_gen_nmc_lin_2xlg16_01', 'bundle_gen_nmc_lin_2xlg32_01',
        ];
        try {
            $found = [];
            // bundle-id filter rejects too many Values per request
            // (InvalidParameter.FilterValueLimitExceeded) — chunk by 5.
            foreach (array_chunk($expectedBundles, 5) as $chunk) {
                $resp = $lh->describeBundles($region, [
                    ['Name' => 'bundle-id', 'Values' => $chunk],
                ], 100);
                foreach ($resp['BundleSet'] ?? [] as $b) {
                    $found[$b['BundleId']] = ($b['Price']['InstancePrice']['OriginalBundlePrice'] ?? $b['Price']['InstancePrice']['DiscountPrice'] ?? '?');
                }
            }
            $missing = array_diff($expectedBundles, array_keys($found));
            $this->row('live.DescribeBundles', empty($missing) ? 'PASS' : 'FAIL',
                empty($missing)
                    ? '8/8 bundle terverifikasi: '.implode(', ', array_map(fn ($id, $p) => "{$id}={$p}", array_keys($found), $found))
                    : 'Bundle hilang: '.implode(', ', $missing));
        } catch (\Throwable $e) {
            // Some accounts restrict bundle-id filters; fall back to unfiltered paging.
            try {
                $found = [];
                $offset = 0;
                do {
                    $resp = $lh->describeBundles($region, [], 100, $offset);
                    foreach ($resp['BundleSet'] ?? [] as $b) {
                        $found[$b['BundleId']] = true;
                    }
                    $total = (int) ($resp['TotalCount'] ?? 0);
                    $offset += 100;
                } while ($offset < $total && $offset < 500);
                $missing = array_diff($expectedBundles, array_keys($found));
                $this->row('live.DescribeBundles', empty($missing) ? 'PASS' : 'FAIL',
                    empty($missing) ? '8/8 bundle ditemukan (fallback tanpa filter)' : 'Bundle hilang: '.implode(', ', $missing));
            } catch (\Throwable $e2) {
                $this->row('live.DescribeBundles', 'FAIL', $this->safeError($e2));
            }
        }

        // 3) Blueprints — verify the 7 configured blueprint IDs.
        try {
            $expectedBlueprints = array_values(array_filter((array) config('services.tencent.blueprints')));
            $found = [];
            $offset = 0;
            do {
                $resp = $lh->describeBlueprints($region, [
                    ['Name' => 'blueprint-id', 'Values' => $expectedBlueprints],
                ], 100, $offset);
                foreach ($resp['BlueprintSet'] ?? [] as $bp) {
                    $found[] = $bp['BlueprintId'];
                }
                $total = (int) ($resp['TotalCount'] ?? 0);
                $offset += 100;
            } while ($offset < $total);
            $missing = array_diff($expectedBlueprints, $found);
            $this->row('live.DescribeBlueprints', empty($missing) ? 'PASS' : 'FAIL',
                empty($missing) ? count($expectedBlueprints).'/'.count($expectedBlueprints).' blueprint terverifikasi' : 'Blueprint hilang: '.implode(', ', $missing));
        } catch (\Throwable $e) {
            $this->row('live.DescribeBlueprints', 'FAIL', $this->safeError($e));
        }

        // 4) Instances baseline — read-only, Limit 1.
        try {
            $resp = $lh->describeInstances($region, [], [], 1);
            $this->row('live.DescribeInstances', 'PASS',
                'TotalCount='.($resp['TotalCount'] ?? 0).' (baseline instance saat ini, tidak ada perubahan)');
        } catch (\Throwable $e) {
            $this->row('live.DescribeInstances', 'FAIL', $this->safeError($e));
        }

        // 5) Balance — READ ONLY. Unit (cents vs dollars) must be confirmed
        //    from this output before BalanceGuard thresholds are finalized.
        try {
            $resp = $billing->describeAccountBalance();
            $this->row('live.DescribeAccountBalance', 'PASS',
                'Balance='.($resp['Balance'] ?? '?').' RealBalance='.($resp['RealBalance'] ?? '?')
                .' Uin='.($resp['Uin'] ?? '?')
                .' — VERIFIKASI UNIT (sen vs dolar) sebelum threshold $'.config('services.tencent.low_balance_usd', 20).' dipakai (D9).');
        } catch (\Throwable $e) {
            $this->row('live.DescribeAccountBalance', 'FAIL', $this->safeError($e));
        }

        // 6) TAT auth check — read-only listing.
        try {
            $resp = $tat->describeCommands($region, 1);
            $this->row('live.DescribeCommands (TAT)', 'PASS',
                'Auth TAT OK, TotalCount='.($resp['TotalCount'] ?? 0));
        } catch (\Throwable $e) {
            $this->row('live.DescribeCommands (TAT)', 'FAIL', $this->safeError($e));
        }

        // 7) D5 — exact recycle-bin retention: documented ~7 days upstream.
        //    Cannot be read via API without a SHUTDOWN instance; flagged for
        //    confirmation with a real isolated instance in Phase 3.
        $this->row('live.retention (D5)', 'WARN',
            'Retensi recycle bin upstream ~7 hari (dokumentasi). Angka pasti diverifikasi di Phase 3 dengan instance SHUTDOWN nyata; jangan andalkan angka ini untuk scheduler dulu.');
    }

    private function safeError(\Throwable $e): string
    {
        // ProviderException messages contain only service/action/code/message/
        // RequestId — never credentials. Still, truncate defensively.
        return mb_substr($e->getMessage(), 0, 300);
    }

    private function row(string $check, string $status, string $detail): void
    {
        $this->results[] = [$check, $status, $detail];
        $color = match ($status) {
            'PASS' => 'green',
            'WARN', 'SKIP' => 'yellow',
            default => 'red',
        };
        $this->line(sprintf('[<fg=%s>%s</>] %s — %s', $color, $status, $check, $detail));
    }
}