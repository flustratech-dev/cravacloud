<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateways')) {
            return;
        }

        DB::table('payment_gateways')
            ->where('code', '!=', 'midtrans')
            ->delete();

        $midtrans = DB::table('payment_gateways')->where('code', 'midtrans')->first();

        if ($midtrans) {
            $update = [
                'is_active' => true,
                'sort_order' => 0,
                'updated_at' => now(),
            ];
            if (empty($midtrans->credentials)) {
                $update['mode'] = config('services.midtrans.is_production') ? 'production' : 'sandbox';
            }

            DB::table('payment_gateways')
                ->where('code', 'midtrans')
                ->update($update);
        } else {
            DB::table('payment_gateways')->insert([
                'code' => 'midtrans',
                'name' => 'Midtrans',
                'description' => 'Pembayaran Otomatis Online (QRIS, Virtual Account, E-Wallet, dan Kartu) via Midtrans.',
                'is_active' => true,
                'mode' => config('services.midtrans.is_production') ? 'production' : 'sandbox',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Sengaja tidak mengembalikan gateway usang yang telah dihapus.
    }
};
