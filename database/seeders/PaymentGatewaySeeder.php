<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

/**
 * Registry payment gateway bawaan (hanya Midtrans).
 *
 * Aman dijalankan berulang: baris Midtrans yang sudah ada tidak ditimpa
 * kredensial dan mode-nya, sementara gateway lama selain Midtrans dihapus.
 */
class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        PaymentGateway::where('code', '!=', 'midtrans')->delete();

        $defaultMode = config('services.midtrans.is_production') ? 'production' : 'sandbox';

        PaymentGateway::firstOrCreate(
            ['code' => 'midtrans'],
            [
                'name' => 'Midtrans',
                'description' => 'Pembayaran Otomatis Online (QRIS, Virtual Account, E-Wallet, dan Kartu) via Midtrans.',
                'is_active' => true,
                'mode' => $defaultMode,
                'sort_order' => 0,
            ]
        );
    }
}
