<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Subscription $subscription,
        public int $daysUntil
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sub = $this->subscription;
        $spec = $sub->vpsSpec?->name ?? 'Cloud VPS';
        $due = $sub->next_billing_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-';

        $subjectText = $this->daysUntil === 0
            ? "Jatuh tempo hari ini — {$spec}"
            : "H-{$this->daysUntil}: Pengingat Jatuh Tempo {$spec}";

        $headingText = $this->daysUntil === 0
            ? "Tagihan Jatuh Tempo Hari Ini"
            : "Pengingat Perpanjangan Layanan (H-{$this->daysUntil})";

        $bodyText = $this->daysUntil === 0
            ? "Langganan {$spec} Anda jatuh tempo HARI INI ({$due})."
            : "Langganan {$spec} Anda akan jatuh tempo dalam {$this->daysUntil} hari ({$due}).";

        return $this->newMailMessage()
            ->subject($subjectText)
            ->view('emails.billing-reminder', [
                'user' => $notifiable,
                'subscription' => $this->subscription,
                'daysUntil' => $this->daysUntil,
                'subjectText' => $subjectText,
                'headingText' => $headingText,
                'bodyText' => $bodyText,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'days_until' => $this->daysUntil,
            'amount' => (float) $this->subscription->unit_amount,
            'due_at' => $this->subscription->next_billing_at?->toIso8601String(),
        ];
    }
}
