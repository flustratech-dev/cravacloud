<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\RenewalGeneratedNotification;
use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Generate invoice renewal untuk subscription yang next_billing_at <= now
 * dan auto_renew = true. Invoice baru menunggu pembayaran (via webhook).
 *
 * Jalankan harian.
 */
class BillingRenewCommand extends Command
{
    protected $signature = 'billing:renew
        {--dry-run : Hanya list, tidak generate invoice}
        {--limit=100 : Maks subscription per run}';

    protected $description = 'Generate invoice renewal untuk subscription auto-renew.';

    public function __construct(protected BillingService $billingService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $subs = Subscription::where('auto_renew', true)
            ->whereIn('status', ['active', 'past_due', 'grace_period'])
            ->where('next_billing_at', '<=', now())
            ->with(['customer', 'vpsSpec', 'vpsInstance'])
            ->limit($limit)
            ->get();

        $this->info("Ditemukan {$subs->count()} subscription untuk direnew.");

        $ok = 0; $fail = 0;
        foreach ($subs as $sub) {
            if ($dry) {
                $this->line("  → [dry] would renew sub #{$sub->id} (next {$sub->next_billing_at})");
                continue;
            }
            try {
                $invoice = $this->billingService->renewSubscription($sub);
                if (!$invoice) {
                    $this->warn("  sub #{$sub->id} skipped (not eligible).");
                    continue;
                }
                $sub->customer->notify(new RenewalGeneratedNotification($sub, $invoice));
                $this->line("  ✓ sub #{$sub->id} → invoice {$invoice->invoice_number} (Rp " . number_format($invoice->amount, 0, ',', '.') . ')');
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $sub->update([
                    'renewal_failures' => $sub->renewal_failures + 1,
                    'last_renewal_error' => mb_substr($e->getMessage(), 0, 500),
                    'last_renewal_attempt_at' => now(),
                ]);
                Log::error('billing.renew_failed', [
                    'subscription_id' => $sub->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ sub #{$sub->id}: {$e->getMessage()}");
            }
        }

        $this->info("Renewal selesai: {$ok} sukses, {$fail} gagal.");
        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
