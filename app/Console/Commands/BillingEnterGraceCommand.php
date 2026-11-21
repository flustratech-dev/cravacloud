<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\RenewalService;
use Illuminate\Console\Command;

/**
 * Subscription yang past due (next_billing_at lewat & masih belum bayar)
 * → transisi ke grace_period dengan durasi sesuai jenis produk (RenewalService::graceDaysFor).
 */
class BillingEnterGraceCommand extends Command
{
    protected $signature = 'billing:enter-grace
        {--grace-days= : Override durasi grace period dalam hari}
        {--dry-run}';

    protected $description = 'Masukkan subscription past due ke grace period.';

    public function handle(RenewalService $renewals): int
    {
        $overrideDays = $this->option('grace-days') !== null ? (int) $this->option('grace-days') : null;
        $dry = (bool) $this->option('dry-run');

        // Kondisi: status masih active/past_due, next_billing_at lewat > 0 hari,
        // dan belum ada grace_period_ends_at.
        $subs = Subscription::whereIn('status', ['active', 'past_due'])
            ->where('next_billing_at', '<', now())
            ->whereNull('grace_period_ends_at')
            ->with('vpsInstance.order.vpsSpec')
            ->get();

        $this->info("Ditemukan {$subs->count()} subscription untuk masuk grace period.");

        foreach ($subs as $sub) {
            $graceDays = $overrideDays !== null
                ? max(1, $overrideDays)
                : ($sub->vpsInstance ? $renewals->graceDaysFor($sub->vpsInstance) : $renewals->graceDaysForType('vps'));

            if ($dry) {
                $this->line("  → [dry] sub #{$sub->id} akan masuk grace period ({$graceDays} hari).");
                continue;
            }

            $graceEndsAt = now()->addDays($graceDays);
            $sub->update([
                'status' => 'grace_period',
                'grace_period_ends_at' => $graceEndsAt,
            ]);

            if ($sub->vpsInstance && ! in_array($sub->vpsInstance->status, ['suspended', 'terminated'], true)) {
                $sub->vpsInstance->update(['grace_period_ends_at' => $graceEndsAt]);
            }

            $this->line("  ✓ sub #{$sub->id} → grace_period (berakhir {$sub->grace_period_ends_at})");
        }

        return self::SUCCESS;
    }
}
