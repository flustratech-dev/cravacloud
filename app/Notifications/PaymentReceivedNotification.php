<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Order $order,
        public ?Invoice $invoice = null
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice ?? $this->order->invoice ?? Invoice::where('order_id', $this->order->id)->first();
        $invNo = $invoice?->invoice_number ?? ('INV-' . $this->order->id);

        $mail = $this->newMailMessage()
            ->subject("Pembayaran diterima — {$invNo}")
            ->view('emails.payment-received', [
                'order' => $this->order,
                'invoice' => $invoice,
            ]);

        if ($invoice) {
            try {
                $invoice->loadMissing(['order.customer', 'order.vpsSpec']);
                $pdf = Pdf::loadView('dashboard.invoice-pdf', ['invoice' => $invoice])
                    ->setPaper('a4', 'portrait')
                    ->setOptions([
                        'isHtml5ParserEnabled' => true,
                        'isRemoteEnabled' => true,
                        'defaultFont' => 'Helvetica',
                    ]);

                $mail->attachData(
                    $pdf->output(),
                    "Invoice-{$invNo}.pdf",
                    ['mime' => 'application/pdf']
                );
            } catch (\Throwable $e) {
                Log::error('notification.invoice_pdf_attach_failed', [
                    'order_id' => $this->order->id,
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'invoice_id' => $this->invoice?->id,
            'amount' => (float) $this->order->amount,
        ];
    }
}
