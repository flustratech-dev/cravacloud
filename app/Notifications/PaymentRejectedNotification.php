<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Order $order,
        public ?Invoice $invoice = null,
        public string $reason = 'Mutasi pembayaran tidak ditemukan atau tidak sesuai'
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invNo = $this->invoice?->invoice_number ?? ('INV-' . $this->order->id);

        return $this->newMailMessage('support')
            ->subject("Pembayaran Tidak Terverifikasi — {$invNo}")
            ->view('emails.payment-rejected', [
                'order' => $this->order,
                'invoice' => $this->invoice,
                'reason' => $this->reason,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'invoice_id' => $this->invoice?->id,
            'amount' => (float) $this->order->amount,
            'reason' => $this->reason,
        ];
    }
}
