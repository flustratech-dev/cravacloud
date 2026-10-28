<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\MaintenanceController;
use App\Models\MaintenanceWindow;
use App\Models\SystemComponent;
use App\Services\MaintenanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 1 - Penggerak jendela maintenance terjadwal.
 *
 * Tiga pekerjaan:
 *   1. Mulai jendela yang waktunya sudah tiba (scheduled -> in_progress)
 *   2. Akhiri jendela yang waktunya sudah lewat (in_progress -> completed)
 *   3. Kirim pemberitahuan H-notice_days ke pelanggan, sekali saja
 *
 * Status komponen di halaman status ikut disesuaikan.
 * Command ini idempoten dan aman dijalankan berkali-kali.
 */
class MaintenanceSyncCommand extends Command
{
    protected $signature = 'maintenance:sync
        {--dry-run : Hanya tampilkan rencana tindakan, tidak mengubah apa pun}';

    protected $description = 'Jalankan dan akhiri jendela maintenance terjadwal, serta kirim pemberitahuannya.';

    public function handle(MaintenanceService $maintenance): int
    {
        $dry = (bool) $this->option('dry-run');

        $started = $this->startDueWindows($dry);
        $completed = $this->completeFinishedWindows($dry);
        $notified = $this->notifyUpcomingWindows($dry);

        if (!$dry) {
            $this->syncComponentStatuses($maintenance);
        }

        $this->info("Dimulai: {$started} | Diselesaikan: {$completed} | Diberitahukan: {$notified}");

        return self::SUCCESS;
    }

    /**
     * scheduled -> in_progress untuk jendela yang waktunya sudah tiba.
     */
    protected function startDueWindows(bool $dry): int
    {
        $windows = MaintenanceWindow::where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->get();

        foreach ($windows as $window) {
            if ($dry) {
                $this->line("  → [dry] akan memulai jendela #{$window->id} \"{$window->title}\"");
                continue;
            }

            $window->update(['status' => 'in_progress']);
            $this->line("  ✓ Memulai jendela #{$window->id} \"{$window->title}\"");
        }

        return $windows->count();
    }

    /**
     * in_progress -> completed untuk jendela yang waktunya sudah lewat.
     *
     * Jendela terjadwal yang terlewat seluruhnya (mis. scheduler mati)
     * ikut ditutup agar tidak menggantung.
     */
    protected function completeFinishedWindows(bool $dry): int
    {
        $windows = MaintenanceWindow::whereIn('status', ['scheduled', 'in_progress'])
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($windows as $window) {
            if ($dry) {
                $this->line("  → [dry] akan menyelesaikan jendela #{$window->id} \"{$window->title}\"");
                continue;
            }

            $window->update(['status' => 'completed']);
            $this->line("  ✓ Menyelesaikan jendela #{$window->id} \"{$window->title}\"");
        }

        return $windows->count();
    }

    /**
     * Kirim pemberitahuan H-notice_days, sekali per jendela.
     * customers_notified_at adalah pengaman idempotensinya.
     */
    protected function notifyUpcomingWindows(bool $dry): int
    {
        $windows = MaintenanceWindow::upcoming()
            ->where('notify_customers', true)
            ->whereNull('customers_notified_at')
            ->get()
            ->filter(fn (MaintenanceWindow $w) => $w->isAnnounceable());

        $total = 0;

        foreach ($windows as $window) {
            if ($dry) {
                $this->line("  → [dry] akan memberitahukan jendela #{$window->id} \"{$window->title}\"");
                $total++;
                continue;
            }

            try {
                $sent = MaintenanceController::sendWindowNotifications($window);
                $this->line("  ✓ Memberitahukan jendela #{$window->id} ke {$sent} pelanggan");
                $total++;
            } catch (\Throwable $e) {
                Log::error('maintenance.sync_notify_failed', [
                    'window_id' => $window->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ✗ Gagal memberitahukan jendela #{$window->id}: {$e->getMessage()}");
            }
        }

        return $total;
    }

    /**
     * Setel status komponen mengikuti jendela yang sedang berjalan.
     *
     * Hanya status maintenance yang disentuh. Status degraded dan outage
     * yang disetel admin secara manual tidak ditimpa.
     */
    protected function syncComponentStatuses(MaintenanceService $maintenance): void
    {
        $running = $maintenance->runningWindows();

        // Cakupan yang sedang tertutup, digabung dari seluruh jendela berjalan.
        $activeScopes = $running->flatMap(fn (MaintenanceWindow $w) => $w->scopes ?? [])->unique();

        foreach ($this->scopeToComponentMap() as $scope => $componentSlug) {
            $component = SystemComponent::where('slug', $componentSlug)->first();
            if (!$component) {
                continue;
            }

            $shouldBeMaintenance = $activeScopes->contains($scope)
                || $maintenance->isScopeActive($scope)
                || $maintenance->isGlobalActive();

            if ($shouldBeMaintenance && $component->status === 'operational') {
                $component->update([
                    'status' => 'maintenance',
                    'status_note' => 'Sedang dalam pemeliharaan terjadwal.',
                ]);
                continue;
            }

            if (!$shouldBeMaintenance && $component->status === 'maintenance') {
                $component->update(['status' => 'operational', 'status_note' => null]);
            }
        }
    }

    /**
     * Pemetaan cakupan maintenance ke komponen halaman status.
     */
    protected function scopeToComponentMap(): array
    {
        return [
            'checkout' => 'web-panel',
            'dashboard' => 'web-panel',
            'provisioning' => 'provisioning',
            'vps_actions' => 'vps-network',
            'support' => 'support',
        ];
    }
}
