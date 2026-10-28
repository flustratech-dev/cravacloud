<?php

namespace App\Services;

use App\Models\MaintenanceWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Penentu apakah suatu cakupan sedang tertutup karena maintenance.
 *
 * Tiga tingkat yang ditangani:
 *   Tingkat 1 - global   : seluruh situs tertutup kecuali admin
 *   Tingkat 2 - cakupan  : hanya bagian tertentu yang ditutup
 *   Tingkat 3 - terjadwal: jendela maintenance yang sedang berjalan
 *
 * Rute webhook TIDAK PERNAH ditutup. Lihat EXEMPT_PATHS.
 */
class MaintenanceService
{
    /**
     * Cakupan yang dapat ditutup terpisah.
     */
    public const SCOPES = [
        'checkout' => 'Checkout & Pemesanan',
        'provisioning' => 'Antrean Provisioning',
        'vps_actions' => 'Aksi VPS Pelanggan',
        'support' => 'Tiket Bantuan',
        'dashboard' => 'Dasbor Klien',
    ];

    /**
     * Rute yang selalu lolos dari seluruh tingkat maintenance.
     *
     * Webhook pembayaran wajib ada di sini. Bila Lynk mengirim notifikasi
     * lalu ditolak 503, pembayaran pelanggan berpotensi hilang tanpa
     * percobaan ulang. Payload tetap diterima dan diproses belakangan.
     */
    public const EXEMPT_PATHS = [
        'api/webhooks/*',
        'up',
        'login',
        'logout',
        'admin',
        'admin/*',
    ];

    public function __construct(
        protected SettingsService $settings
    ) {
    }

    /**
     * Tingkat 1: seluruh situs tertutup.
     */
    public function isGlobalActive(): bool
    {
        return $this->settings->bool('maintenance_global_enabled', false);
    }

    /**
     * Tingkat 2: cakupan tertentu ditutup lewat sakelar manual.
     */
    public function isScopeActive(string $scope): bool
    {
        return $this->settings->bool('maintenance_scope_' . $scope, false);
    }

    /**
     * Gabungan semua tingkat untuk satu cakupan.
     */
    public function isBlocked(string $scope, ?int $specId = null, ?int $regionId = null): bool
    {
        if ($this->isGlobalActive()) {
            return true;
        }

        if ($this->isScopeActive($scope)) {
            return true;
        }

        return $this->runningWindowFor($scope, $specId, $regionId) !== null;
    }

    /**
     * Tingkat 3: jendela terjadwal yang sedang berjalan untuk cakupan ini.
     */
    public function runningWindowFor(string $scope, ?int $specId = null, ?int $regionId = null): ?MaintenanceWindow
    {
        foreach ($this->runningWindows() as $window) {
            if (!$window->coversScope($scope)) {
                continue;
            }

            if (!$window->coversSpec($specId) || !$window->coversRegion($regionId)) {
                continue;
            }

            return $window;
        }

        return null;
    }

    /**
     * Seluruh jendela yang sedang berjalan.
     */
    public function runningWindows()
    {
        if (!Schema::hasTable('maintenance_windows')) {
            return collect();
        }

        return MaintenanceWindow::active()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get();
    }

    /**
     * Jendela terjadwal yang sudah waktunya diumumkan lewat spanduk.
     *
     * Penyaringan H-notice_days dikerjakan di PHP agar kueri tetap portabel
     * antara MySQL dan SQLite.
     */
    public function upcomingWindows()
    {
        if (!Schema::hasTable('maintenance_windows')) {
            return collect();
        }

        return MaintenanceWindow::upcoming()
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (MaintenanceWindow $w) => $w->isAnnounceable())
            ->values();
    }

    /**
     * Pesan yang ditampilkan ke pengunjung saat cakupan tertutup.
     */
    public function messageFor(string $scope, ?int $specId = null, ?int $regionId = null): string
    {
        $window = $this->runningWindowFor($scope, $specId, $regionId);

        if ($window) {
            return $window->description
                ?: 'Sedang ada maintenance terjadwal: ' . $window->title . '. Estimasi selesai '
                    . $window->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB.';
        }

        $custom = $this->settings->get('maintenance_message');

        return $custom ?: 'Layanan ini sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.';
    }

    /**
     * Estimasi waktu pulih untuk header Retry-After, dalam detik.
     */
    public function retryAfterSeconds(string $scope = 'dashboard'): int
    {
        $window = $this->runningWindowFor($scope);

        if ($window && $window->ends_at->isFuture()) {
            return max(60, (int) now()->diffInSeconds($window->ends_at));
        }

        return 3600;
    }

    /**
     * Apakah request ini berhak melewati maintenance.
     *
     * Tiga jalur pintas: rute yang dikecualikan, admin yang sudah masuk,
     * serta token rahasia atau IP yang diizinkan untuk pengujian.
     */
    public function shouldBypass(Request $request): bool
    {
        foreach (self::EXEMPT_PATHS as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        $user = $request->user();
        if ($user && $user->is_admin) {
            return true;
        }

        $token = (string) $this->settings->get('maintenance_bypass_token', '');
        if ($token !== '') {
            if (hash_equals($token, (string) $request->query('bypass', ''))) {
                $request->session()?->put('maintenance_bypass', $token);

                return true;
            }

            if (hash_equals($token, (string) $request->session()?->get('maintenance_bypass', ''))) {
                return true;
            }
        }

        $allowedIps = array_filter(array_map(
            'trim',
            explode(',', (string) $this->settings->get('maintenance_allowed_ips', ''))
        ));

        if ($allowedIps !== [] && in_array($request->ip(), $allowedIps, true)) {
            return true;
        }

        return false;
    }

    /**
     * Daftar cakupan beserta keadaannya, untuk halaman pengaturan admin.
     */
    public function scopeStates(): array
    {
        $states = [];

        foreach (self::SCOPES as $scope => $label) {
            $states[$scope] = [
                'label' => $label,
                'manual' => $this->isScopeActive($scope),
                'window' => $this->runningWindowFor($scope),
            ];
        }

        return $states;
    }
}
