<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien CravaCloud WhatsApp Gateway.
 *
 * Menangani pengiriman pesan teks, dokumen/media, dan broadcast ke pengguna.
 *
 * Prinsip: Pengiriman WhatsApp tidak boleh pernah menggagalkan proses bisnis
 * (order, invoice, atau provisioning). Semua kegagalan dicatat ke log sistem
 * dan mengembalikan nilai boolean false tanpa melempar exception ke pemanggil.
 */
class WhatsAppGateway
{
    /**
     * Mengirim satu pesan teks ke pengguna.
     *
     * @param string|null $phone Nomor tujuan
     * @param string $message Isi pesan teks
     * @param string|null $sessionId ID sesi gateway opsional
     * @return bool True jika berhasil diterima oleh gateway
     */
    public static function send(?string $phone, string $message, ?string $sessionId = null): bool
    {
        $key = config('whatsapp.key');

        if (! config('whatsapp.enabled') || ! $key) {
            return false;
        }

        $phone = self::normalize($phone);

        if ($phone === null || trim($message) === '') {
            return false;
        }

        try {
            $response = self::request($key)->post('/api/v1/messages/text', array_filter([
                'session_id' => $sessionId ?? config('whatsapp.session'),
                'to'         => $phone,
                'message'    => $message,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Gateway WhatsApp tidak bisa dihubungi', [
                'phone' => self::mask($phone),
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        Log::warning('Gateway WhatsApp menolak pesan teks', [
            'phone'  => self::mask($phone),
            'status' => $response->status(),
            'error'  => $response->json('error.message') ?? $response->body(),
        ]);

        return false;
    }

    /**
     * Mengirim berkas media (dokumen PDF / gambar) dengan caption opsional.
     *
     * @param string|null $phone Nomor tujuan
     * @param string|resource $fileContent Isi berkas mentah atau resource stream
     * @param string $filename Nama berkas (mis. Invoice-INV-2026.pdf)
     * @param string $type Tipe media: 'document', 'image', 'video', 'audio'
     * @param string|null $caption Teks keterangan di bawah berkas
     * @param string|null $sessionId ID sesi gateway opsional
     * @return bool
     */
    public static function sendMedia(
        ?string $phone,
        mixed $fileContent,
        string $filename,
        string $type = 'document',
        ?string $caption = null,
        ?string $sessionId = null
    ): bool {
        $key = config('whatsapp.key');

        if (! config('whatsapp.enabled') || ! $key) {
            return false;
        }

        $phone = self::normalize($phone);

        if ($phone === null || empty($fileContent)) {
            return false;
        }

        try {
            $req = self::request($key)
                ->timeout(max(15, (int) config('whatsapp.timeout', 10)))
                ->attach('file', $fileContent, $filename);

            $payload = array_filter([
                'session_id' => $sessionId ?? config('whatsapp.session'),
                'to'         => $phone,
                'type'       => $type,
                'caption'    => $caption,
            ], fn ($val) => $val !== null && $val !== '');

            $response = $req->post('/api/v1/messages/media', $payload);
        } catch (\Throwable $e) {
            Log::warning('Gateway WhatsApp gagal mengirim lampiran berkas', [
                'phone'    => self::mask($phone),
                'filename' => $filename,
                'error'    => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        Log::warning('Gateway WhatsApp menolak lampiran berkas', [
            'phone'    => self::mask($phone),
            'filename' => $filename,
            'status'   => $response->status(),
            'error'    => $response->json('error.message') ?? $response->body(),
        ]);

        return false;
    }

    /**
     * Mengirim pesan massal (bulk) ke banyak nomor.
     *
     * @param array<int, string> $phones
     * @param string $message
     * @param string|null $sessionId
     * @return bool
     */
    public static function broadcast(array $phones, string $message, ?string $sessionId = null): bool
    {
        $key = config('whatsapp.key');

        if (! config('whatsapp.enabled') || ! $key) {
            return false;
        }

        $targets = array_values(array_filter(array_map(self::normalize(...), $phones)));

        if ($targets === [] || trim($message) === '') {
            return false;
        }

        try {
            $response = self::request($key)->post('/api/v1/messages/bulk', array_filter([
                'session_id' => $sessionId ?? config('whatsapp.session'),
                'to'         => $targets,
                'message'    => $message,
            ]));

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Broadcast WhatsApp gagal menghubungi gateway', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mengirim memakai template yang tersimpan di gateway.
     *
     * @param string|null $phone
     * @param string $template
     * @param array<string, string> $variables
     * @param string|null $sessionId
     * @return bool
     */
    public static function template(
        ?string $phone,
        string $template,
        array $variables = [],
        ?string $sessionId = null
    ): bool {
        $key = config('whatsapp.key');

        if (! config('whatsapp.enabled') || ! $key) {
            return false;
        }

        $phone = self::normalize($phone);

        if ($phone === null) {
            return false;
        }

        try {
            $response = self::request($key)->post('/api/v1/messages/template', array_filter([
                'session_id' => $sessionId ?? config('whatsapp.session'),
                'to'         => $phone,
                'template'   => $template,
                'variables'  => $variables,
            ]));

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Pengiriman template WhatsApp gagal', [
                'template' => $template,
                'error'    => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Memeriksa kesehatan gateway (/api/v1/health).
     *
     * @return array{status: string, message?: string, workspace?: string, sessions?: array, usage?: array}
     */
    public static function healthCheck(): array
    {
        $key = config('whatsapp.key');

        if (! $key) {
            return [
                'status'  => 'not_configured',
                'message' => 'API key WhatsApp Gateway belum diisi di panel admin.',
            ];
        }

        try {
            $response = Http::baseUrl(rtrim(config('whatsapp.url'), '/'))
                ->withHeader('X-Api-Key', $key)
                ->connectTimeout(config('whatsapp.connect_timeout', 3))
                ->timeout(config('whatsapp.timeout', 8))
                ->acceptJson()
                ->get('/api/v1/health');

            if ($response->failed()) {
                return [
                    'status'  => 'error',
                    'message' => $response->json('error.message') ?? 'Gateway menolak permintaan.',
                ];
            }

            $data = $response->json('data') ?? [];
            $connectedCount = $data['sessions']['connected'] ?? 0;

            return [
                'status'    => $connectedCount > 0 ? 'ready' : 'disconnected',
                'workspace' => $data['workspace'] ?? null,
                'sessions'  => $data['sessions'] ?? null,
                'usage'     => $data['usage'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Gagal menghubungi WA Gateway: ' . $e->getMessage());

            return [
                'status'  => 'offline',
                'message' => 'Gateway tidak dapat dihubungi: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Normalisasi nomor telepon ke format internasional 62xxxxxxxxxx.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62' . $digits;
        }

        return preg_match('/^62[1-9][0-9]{7,13}$/', $digits) === 1 ? $digits : null;
    }

    public static function mask(string $phone): string
    {
        if (strlen($phone) <= 6) {
            return '****';
        }

        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }

    private static function request(string $apiKey): PendingRequest
    {
        return Http::baseUrl(rtrim(config('whatsapp.url'), '/'))
            ->withHeader('X-Api-Key', $apiKey)
            ->connectTimeout((int) config('whatsapp.connect_timeout', 3))
            ->timeout((int) config('whatsapp.timeout', 10))
            ->acceptJson();
    }
}
