<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\ServiceTerminatedNotification;
use App\Services\VpsStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Subscription suspended > N hari → terminate permanen.
 * Default retention = 30 hari suspend sebelum terminasi.
 */
class BillingTerminateExpiredCommand extends Command
{
    protected $signature = 'billing:terminate-expired
        {--suspend-days=30 : Durasi suspend maksimal sebelum terminate}
        {--dry-run}';

    protected $description = 'Terminate subscription yang sudah lama suspended.';

    public function __construct(protected VpsStateMachine $vpsStateMachine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('suspend-days');
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $subs = Subscription::where('status', 'suspended')
            ->where('updated_at', '<', $cutoff)
            ->with(['vpsInstance', 'customer'])
            ->get();

        $this->info("Ditemukan {$subs->count()} subscription untuk di-terminate.");

        foreach ($subs as $sub) {
            if ($dry) {
                $this->line("  → [dry] sub #{$sub->id} akan di-terminate.");
                continue;
            }

            try {
                $sub->update([
                    'status' => 'terminated',
                    'ended_at' => now(),
                ]);

                if ($sub->vpsInstance && $sub->vpsInstance->status !== 'terminated') {
                    try {
                        $this->vpsStateMachine->transition($sub->vpsInstance, 'terminated', [
                            'reason' => 'Auto-terminate: suspended > ' . $days . ' hari.',
                            'actor_type' => 'system',
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('billing.terminate_vps_failed', [
                            'vps_id' => $sub->vpsInstance->id,
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
}
