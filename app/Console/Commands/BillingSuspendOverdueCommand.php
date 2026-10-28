<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\ServiceSuspendedNotification;
use App\Services\VpsStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Grace period habis → suspend subscription + VPS.
 */
class BillingSuspendOverdueCommand extends Command
{
    protected $signature = 'billing:suspend-overdue {--dry-run}';

    protected $description = 'Suspend layanan yang grace period-nya habis.';

    public function __construct(protected VpsStateMachine $vpsStateMachine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $subs = Subscription::where('status', 'grace_period')
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<', now())
            ->with(['vpsInstance', 'customer'])
            ->get();

        $this->info("Ditemukan {$subs->count()} subscription untuk di-suspend.");

        foreach ($subs as $sub) {
            if ($dry) {
                $this->line("  → [dry] sub #{$sub->id} akan di-suspend.");
                continue;
            }

            try {
                $sub->update(['status' => 'suspended']);

                if ($sub->vpsInstance && $sub->vpsInstance->status !== 'suspended') {
                    try {
                        $this->vpsStateMachine->transition($sub->vpsInstance, 'suspended', [
                            'reason' => 'Grace period habis, service tidak dibayar.',
                            'actor_type' => 'system',
                        ]);
                    } catch (\Throwable $e) {
                        // Kalau VPS di state yang tidak bisa suspended (mis. terminated), skip.
                        Log::warning('billing.suspend_vps_failed', [
                            'vps_id' => $sub->vpsInstance->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $sub->customer?->notify(new ServiceSuspendedNotification($sub));
                $this->line("  ✓ sub #{$sub->id} → suspended");
            } catch (\Throwable $e) {
                Log::error('billing.suspend_failed', [
                    'subscription_id' => $sub->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ sub #{$sub->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
