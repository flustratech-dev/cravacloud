<?php

namespace App\Notifications;

use App\Models\MaintenanceWindow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan maintenance terjadwal ke pelanggan.
 */
class MaintenanceScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public MaintenanceWindow $window
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $start = $this->window->starts_at->timezone('Asia/Jakarta');
        $end = $this->window->ends_at->timezone('Asia/Jakarta');

        $subject = 'Pemberitahuan Maintenance Terjadwal — ' . $start->format('d M Y');

        return $this->newMailMessage()
            ->subject($subject)
            ->view('emails.maintenance-scheduled', [
                'user' => $notifiable,
                'window' => $this->window,
                'startText' => $start->format('d M Y, H:i') . ' WIB',
                'endText' => $end->format('d M Y, H:i') . ' WIB',
                'durationText' => $this->durationText(),
                'subject' => $subject,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'maintenance_window_id' => $this->window->id,
            'title' => $this->window->title,
            'starts_at' => $this->window->starts_at->toIso8601String(),
            'ends_at' => $this->window->ends_at->toIso8601String(),
        ];
    }

    protected function durationText(): string
    {
        $minutes = $this->window->duration_minutes;

        if ($minutes < 60) {
            return $minutes . ' menit';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? $hours . ' jam' : $hours . ' jam ' . $rest . ' menit';
    }
}
