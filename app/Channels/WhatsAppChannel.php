<?php

namespace App\Channels;

use App\Services\WhatsAppGateway;
use App\Services\WhatsAppMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Channel notifikasi WhatsApp untuk Laravel Notifications.
 *
 * Mengirimkan pesan teks atau dokumen langsung ke nomor WhatsApp pelanggan
 * saat notifikasi sistem dipicu.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! config('whatsapp.enabled') || ! config('whatsapp.key')) {
            return;
        }

        $phone = null;

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $phone = $notifiable->routeNotificationFor('whatsapp', $notification);
        }

        if (! $phone && isset($notifiable->phone)) {
            $phone = $notifiable->phone;
        }

        $normalizedPhone = WhatsAppGateway::normalize($phone);

        if (! $normalizedPhone) {
            Log::info('Notifikasi WhatsApp dilewati: nomor tujuan tidak valid atau kosong', [
                'notifiable_id' => $notifiable->id ?? null,
                'raw_phone'     => $phone,
            ]);

            return;
        }

        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        try {
            $message = $notification->toWhatsApp($notifiable);

            if (! $message) {
                return;
            }

            if ($message instanceof WhatsAppMessage) {
                $message->sendTo($normalizedPhone);
            } elseif (is_string($message) && trim($message) !== '') {
                WhatsAppGateway::send($normalizedPhone, $message);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal memproses pengiriman notifikasi WhatsApp', [
                'notification' => get_class($notification),
                'notifiable_id' => $notifiable->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
