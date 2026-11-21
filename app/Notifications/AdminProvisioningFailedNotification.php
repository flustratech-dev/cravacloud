<?php

namespace App\Notifications;

use App\Models\ProvisioningTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminProvisioningFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public ProvisioningTask $task,
        public string $errorMessage
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject("🚨 [PROVISIONING GAGAL] Order #{$this->task->order_id} — Butuh Intervensi Manual")
            ->view('emails.admin-provisioning-failed', [
                'task' => $this->task,
                'errorMessage' => $this->errorMessage,
            ]);
    }
}
