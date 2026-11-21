<?php

use App\Services\AdminAlertService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CravaCloud v2.1 — seed operational settings (disabled_stacks & admin_alert_wa_events)
 * secara idempoten agar instalasi/migrasi produksi langsung memiliki default aman
 * tanpa perlu menjalankan SettingsSeeder secara manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $defaultWaEvents = [];
        foreach (AdminAlertService::EVENTS as $key => $meta) {
            $defaultWaEvents[$key] = (bool) ($meta['default_wa'] ?? false);
        }

        $rows = [
            [
                'key' => 'disabled_stacks',
                'value' => json_encode([]),
                'type' => 'json',
                'group' => 'provisioning',
                'label' => 'Stack Dinonaktifkan (Kill-Switch)',
                'description' => 'Daftar kunci stack dari StackRegistry yang disembunyikan sementara dari checkout & reinstall.',
                'is_public' => false,
                'sort_order' => 130,
            ],
            [
                'key' => 'admin_alert_wa_events',
                'value' => json_encode($defaultWaEvents),
                'type' => 'json',
                'group' => 'notification',
                'label' => 'Matriks Event Alert WA Admin',
                'description' => 'Konfigurasi toggle per event untuk pengiriman alert operasional ke WhatsApp Admin.',
                'is_public' => false,
                'sort_order' => 95,
            ],
        ];

        $now = now();
        foreach ($rows as $row) {
            DB::table('settings')->insertOrIgnore(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        // Non-destruktif per kebijakan v2.1: tidak menghapus konfigurasi operasional yang mungkin sudah diubah admin.
    }
};
