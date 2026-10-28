<?php

namespace App\Http\Controllers;

use App\Models\SystemComponent;
use App\Services\MaintenanceService;

/**
 * PHASE 1 - Halaman status publik.
 *
 * Menampilkan keadaan sebenarnya dari tabel system_components dan jendela
 * maintenance. Persentase uptime tidak ditampilkan karena tidak ada
 * pemantauan otomatis atas server supplier yang bisa menjadi dasarnya.
 */
class StatusController extends Controller
{
    public function __invoke(MaintenanceService $maintenance)
    {
        $components = SystemComponent::visible()->orderBy('sort_order')->get();

        $allOperational = $components->isEmpty()
            || $components->every(fn (SystemComponent $c) => $c->status === 'operational');

        return view('pages.status', [
            'components' => $components,
            'allOperational' => $allOperational,
            'runningWindows' => $maintenance->runningWindows(),
            'upcomingWindows' => $maintenance->upcomingWindows(),
            'globalMaintenance' => $maintenance->isGlobalActive(),
        ]);
    }
}
