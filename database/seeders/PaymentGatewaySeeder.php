<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

/**
 * PHASE 4 - Registry payment gateway bawaan.
 *
 * Status aktif awal SENGAJA sama dengan perilaku checkout sebelumnya:
 * hanya Lynk.id dan QRIS yang bisa dipilih. Gateway lain didaftarkan
 * nonaktif sebagai kerangka, menunggu pendaftaran merchant.
 *
 * Aman dijalankan berulang: baris yang sudah ada tidak ditimpa, sehingga
 * status dan kredensial yang diubah admin tidak kembali ke bawaan.
 */
class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'code' => 'lynk',
                'name' => 'Lynk.id',
                'description' => 'Jalur pembayaran Lynk.id.',
                'is_active' => false,
                'mode' => 'production',
            ],
            [
                'code' => 'qris',
                'name' => 'QRIS CravaCloud',
                'description' => 'QRIS statis verifikasi manual.',
                'is_active' => true,
                'mode' => 'production',
            ],
            [
                'code' => 'midtrans',
                'name' => 'Midtrans',
                'description' => 'QRIS Otomatis, Virtual Account, dan E-Wallet resmi via Midtrans.',
                'is_active' => false,
                'mode' => 'sandbox',
            ],
            [
                'code' => 'xendit',
                'name' => 'Xendit',
                'description' => 'Pembayaran Otomatis Online (QRIS, VA, Minimarket, PayLater) via Xendit.',
                'is_active' => true,
                'mode' => 'sandbox',
            ],
            [
                'code' => 'tripay',
                'name' => 'Tripay',
                'description' => 'Kerangka. Menunggu pendaftaran merchant.',
                'is_active' => false,
                'mode' => 'sandbox',
            ],
            [
                'code' => 'manual_transfer',
                'name' => 'Transfer Bank Manual',
                'description' => 'Kerangka rekening tujuan transfer manual. Belum ditawarkan di checkout.',
                'is_active' => false,
                'mode' => 'production',
            ],
        ];

        foreach ($gateways as $index => $gateway) {
            PaymentGateway::firstOrCreate(
                ['code' => $gateway['code']],
                $gateway + ['sort_order' => $index]
            );
        }
    }
}
