<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus paket lama (Student Basic..Business) dan kategori produk lama (ai_combo, managed_db)
 * yang tidak lagi dipakai pada katalog CravaCloud v2 (8 paket Cloud VPS + 177 1-Click Stack).
 *
 * Aman untuk produksi: paket yang tidak direferensikan order/subscription/invoice_item dihapus bersih;
 * bila di suatu database produksi ada referensi historis, paket dinonaktifkan agar FK tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vps_specs')) {
            return;
        }

        $legacyNames = [
            'Student Basic',
            'Standard',
            'Premium',
            'Startup',
            'Business',
            'Mahasiswa Basic',
            'Terminal Coding Agent',
            'Cloud AI Workstation',
            'Hermes Autonomous Hub',
            'Enterprise Private AI & RAG',
            'DB Micro',
            'DB Standard',
            'DB Enterprise / AI Vector',
        ];

        $candidates = DB::table('vps_specs')
            ->whereIn('category', ['ai_combo', 'managed_db'])
            ->orWhereIn('name', $legacyNames)
            ->pluck('id');

        foreach ($candidates as $specId) {
            $hasOrder = Schema::hasTable('orders') && DB::table('orders')->where('vps_spec_id', $specId)->exists();
            $hasSub = Schema::hasTable('subscriptions') && DB::table('subscriptions')->where('vps_spec_id', $specId)->exists();
            $hasItem = Schema::hasTable('invoice_items') && DB::table('invoice_items')->where('vps_spec_id', $specId)->exists();

            if ($hasOrder || $hasSub || $hasItem) {
                DB::table('vps_specs')->where('id', $specId)->update([
                    'is_active' => false,
                    'is_renewable' => false,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('vps_specs')->where('replacement_spec_id', $specId)->update(['replacement_spec_id' => null]);
                DB::table('vps_specs')->where('id', $specId)->delete();
            }
        }
    }

    public function down(): void
    {
        // Tidak mengembalikan paket lama yang sudah dihentikan.
    }
};
