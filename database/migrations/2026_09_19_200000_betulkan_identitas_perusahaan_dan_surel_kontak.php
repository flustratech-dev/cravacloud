<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Nama badan hukum dan surel kontak yang masih berisi isian template.
 *
 * SettingsSeeder memakai firstOrCreate, jadi memperbaiki nilai bawaannya di
 * seeder tidak menyentuh database yang sudah pernah di-seed — produksi akan
 * terus mencetak nama badan usaha yang tidak ada di setiap faktur, dan surel
 * dukungan yang tidak dibaca siapa pun di setiap halaman.
 *
 * Yang diganti HANYA nilai yang masih persis sama dengan template. Nilai yang
 * sudah diubah admin lewat panel pengaturan dibiarkan: itu keputusan manusia,
 * bukan isian bawaan.
 */
return new class extends Migration
{
    private const PERBAIKAN = [
        'company_legal_name' => [
            'baru' => 'PT NAMA PERUSAHAAN',
            'template' => ['', 'CravaCloud', 'CravaCloud Indonesia', 'PT Crava Media Host', 'PT Crava Media Host Indonesia'],
        ],
        'support_email' => [
            'baru' => 'admin@gmail.com',
            'template' => ['', 'support@cravacloud.id'],
        ],
        'admin_notification_email' => [
            'baru' => 'admin@gmail.com',
            'template' => ['', 'admin@cravacloud.id'],
        ],
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('settings')) {
            return;
        }

        foreach (self::PERBAIKAN as $kunci => $aturan) {
            DB::table('settings')
                ->where('key', $kunci)
                ->where(fn ($q) => $q->whereIn('value', $aturan['template'])->orWhereNull('value'))
                ->update(['value' => $aturan['baru'], 'updated_at' => now()]);
        }

        // Pengaturan dibaca lewat cache; tanpa ini nilai lama tetap tampil sampai
        // cache-nya kedaluwarsa sendiri.
        Cache::forget(SettingsService::CACHE_KEY);
    }

    /**
     * Tidak dibalik: nilai template yang diganti memang salah sejak awal.
     */
    public function down(): void {}
};
