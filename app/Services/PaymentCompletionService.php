<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya titik "setelah lunas".
 *
 * Dipanggil otomatis oleh OrderStateMachine setiap order berpindah ke 'paid'
 * (setelah transaksi DB commit), apa pun jalurnya: webhook Xendit/Midtrans,
 * cek status saat pelanggan membuka halaman order, Lynk, verifikasi admin.
 *
 * Sebelumnya notifikasi hanya dikirim dari webhook. Bila halaman order lebih
 * dulu menandai lunas, webhook ditolak (paid → paid) sehingga admin dan
 * pelanggan tidak menerima apa pun, dan perpanjangan tidak diterapkan.
 *
 * Idempoten:
 *  - Perpanjangan dijaga orders.renewal_applied_at (di RenewalService).
 *  - Notifikasi dijaga orders.paid_notified_at (klaim atomik, hanya sekali).
 */
class PaymentCompletionService
{
    public function __construct(
        private RenewalService $renewals,
        private AdminAlertService $adminAlerts,
    ) {
    }

    public function handle(int $orderId): void
    {
        try {
            $order = Order::with(['customer', 'vpsSpec', 'invoice'])->find($orderId);

            if (!$order || !$order->paid_at || !in_array($order->status, ['paid', 'provisioning', 'active'], true)) {
                return;
            }

            if ($order->isRenewal()) {
                try {
                    $this->renewals->handleRenewalPayment($order);
                } catch (\Throwable $e) {
                    Log::error('payment_completion.renewal_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            }

            // Phase 5C: upgrade order paid → apply the bundle upgrade upstream.
            // isUpgrade() is excluded from isRenewal()/isNew() so it never gets
            // misrouted into the renewal or new-provision flows.
            if ($order->isUpgrade()) {
                try {
                    app(UpgradeService::class)->handleUpgradePayment($order);
                } catch (\Throwable $e) {
                    Log::error('payment_completion.upgrade_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            }

            $claimed = Order::whereKey($order->id)
                ->whereNull('paid_notified_at')
                ->update(['paid_notified_at' => now()]);

            if ($claimed === 0) {
                return;
            }

            $order = $order->fresh(['customer', 'vpsSpec', 'invoice']);

            $this->notifyCustomer($order);

            // Order Shopee sudah punya notifikasi admin sendiri (AdminShopeeOrderNotification).
            if ($order->channel !== 'shopee') {
                $this->adminAlerts->orderPaid($order);
            }

            // Phase 3 (D13 canary): auto-provisioning hanya jalan bila gate
            // mengizinkan (default OFF → alur manual tidak berubah sedikit pun).
            // Kegagalan di sini tidak boleh mengganggu penyelesaian pembayaran.
            if ($order->isNew()) {
                try {
                    app(\App\Services\Provisioning\ProvisioningOrchestrator::class)->maybeAutoProvision($order);
                } catch (\Throwable $e) {
                    Log::error('payment_completion.auto_provision_failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('payment_completion.failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }
    }

    private function notifyCustomer(Order $order): void
    {
        // Pembeli Shopee tidak menerima surel pembayaran dari sistem (sama seperti sebelumnya).
        if ($order->channel === 'shopee' || !$order->customer) {
            return;
        }

        try {
            $order->customer->notify(new PaymentReceivedNotification($order, $order->invoice));
        } catch (\Throwable $e) {
            Log::error('payment_completion.notify_customer_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
