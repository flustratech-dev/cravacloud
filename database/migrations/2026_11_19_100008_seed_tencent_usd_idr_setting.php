<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tencent integration Phase 3 — D9 exchange rate setting.
 *
 * tencent_usd_idr: admin-managed USD→IDR rate used by ProvisioningFinalizer
 * to convert the upstream bundle HPP (USD) into SupplierPurchase.actual_cost
 * (IDR). Default 16500; admin can adjust anytime without deploy.
 *
 * Idempotent: an existing (admin-edited) value is never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'tencent_usd_idr')->exists();
        if (! $exists) {
            DB::table('settings')->insert([
                'key' => 'tencent_usd_idr',
                'value' => '16500',
                'type' => 'integer',
                'group' => 'provisioning',
                'label' => 'Kurs USD ke IDR (HPP Upstream)',
                'description' => 'Kurs konversi biaya paket upstream (USD) ke Rupiah untuk catatan pembelian supplier. Kelola manual sesuai kurs berjalan.',
                'is_public' => false,
                'sort_order' => 93,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'tencent_usd_idr')->delete();
    }
};