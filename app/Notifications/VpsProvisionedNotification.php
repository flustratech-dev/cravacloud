<?php

namespace App\Notifications;

use App\Models\VpsInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VpsProvisionedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public VpsInstance $instance
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->instance->loadMissing(['order.vpsSpec']);
        $hostname = $this->instance->hostname;
        $ip = $this->instance->public_ip ?? 'Aktif';

        return $this->newMailMessage()
            ->subject("🚀 Server VPS Anda Telah Aktif — {$hostname} ({$ip})")
            ->view('emails.vps-provisioned', [
                'user' => $notifiable,
                'instance' => $this->instance,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vps_instance_id' => $this->instance->id,
            'hostname' => $this->instance->hostname,
            'public_ip' => $this->instance->public_ip,
            'status' => $this->instance->status,
        ];
    }
}
