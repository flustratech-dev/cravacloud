<?php

namespace App\Notifications;

use App\Models\VpsInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pelanggan saat admin mengubah username SSH pada VPS aktif.
 */
class VpsSshUsernameChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public VpsInstance $instance,
        public string $oldUsername,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function serverName(): string
    {
        return $this->instance->hostname ?: ('VPS #' . $this->instance->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Username SSH Server Anda Diperbarui — ' . $this->serverName();

        $body = "Halo " . ($notifiable->full_name ?? 'Pelanggan CravaCloud') . ",\n\n"
            . "Tim CravaCloud telah memperbarui username login SSH untuk server {$this->serverName()}.\n\n"
            . "Username lama: {$this->oldUsername}\n"
            . "Username baru: {$this->instance->ssh_user}\n"
            . "Perintah login: {$this->instance->ssh_command}\n\n"
            . "Password tidak berubah dan dapat dilihat di Dashboard > Detail VPS > tab Akses (klik ikon mata).\n\n"
            . "Jika Anda tidak merasa meminta perubahan ini, silakan hubungi support kami.\n\n"
            . "Terima kasih,\nTim CravaCloud";

        return $this->newMailMessage()
            ->subject($subject)
            ->view('emails.templated', [
                'user' => $notifiable,
                'subject' => $subject,
                'bodyText' => $body,
                'badgeText' => 'AKSES SERVER',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vps_instance_id' => $this->instance->id,
            'hostname' => $this->instance->hostname,
            'old_ssh_username' => $this->oldUsername,
            'ssh_username' => $this->instance->ssh_user,
        ];
    }
}
