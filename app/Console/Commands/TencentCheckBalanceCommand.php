<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\AdminAlertService;
use App\Services\Provisioning\Tencent\BalanceGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Hourly upstream balance watchdog (blueprint §2.5, D9). Read-only (Rp0).
 *
 * Alerts WA+email via AdminAlertService when:
 *   balance < low_balance_usd (default $20), OR
 *   balance < cheapest pending mapped order's HPP (can't fulfill what's sold).
 * Deduped daily; escalates (re-alerts) when the balance drops further.
 */
class TencentCheckBalanceCommand extends Command
{
    protected $signature = 'tencent:check-balance';

    protected $description = 'Cek saldo upstream Tencent dan alert bila di bawah threshold (read-only)';

    public function handle(BalanceGuard $guard, AdminAlertService $alerts): int
    {
        try {
            $balance = $guard->balanceUsd(fresh: true);
        } catch (\Throwable $e) {
            $this->error('Gagal membaca saldo: '.mb_substr($e->getMessage(), 0, 300));
            Log::warning('tencent.balance_check_failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $threshold = $guard->lowBalanceThresholdUsd();
        $minPendingHpp = $this->minPendingHppUsd();
        $required = max($threshold, $minPendingHpp ?? 0.0);

        $this->info(sprintf(
            'Saldo upstream: $%.2f | threshold: $%.2f | HPP pending terendah: %s',
            $balance,
            $threshold,
            $minPendingHpp !== null ? sprintf('$%.2f', $minPendingHpp) : '(tidak ada order pending terpetakan)',
        ));

        if ($balance >= $required) {
            $this->info('Saldo aman — tidak ada alert.');
            Cache::put('tencent:balance:last', $balance, now()->addWeek());

            return self::SUCCESS;
        }

        // Daily dedupe with escalation: re-alert only if balance dropped
        // below the level we last alerted at.
        $dayKey = 'tencent:balance_alert:'.now()->format('Ymd');
        $lastAlerted = Cache::get($dayKey);
        $shouldAlert = $lastAlerted === null || $balance < (float) $lastAlerted;

        if ($shouldAlert) {
            $reason = $balance < $threshold
                ? "di bawah threshold \${$threshold}"
                : 'di bawah HPP order pending terendah ($'.number_format((float) $minPendingHpp, 2).')';

            $text = "Saldo upstream Tencent Cloud hanya \${$balance} ({$reason}). "
                .'Segera top-up agar provisioning & renewal otomatis tidak gagal. '
                .'Order yang sudah dibayar customer akan ditahan aman (failed + retry-provision) bila saldo kurang saat create.';

            try {
                $alerts->digest('Saldo upstream rendah', $text);
                Cache::put($dayKey, $balance, now()->addDay());
                $this->warn('Alert terkirim ke admin (WA+email).');
            } catch (\Throwable $e) {
                Log::warning('tencent.balance_alert_failed', ['error' => $e->getMessage()]);
                $this->error('Alert gagal dikirim: '.mb_substr($e->getMessage(), 0, 200));
            }
        } else {
            $this->line('Sudah di-alert hari ini pada level yang sama/lebih rendah — dedupe.');
        }

        Cache::put('tencent:balance:last', $balance, now()->addWeek());

        return self::SUCCESS;
    }

    /** Cheapest HPP (USD) among pending orders whose spec is upstream-mapped. */
    private function minPendingHppUsd(): ?float
    {
        $prices = (array) config('services.tencent.bundle_prices_usd', []);

        $bundleIds = Order::where('status', 'pending')
            ->where('order_type', '!=', 'renewal')
            ->whereHas('vpsSpec', fn ($q) => $q->whereNotNull('upstream_bundle_id'))
            ->with('vpsSpec:id,upstream_bundle_id')
            ->get()
            ->pluck('vpsSpec.upstream_bundle_id')
            ->filter()
            ->unique();

        $min = null;
        foreach ($bundleIds as $bundleId) {
            if (isset($prices[$bundleId])) {
                $price = (float) $prices[$bundleId];
                $min = $min === null ? $price : min($min, $price);
            }
        }

        return $min;
    }
}