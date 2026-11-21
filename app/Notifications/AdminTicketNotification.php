<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public SupportTicket $ticket,
        public string $userMessage,
        public string $eventType = 'created' // 'created' | 'replied'
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->eventType === 'created' ? 'Tiket Bantuan Baru Masuk' : 'Balasan Baru dari Pelanggan';

        return $this->newMailMessage()
            ->subject("[SUPPORT] {$title} — #{$this->ticket->id}")
            ->view('emails.admin-ticket', [
                'ticket' => $this->ticket,
                'userMessage' => $this->userMessage,
                'eventType' => $this->eventType,
            ]);
    }
}
