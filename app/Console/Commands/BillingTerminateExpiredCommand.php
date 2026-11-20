<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SupplierPurchase;
use App\Notifications\ServiceTerminatedNotification;
use App\Services\Provisioning\VpsLifecycleDispatcher;
use App\Services\VpsStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Subscription suspended > N hari → terminate permanen.
 * Default retention = 30 hari suspend sebelum terminasi.
 *
 * Phase 4C (D5): untuk instance tencent, deadline aman =
 *   min(suspended_at + suspend-days, supplier_expires_at + terminate_safety_days)
 * — tidak ada angka 7 hari hardcode; jendela upstream dikonfigurasi via
 * services.tencent.terminate_safety_days. Destroy upstream dijalankan lewat
 * DestroyVpsJob (Isolate → Terminate); VPS → terminated setelah job sukses.
 */
class BillingTerminateExpiredCommand extends Command
{
    protected $signature = 'billing:terminate-expired
        {--suspend-days=30 : Durasi suspend maksimal sebelum terminate}
        {--dry-run}';

    protected $description = 'Terminate subscription yang sudah lama suspended.';

    public function __construct(
        protected VpsStateMachine $vpsStateMachine,
        protected VpsLifecycleDispatcher $lifecycle,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('suspend-days');
        $dry = (bool) $this->option('dry-run');
        $safetyDays = max(0, (int) config('services.tencent.terminate_safety_days', 12));

        $subs = Subscription::where('status', 'suspended')
            ->with(['vpsInstance', 'customer'])
            ->get()
            ->filter(function (Subscription $sub) use ($days, $safetyDays) {
                return $this->deadlineFor($sub, $days, $safetyDays)?->isPast() ?? false;
            });

        $this->info("Ditemukan {$subs->count()} subscription untuk di-terminate.");

        foreach ($subs as $sub) {
            if ($dry) {
                $this->line("  → [dry] sub #{$sub->id} akan di-terminate.");
                continue;
            }

            $vps = $sub->vpsInstance;
            $isTencent = $vps && $this->lifecycle->supports($vps);

            // A1: tencent with a live upstream instance → destroy FIRST.
            // Subscription stays SUSPENDED; DestroyVpsJob::onSuccess severs it
            // (ended_at + ServiceTerminatedNotification) only after the upstream
            // slot is actually released. Never terminate the billing record
            // while the upstream resource may still exist / occupy quota.
            if ($isTencent && $vps->status !== 'terminated') {
                try {
                    $this->lifecycle->destroy(
                        $vps,
                        'Auto-terminate: suspended melewati deadline aman.'
                    );
                    $this->line("  → sub #{$sub->id}: destroy upstream dijadwalkan (subscription tetap suspended).");
                } catch (\Throwable $e) {
                    // Dispatch failed → sub stays suspended, retried next cron.
                    Log::warning('billing.terminate_destroy_dispatch_failed', [
                        'subscription_id' => $sub->id,
                        'vps_id' => $vps->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("  ✗ sub #{$sub->id}: gagal menjadwalkan destroy — {$e->getMessage()}");
                }
                continue;
            }

            // Manual (or tencent already terminated upstream): immediate
            // severance — identical to the legacy behaviour.
            try {
                $sub->update([
                    'status' => 'terminated',
                    'ended_at' => now(),
                ]);

                if ($vps && $vps->status !== 'terminated') {
                    try {
                        $this->vpsStateMachine->transition($vps, 'terminated', [
                            'reason' => 'Auto-terminate: suspended > ' . $days . ' hari.',
                            'actor_type' => 'system',
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('billing.terminate_vps_failed', [
                            'vps_id' => $vps->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $sub->customer?->notify(new ServiceTerminatedNotification($sub));
                $this->line("  ✓ sub #{$sub->id} → terminated");
            } catch (\Throwable $e) {
                Log::error('billing.terminate_failed', [
                    'subscription_id' => $sub->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ sub #{$sub->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Deadline terminate per subscription. Manual = updated_at + suspend-days
     * (perilaku lama, identik). Tencent = min(deadline pelanggan,
     * supplier_expires_at + terminate_safety_days) — whichever comes FIRST,
     * supaya penghapusan terkendali terjadi sebelum upstream menghapus paksa.
     */
    private function deadlineFor(Subscription $sub, int $days, int $safetyDays): ?Carbon
    {
        $customerDeadline = $sub->updated_at?->copy()->addDays($days);
        $vps = $sub->vpsInstance;

        if (! $vps || ! $this->lifecycle->supports($vps)) {
            return $customerDeadline;
        }

        $supplierExpires = SupplierPurchase::where('vps_instance_id', $vps->id)
            ->whereNotNull('supplier_expires_at')
            ->max('supplier_expires_at');

        if (! $supplierExpires) {
            return $customerDeadline;
        }

        $upstreamDeadline = Carbon::parse($supplierExpires)->addDays($safetyDays);

        if ($customerDeadline === null || $upstreamDeadline->lessThan($customerDeadline)) {
            return $upstreamDeadline;
        }

        return $customerDeadline;
    }
}
