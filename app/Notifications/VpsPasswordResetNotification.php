<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\VpsInstance;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 5B — sent to the customer when their server login password is reset
 * (self-service or admin). White-label; the new password is NEVER included
 * in the message — the customer reveals it from the dashboard (encrypted at
 * rest, rate-limited reveal).
 */
class VpsPasswordResetNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(public VpsInstance $instance)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if (config('whatsapp.enabled') && ! empty($notifiable->phone)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    private function serverName(): string
    {
        return $this->instance->hostname ?: ('VPS #'.$this->instance->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Password Server Anda Diperbarui — '.$this->serverName();
        $dashboardUrl = url('/dashboard/vps/'.$this->instance->id);

        $body = 'Halo '.($notifiable->full_name ?? 'Pelanggan CravaCloud').",\n\n"
            ."Password login untuk server {$this->serverName()} telah berhasil diperbarui.\n\n"
            ."Username: {$this->instance->ssh_user}\n"
            ."Perintah login: {$this->instance->ssh_command}\n\n"
            ."Demi keamanan, password baru tidak dikirim melalui email/WhatsApp. "
            ."Lihat password baru di Dashboard > Detail VPS > tab Akses (klik ikon mata):\n"
            ."{$dashboardUrl}\n\n"
            ."Segera ganti password setelah login pertama kali. "
            ."Jika Anda tidak merasa meminta perubahan ini, hubungi support kami.\n\n"
            ."Terima kasih,\nTim CravaCloud";

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
            'ssh_username' => $this->instance->ssh_user,
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $appUrl = rtrim((string) config('app.url', 'https://cravacloud.id'), '/');

        return WhatsAppMessage::create(implode("\n", [
            'Halo *'.($notifiable->full_name ?? 'Pelanggan CravaCloud').'*,',
            '',
            '🔐 *PASSWORD SERVER DIPERBARUI*',
            "Server: `{$this->serverName()}`",
            '',
            "• *Username:* `{$this->instance->ssh_user}`",
            '',
            '💻 *Login SSH:*',
            "`{$this->instance->ssh_command}`",
            '',
            'Password baru tidak dikirim demi keamanan. Lihat di Dashboard > Detail VPS > tab Akses (klik ikon mata):',
            "{$appUrl}/dashboard/vps/{$this->instance->id}",
            '',
            '_CravaCloud Solutions_',
        ]));
    }
}