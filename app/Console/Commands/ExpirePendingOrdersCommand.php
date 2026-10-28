<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\OrderStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * PILAR 3 - Auto-Expire Order pending (TTL 24 Jam).
 *
 * Mencegah adanya order "gantung" berminggu-minggu di database pelanggan
 * yang dapat mengunci harga lama saat terjadi fluktuasi harga retail upstream.
 */
class ExpirePendingOrdersCommand extends Command
{
    protected $signature = 'orders:expire-pending {--dry-run} {--hours=24}';

    protected $description = 'Batalkan secara otomatis order pending yang telah melebihi batas waktu pembayaran.';

    public function __construct(protected OrderStateMachine $orderStateMachine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $hours = (int) $this->option('hours');

        $cutoff = now()->subHours($hours);

        $pendingOrders = Order::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->with(['invoice', 'customer'])
            ->get();

        $count = $pendingOrders->count();
        $this->info("Ditemukan {$count} pesanan pending yang berumur lebih dari {$hours} jam.");

        foreach ($pendingOrders as $order) {
            if ($dry) {
                $this->line("  → [dry-run] Order #{$order->id} ({$order->customer?->email}) dibuat {$order->created_at} akan di-expire.");
                continue;
            }

            try {
                $this->orderStateMachine->transition($order, 'expired', [
                    'reason' => "Batas waktu pembayaran {$hours} jam telah berakhir tanpa konfirmasi pembayaran.",
                    'actor_type' => 'system',
                ]);

                // Update status invoice jika ada
                if ($order->invoice && in_array($order->invoice->status, ['draft', 'unpaid', 'pending'], true)) {
                    $order->invoice->update([
                        'status' => 'cancelled',
                    ]);
                }

                // Invalidate transaksi gateway yang masih pending
                PaymentTransaction::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'expired',
                    ]);

                Log::info('order.auto_expired', [
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'created_at' => (string) $order->created_at,
                    'hours_ttl' => $hours,
                ]);

                $this->info("Order #{$order->id} berhasil diubah status menjadi expired.");
            } catch (\Throwable $e) {
                Log::error('order.auto_expire_failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Gagal meng-expire Order #{$order->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
