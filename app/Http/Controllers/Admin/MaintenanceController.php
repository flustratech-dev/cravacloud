<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\DatacenterRegion;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Models\VpsSpec;
use App\Notifications\MaintenanceScheduledNotification;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 1 - Jadwal maintenance terencana (Tingkat 3).
 *
 * Perpindahan status otomatis dijalankan command maintenance:sync.
 * Controller ini hanya menangani pembuatan, penyuntingan, dan pembatalan.
 */
class MaintenanceController extends Controller
{
    use LogsAdminAudit;

    public function __construct(
        protected MaintenanceService $maintenance
    ) {
    }

    public function index()
    {
        // CASE dipakai alih-alih FIELD() agar urutan tetap sama di MySQL dan SQLite.
        $windows = MaintenanceWindow::with('creator')
            ->orderByRaw(
                "CASE status WHEN 'in_progress' THEN 0 WHEN 'scheduled' THEN 1 "
                . "WHEN 'completed' THEN 2 ELSE 3 END"
            )
            ->orderBy('starts_at', 'desc')
            ->paginate(20);

        return view('admin.maintenance', [
            'windows' => $windows,
            'scopes' => MaintenanceService::SCOPES,
            'specs' => VpsSpec::orderBy('name')->get(),
            'regions' => $this->regions(),
            'runningCount' => MaintenanceWindow::active()->count(),
            'scheduledCount' => MaintenanceWindow::where('status', 'scheduled')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateWindow($request);

        $window = MaintenanceWindow::create($validated + [
            'status' => 'scheduled',
            'created_by' => Auth::id(),
        ]);

        $this->audit(
            'maintenance.window_created',
            "Membuat jadwal maintenance \"{$window->title}\" untuk "
                . $window->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB.',
            $window
        );

        return redirect()
            ->route('admin.maintenance.index')
            ->with('success', 'Jadwal maintenance berhasil dibuat.');
    }

    public function update(Request $request, int $id)
    {
        $window = MaintenanceWindow::findOrFail($id);

        if (in_array($window->status, ['completed', 'cancelled'], true)) {
            return back()->with('error', 'Jadwal yang sudah selesai atau dibatalkan tidak dapat diubah.');
        }

        $validated = $this->validateWindow($request);

        $window->update($validated);

        $this->audit(
            'maintenance.window_updated',
            "Memperbarui jadwal maintenance \"{$window->title}\".",
            $window
        );

        return back()->with('success', 'Jadwal maintenance berhasil diperbarui.');
    }

    /**
     * Jalankan jendela lebih awal tanpa menunggu scheduler.
     */
    public function start(int $id)
    {
        $window = MaintenanceWindow::findOrFail($id);

        if ($window->status !== 'scheduled') {
            return back()->with('error', 'Hanya jadwal berstatus terjadwal yang dapat dimulai.');
        }

        $window->update(['status' => 'in_progress']);

        $this->audit(
            'maintenance.window_started',
            "Memulai maintenance \"{$window->title}\" lebih awal.",
            $window
        );

        return back()->with('success', 'Maintenance dimulai. Cakupan terdampak kini tertutup.');
    }

    /**
     * Akhiri jendela lebih awal.
     */
    public function complete(int $id)
    {
        $window = MaintenanceWindow::findOrFail($id);

        if ($window->status !== 'in_progress') {
            return back()->with('error', 'Hanya maintenance yang sedang berjalan yang dapat diselesaikan.');
        }

        $window->update(['status' => 'completed']);

        $this->audit(
            'maintenance.window_completed',
            "Menyelesaikan maintenance \"{$window->title}\".",
            $window
        );

        return back()->with('success', 'Maintenance selesai. Layanan kembali normal.');
    }

    public function cancel(int $id)
    {
        $window = MaintenanceWindow::findOrFail($id);

        if (in_array($window->status, ['completed', 'cancelled'], true)) {
            return back()->with('error', 'Jadwal ini sudah tidak aktif.');
        }

        $window->update(['status' => 'cancelled']);

        $this->audit(
            'maintenance.window_cancelled',
            "Membatalkan maintenance \"{$window->title}\".",
            $window
        );

        return back()->with('success', 'Jadwal maintenance dibatalkan.');
    }

    /**
     * Kirim pemberitahuan ke pelanggan secara manual.
     * Pengiriman otomatis ditangani command maintenance:sync.
     */
    public function notify(int $id)
    {
        $window = MaintenanceWindow::findOrFail($id);

        if ($window->status === 'cancelled') {
            return back()->with('error', 'Jadwal yang dibatalkan tidak perlu diberitahukan.');
        }

        $sent = $this->dispatchNotifications($window);

        return back()->with('success', "Pemberitahuan maintenance terkirim ke {$sent} pelanggan.");
    }

    /**
     * Kirim notifikasi jadwal ke seluruh pelanggan aktif.
     * Dipakai juga oleh command maintenance:sync.
     */
    public static function sendWindowNotifications(MaintenanceWindow $window): int
    {
        $sent = 0;

        User::where('is_admin', false)
            ->whereNotNull('email')
            ->chunkById(100, function ($users) use ($window, &$sent) {
                foreach ($users as $user) {
                    try {
                        $user->notify(new MaintenanceScheduledNotification($window));
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error('maintenance.notify_failed', [
                            'window_id' => $window->id,
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $window->update(['customers_notified_at' => now()]);

        return $sent;
    }

    protected function dispatchNotifications(MaintenanceWindow $window): int
    {
        $sent = self::sendWindowNotifications($window);

        $this->audit(
            'maintenance.customers_notified',
            "Mengirim pemberitahuan maintenance \"{$window->title}\" ke {$sent} pelanggan.",
            $window
        );

        return $sent;
    }

    protected function validateWindow(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:' . implode(',', array_keys(MaintenanceService::SCOPES))],
            'spec_ids' => ['nullable', 'array'],
            'spec_ids.*' => ['integer', 'exists:vps_specs,id'],
            'region_ids' => ['nullable', 'array'],
            'region_ids.*' => ['integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notice_days' => ['required', 'integer', 'min:0', 'max:30'],
            'notify_customers' => ['nullable'],
        ], [
            'scopes.required' => 'Pilih minimal satu cakupan yang terdampak.',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);

        $validated['notify_customers'] = $request->boolean('notify_customers');
        $validated['spec_ids'] = $validated['spec_ids'] ?? [];
        $validated['region_ids'] = $validated['region_ids'] ?? [];

        return $validated;
    }

    /**
     * Region diambil bila tabelnya tersedia. Panel tetap berjalan bila kosong.
     */
    protected function regions()
    {
        try {
            return DatacenterRegion::orderBy('name')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
