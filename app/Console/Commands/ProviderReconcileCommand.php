<?php

namespace App\Console\Commands;

use App\Models\VpsInstance;
use App\Services\Provisioning\ProviderManager;
use App\Services\VpsStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rekonsiliasi berkala: bandingkan status VPS di DB vs status di provider.
 * Kalau divergent, catat ke log dan (opsional) transisi ke status provider.
 *
 * Untuk provider 'manual', ini no-op (status() return status DB).
 */
class ProviderReconcileCommand extends Command
{
    protected $signature = 'provider:reconcile
        {--limit=100 : Maks instance per run}
        {--auto-correct : Kalau divergent, transisi state DB ke status provider}
        {--dry-run}';

    protected $description = 'Rekonsiliasi status VPS antara DB dan provider.';

    public function __construct(
        protected ProviderManager $manager,
        protected VpsStateMachine $vpsStateMachine
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $autoCorrect = (bool) $this->option('auto-correct');
        $dry = (bool) $this->option('dry-run');

        $instances = VpsInstance::whereNotIn('status', ['terminated'])
            ->orderBy('last_reconciled_at', 'asc')
            ->limit($limit)
            ->get();

        $divergent = 0; $reconciled = 0;
        $this->info("Cek {$instances->count()} instance.");

        foreach ($instances as $instance) {
            try {
                $provider = $this->manager->provider($instance->provider ?? 'manual');
                $result = $provider->status($instance);
                $providerStatus = $result['status'] ?? null;

                $instance->update([
                    'last_reconciled_at' => now(),
                    'provider_meta' => array_merge($instance->provider_meta ?? [], [
                        'last_reconcile' => $result['meta'] ?? [],
                    ]),
                ]);

                if ($providerStatus && $providerStatus !== $instance->status) {
                    $divergent++;
                    $this->warn("  ! vps #{$instance->id} DB={$instance->status} provider={$providerStatus}");
                    Log::warning('reconcile.divergent', [
                        'vps_id' => $instance->id,
                        'db_status' => $instance->status,
                        'provider_status' => $providerStatus,
                    ]);

                    if ($autoCorrect && !$dry) {
                        try {
                            $this->vpsStateMachine->transition($instance, $providerStatus, [
                                'reason' => 'Reconcile auto-correct dari provider.',
                                'actor_type' => 'system',
                                'metadata' => ['source' => 'reconcile'],
                            ]);
                            $reconciled++;
                        } catch (\Throwable $e) {
                            Log::warning('reconcile.transition_failed', [
                                'vps_id' => $instance->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('reconcile.error', ['vps_id' => $instance->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Selesai. Divergent: {$divergent}, auto-corrected: {$reconciled}.");
        return self::SUCCESS;
    }
}
