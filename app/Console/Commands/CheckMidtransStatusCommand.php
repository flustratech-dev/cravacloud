<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Services\Payments\MidtransService;
use Illuminate\Console\Command;

class CheckMidtransStatusCommand extends Command
{
    protected $signature = 'midtrans:check-status {order_id? : ID pesanan spesifik yang ingin dicek}';

    protected $description = 'Cek status transaksi ke Core API Midtrans dan sinkronkan pesanan lunas secara real-time';

    public function handle(): int
    {
        $orderId = $this->argument('order_id');

        if (!MidtransService::isConfigured()) {
            $this->error('Midtrans Server Key belum dikonfigurasi di sistem.');
            return 1;
        }

        $query = Order::query();

        if ($orderId) {
            $query->where('id', $orderId);
        } else {
            $query->whereNull('paid_at')
                ->where('status', 'pending');
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->info('Tidak ada pesanan pending yang perlu disinkronkan.');
            return 0;
        }

        $this->info('Memeriksa ' . $orders->count() . ' pesanan ke Midtrans Core API...');

        $settledCount = 0;

        foreach ($orders as $order) {
            if (!PaymentGateway::isMidtransMethod($order->payment_method)) {
                continue;
            }

            $this->line("Memeriksa Order #{$order->id} (Metode: {$order->payment_method})...");

            $res = MidtransService::checkAndSyncStatus($order);

            if (!empty($res['success'])) {
                $status = $res['status'] ?? 'unknown';
                if ($status === 'settled') {
                    $settledCount++;
                    $order->refresh();
                    $this->info(" -> BERHASIL LUNAS! Order #{$order->id} telah diupdate ke status 'paid'.");
                } else {
                    $this->line(" -> Status Midtrans: {$status}");
                }
            } else {
                $this->warn(" -> Belum ada pembayaran terkonfirmasi di Midtrans untuk Order #{$order->id}.");
            }
        }

        $this->newLine();
        $this->info("Selesai. {$settledCount} pesanan berhasil disinkronkan menjadi LUNAS.");

        return 0;
    }
}
