<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\BillingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cek subscription yang mendekati jatuh tempo (H-7, H-3, H-1, H+0).
 * Kirim notifikasi reminder ke pelanggan.
 *
 * Jalankan harian: schedule di routes/console.php.
 */
class BillingCheckDueCommand extends Command
{
    protected $signature = 'billing:check-due
        {--days=7,3,1,0 : Comma-separated hari-hari untuk reminder}
        {--dry-run : Hanya tampilkan, tidak kirim notifikasi}';

    protected $description = 'Kirim reminder subscription yang mendekati jatuh tempo (H-7/H-3/H-1/H+0).';

    public function handle(): int
    {
        $days = array_map('intval', explode(',', $this->option('days')));
        $dry = (bool) $this->option('dry-run');

        $totalNotified = 0;
        foreach ($days as $d) {
            $start = now()->addDays($d)->startOfDay();
            $end = now()->addDays($d)->endOfDay();

            $subs = Subscription::whereIn('status', ['active', 'past_due', 'grace_period'])
                ->whereBetween('next_billing_at', [$start, $end])
                ->with(['customer', 'vpsSpec', 'vpsInstance'])
                ->get();

            $this->info("H-{$d}: {$subs->count()} subscription due.");

            foreach ($subs as $sub) {
                if ($dry) {
                    $this->line("  → [dry] would notify sub #{$sub->id} customer #{$sub->customer_id}");
                    continue;
                }
                try {
                    $sub->customer->notify(new BillingReminderNotification($sub, $d));
                    $totalNotified++;
                } catch (\Throwable $e) {
                    Log::error('billing.reminder_failed', [
                        'subscription_id' => $sub->id,
                        'days' => $d,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Total reminder dikirim: {$totalNotified}");
        return self::SUCCESS;
    }
}
