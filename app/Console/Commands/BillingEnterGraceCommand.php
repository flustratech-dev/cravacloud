<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Subscription yang past due (next_billing_at lewat & masih belum bayar)
 * → transisi ke grace_period dengan grace_period_ends_at = now + N hari.
 *
 * Default grace = 7 hari. Bisa di-override via option.
 */
class BillingEnterGraceCommand extends Command
{
    protected $signature = 'billing:enter-grace
        {--grace-days=7 : Durasi grace period dalam hari}
        {--dry-run}';

    protected $description = 'Masukkan subscription past due ke grace period.';

    public function handle(): int
    {
        $graceDays = (int) $this->option('grace-days');
        $dry = (bool) $this->option('dry-run');

        // Kondisi: status masih active/past_due, next_billing_at lewat > 0 hari,
        // dan belum ada grace_period_ends_at.
        $subs = Subscription::whereIn('status', ['active', 'past_due'])
            ->where('next_billing_at', '<', now())
            ->whereNull('grace_period_ends_at')
            ->get();

        $this->info("Ditemukan {$subs->count()} subscription untuk masuk grace period.");

        foreach ($subs as $sub) {
            if ($dry) {
                $this->line("  → [dry] sub #{$sub->id} akan masuk grace period.");
                continue;
            }
            $sub->update([
                'status' => 'grace_period',
                'grace_period_ends_at' => now()->addDays($graceDays),
            ]);
            $this->line("  ✓ sub #{$sub->id} → grace_period (berakhir {$sub->grace_period_ends_at})");
        }

        return self::SUCCESS;
    }
}
