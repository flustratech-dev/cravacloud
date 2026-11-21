<?php

namespace App\Console\Commands;

use App\Models\VpsInstance;
use App\Services\SubscriptionSyncService;
use Illuminate\Console\Command;

/**
 * One-time / idempotent backfill agar seluruh VpsInstance yang sudah ada
 * di database memiliki baris Subscription 1:1 yang sinkron.
 */
class BillingBackfillSubscriptionsCommand extends Command
{
    protected $signature = 'billing:backfill-subscriptions
        {--dry-run : Hanya hitung, tidak menulis ke database}';

    protected $description = 'Backfill entri subscriptions 1:1 untuk VpsInstance yang belum tertaut.';

    public function handle(SubscriptionSyncService $sync): int
    {
        $dry = (bool) $this->option('dry-run');

        $instances = VpsInstance::whereDoesntHave('subscription')
            ->with(['order.vpsSpec'])
            ->get();

        $this->info("Ditemukan {$instances->count()} VpsInstance tanpa subscription.");

        $created = 0;
        $skipped = 0;

        foreach ($instances as $instance) {
            if ($dry) {
                $this->line("  -> [dry] instance #{$instance->id} ({$instance->hostname}) status={$instance->status}");
                continue;
            }

            $sub = $sync->ensureForInstance($instance);
            if ($sub) {
                $created++;
                $this->line("  ✓ instance #{$instance->id} -> subscription #{$sub->id} ({$sub->status})");
            } else {
                $skipped++;
                $this->warn("  ! instance #{$instance->id} dilewati (spec tidak ditemukan)");
            }
        }

        if (! $dry) {
            $this->info("Backfill selesai: {$created} dibuat/disinkronkan, {$skipped} dilewati.");
        }

        return self::SUCCESS;
    }
}
