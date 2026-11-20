<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 5C — sent to the customer after a self-service bundle upgrade completes.
 * White-label: only CravaCloud plan names and the new resource figures; the
 * upstream vendor / bundle id never appears.
 */
class VpsUpgradedNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(public VpsInstance $instance, public ?VpsSpec $spec = null)
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

    private function planName(): string
    {
        return $this->spec?->name ?: 'paket baru';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Upgrade Server Berhasil — '.$this->serverName();
        $dashboardUrl = url('/dashboard/vps/'.$this->instance->id);

        $body = 'Halo '.($notifiable->full_name ?? 'Pelanggan CravaCloud').",\n\n"
            ."Server {$this->serverName()} telah berhasil di-upgrade ke {$this->planName()}.\n\n"
            ."Spesifikasi baru:\n"
            ."• CPU: {$this->instance->cpu} vCPU\n"
            ."• RAM: {$this->instance->ram} GB\n"
            ."• Disk: {$this->instance->disk} GB\n\n"
            ."Masa aktif server tidak berubah; upgrade hanya menambah sumber daya "
            ."untuk sisa periode berjalan.\n\n"
            ."Detail server dapat dilihat di Dashboard:\n{$dashboardUrl}\n\n"
            ."Terima kasih,\nTim CravaCloud";

        return $this->newMailMessage()
            ->subject($subject)
            ->view('emails.templated', [
                'user' => $notifiable,
                'subject' => $subject,
                'bodyText' => $body,
                'badgeText' => 'UPGRADE SERVER',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vps_instance_id' => $this->instance->id,
            'hostname' => $this->instance->hostname,
            'plan' => $this->planName(),
            'cpu' => $this->instance->cpu,
            'ram' => $this->instance->ram,
            'disk' => $this->instance->disk,
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $appUrl = rtrim((string) config('app.url', 'https://cravacloud.id'), '/');

        return WhatsAppMessage::create(implode("\n", [
            'Halo *'.($notifiable->full_name ?? 'Pelanggan CravaCloud').'*,',
            '',
            '🚀 *UPGRADE SERVER BERHASIL*',
            "Server: `{$this->serverName()}`",
            "Paket baru: *{$this->planName()}*",
            '',
            "• CPU: `{$this->instance->cpu} vCPU`",
            "• RAM: `{$this->instance->ram} GB`",
            "• Disk: `{$this->instance->disk} GB`",
            '',
            'Masa aktif tidak berubah; sumber daya bertambah untuk sisa periode berjalan.',
            "{$appUrl}/dashboard/vps/{$this->instance->id}",
            '',
            '_CravaCloud Solutions_',
        ]));
    }
}