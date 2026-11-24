<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien VexaHost / CravaCloud WhatsApp Gateway (REST API v1).
 *
 * Prinsip: Pengiriman WhatsApp tidak boleh pernah menggagalkan proses bisnis
 * (order, invoice, atau provisioning). Semua kegagalan dicatat ke log sistem
 * dan mengembalikan nilai aman tanpa melempar exception ke pemanggil.
 */
class WhatsAppGateway
{
    public const MAX_MESSAGE_LENGTH = 4096;

    public const MAX_CAPTION_LENGTH = 1024;

    public const MAX_MEDIA_BYTES = 16 * 1024 * 1024;

    public const MAX_BULK_RECIPIENTS = 1000;

    public const HEALTH_CACHE_PREFIX = 'whatsapp:health:';

    public const LAST_FAILED_CACHE_KEY = 'whatsapp:last_failed_count';

    public const LAST_SESSION_EVENT_CACHE_KEY = 'whatsapp:webhook:last_session_status';

    /**
     * Mengirim satu pesan teks ke nomor WhatsApp atau chat ID grup tujuan.
     *
     * @param  string|null  $phone  Nomor tujuan atau chat ID grup (@g.us)
     * @param  string  $message  Isi pesan teks (maks 4096 karakter)
     * @param  string|null  $sessionId  ID sesi gateway opsional
     * @return bool True jika diterima oleh gateway untuk diantrekan (200/202)
     */
    public static function send(?string $phone, string $message, ?string $sessionId = null): bool
    {
        return self::sendWithResult($phone, $message, $sessionId)['ok'];
    }

    /**
     * Mengirim satu pesan teks dan mengembalikan metadata hasil dari gateway (id, status, scheduled_at).
     *
     * @return array{
     *     ok: bool,
     *     http_status: ?int,
     *     id: ?string,
     *     status: ?string,
     *     scheduled_at: ?string,
     *     session_id: ?string,
     *     to: ?string,
     *     error: ?string,
     *     retry_after: ?int
     * }
     */
    public static function sendWithResult(?string $phone, string $message, ?string $sessionId = null): array
    {
        $key = config('whatsapp.key');

        if (! config('whatsapp.enabled')) {
            return self::failedResult(error: 'Pengiriman WhatsApp dinonaktifkan di konfigurasi (.env).');
        }

        if (! $key) {
            return self::failedResult(error: 'WA_GATEWAY_KEY belum diisi di file .env.');
        }

        $normalizedPhone = self::normalize($phone);
        $body = trim($message);

        if ($normalizedPhone === null || $body === '') {
            return self::failedResult(error: 'Nomor tujuan tidak valid atau isi pesan kosong.');
        }

        $body = mb_substr($body, 0, self::MAX_MESSAGE_LENGTH);

        try {
            $payload = array_filter([
                'session_id' => $sessionId ?: config('whatsapp.session'),
                'to' => $normalizedPhone,
                'message' => $body,
            ], static fn ($value) => $value !== null && $value !== '');

            $response = self::request((string) $key)->post('/api/v1/messages/text', $payload);
        } catch (\Throwable $e) {
            Log::warning('Gateway WhatsApp tidak bisa dihubungi', [
                'phone' => self::mask($normalizedPhone),
                'error' => $e->getMessage(),
            ]);

            return self::failedResult(to: $normalizedPhone, error: $e->getMessage());
        }

        if ($response->successful()) {
            return [
                'ok' => true,
                'http_status' => $response->status(),
                'id' => $response->json('data.id') ?? $response->json('data.message_id'),
                'status' => $response->json('data.status') ?? 'queued',
                'scheduled_at' => $response->json('data.scheduled_at'),
                'session_id' => $response->json('data.session_id'),
                'to' => $response->json('data.to') ?? $normalizedPhone,
                'error' => null,
                'retry_after' => null,
            ];
        }

        $retryAfterHeader = $response->header('Retry-After');
        $retryAfter = is_numeric($retryAfterHeader) ? (int) $retryAfterHeader : null;
        $errorMessage = $response->json('error.message') ?? $response->body();

        Log::warning('Gateway WhatsApp menolak pesan teks', array_filter([
            'phone' => self::mask($normalizedPhone),
            'status' => $response->status(),
            'error' => $errorMessage,
            'retry_after' => $retryAfter,
        ], static fn ($value) => $value !== null));

        return self::failedResult(
            httpStatus: $response->status(),
            to: $normalizedPhone,
            error: is_string($errorMessage) && $errorMessage !== '' ? $errorMessage : 'Gateway menolak permintaan.',
            retryAfter: $retryAfter,
        );
    }

    /**
     * Mengirim satu pesan ke banyak nomor sekaligus lewat /api/v1/messages/bulk (maks 1000 per batch).
     * Gateway mengatur jeda antar pesan secara otomatis.
     *
     * @param  array<int, string|null>  $phones
     */
    public static function broadcast(array $phones, string $message, ?string $sessionId = null): bool
    {
        $key = config('whatsapp.key');

        if (! config('whatsapp.enabled') || ! $key) {
            return false;
        }

        $targets = array_values(array_unique(array_filter(array_map(self::normalize(...), $phones))));
        $body = trim($message);

        if ($targets === [] || $body === '') {
            return false;
        }

        $body = mb_substr($body, 0, self::MAX_MESSAGE_LENGTH);
        $allSucceeded = true;

        foreach (array_chunk($targets, self::MAX_BULK_RECIPIENTS) as $chunk) {
            try {
                $payload = array_filter([
                    'session_id' => $sessionId ?: config('whatsapp.session'),
                    'to' => $chunk,
                    'message' => $body,
                ], static fn ($value) => $value !== null && $value !== '');

                $response = self::request((string) $key)->post('/api/v1/messages/bulk', $payload);

                if (! $response->successful()) {
                    $allSucceeded = false;
                    Log::warning('Gateway WhatsApp menolak pengiriman bulk', [
                        'count' => count($chunk),
                        'status' => $response->status(),
                        'error' => $response->json('error.message') ?? $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                $allSucceeded = false;
                Log::warning('Broadcast WhatsApp gagal menghubungi gateway', [
                    'count' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $allSucceeded;
    }

    /**
     * Mengirim pesan menggunakan template yang tersimpan di dashboard gateway.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public static function template(?string $phone, string $template, array $variables = [], ?string $sessionId = null): bool
    {
        $key = config('whatsapp.key');

        if (! config('whatsapp.enabled') || ! $key) {
            return false;
        }

        $normalizedPhone = self::normalize($phone);
        $slug = trim($template);

        if ($normalizedPhone === null || $slug === '') {
            return false;
        }

        $payload = [
            'to' => $normalizedPhone,
            'template' => $slug,
        ];

        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        $resolvedSession = $sessionId ?: config('whatsapp.session');
        if (is_string($resolvedSession) && $resolvedSession !== '') {
            $payload['session_id'] = $resolvedSession;
        }

        try {
            $response = self::request((string) $key)->post('/api/v1/messages/template', $payload);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Gateway WhatsApp menolak pengiriman template', [
                'phone' => self::mask($normalizedPhone),
                'template' => $slug,
                'status' => $response->status(),
                'error' => $response->json('error.message') ?? $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('Pengiriman template WhatsApp gagal', [
                'template' => $slug,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mengirim pesan dengan lampiran media (maks 16 MB) via multipart/form-data.
     */
    public static function sendMedia(?string $phone, string $filePath, string $type = 'document', ?string $caption = null, ?string $sessionId = null): bool
    {
        $key = config('whatsapp.key');

        if (! config('whatsapp.enabled') || ! $key) {
            return false;
        }

        $normalizedPhone = self::normalize($phone);

        if ($normalizedPhone === null || ! is_file($filePath) || ! is_readable($filePath)) {
            return false;
        }

        $size = @filesize($filePath);
        if ($size === false || $size <= 0 || $size > self::MAX_MEDIA_BYTES) {
            Log::warning('Ukuran berkas media WhatsApp melebihi batas atau kosong', [
                'size' => $size,
            ]);

            return false;
        }

        $resolvedType = in_array($type, ['image', 'document', 'video', 'audio'], true) ? $type : 'document';
        $payload = [
            'to' => $normalizedPhone,
            'type' => $resolvedType,
        ];

        if ($caption !== null && trim($caption) !== '') {
            $payload['caption'] = mb_substr(trim($caption), 0, self::MAX_CAPTION_LENGTH);
        }

        $resolvedSession = $sessionId ?: config('whatsapp.session');
        if (is_string($resolvedSession) && $resolvedSession !== '') {
            $payload['session_id'] = $resolvedSession;
        }

        $stream = @fopen($filePath, 'rb');
        if (! is_resource($stream)) {
            return false;
        }

        try {
            $response = self::request((string) $key)
                ->attach('file', $stream, basename($filePath))
                ->post('/api/v1/messages/media', $payload);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Gateway WhatsApp menolak media', [
                'phone' => self::mask($normalizedPhone),
                'status' => $response->status(),
                'error' => $response->json('error.message') ?? $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('Pengiriman media WhatsApp gagal', [
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Mengambil daftar sesi WhatsApp pada workspace (/api/v1/sessions).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sessions(): array
    {
        $key = config('whatsapp.key');

        if (! $key) {
            return [];
        }

        try {
            $response = self::request((string) $key)->get('/api/v1/sessions');

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json('data');

            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            Log::warning('Gagal mengambil daftar sesi WhatsApp', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Memeriksa status pengiriman satu pesan berdasarkan ULID (/api/v1/messages/{id}).
     *
     * @return array<string, mixed>|null
     */
    public static function messageStatus(string $messageId): ?array
    {
        $key = config('whatsapp.key');
        $id = trim($messageId);

        if (! $key || $id === '') {
            return null;
        }

        try {
            $response = self::request((string) $key)->get('/api/v1/messages/' . rawurlencode($id));

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json('data');

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('Gagal memeriksa status pesan WhatsApp', [
                'message_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Memeriksa kesehatan gateway (/api/v1/health) dengan cache 30 detik (kecuali $fresh = true).
     *
     * @return array<string, mixed>
     */
    public static function healthCheck(bool $fresh = false): array
    {
        $key = config('whatsapp.key');
        $enabled = (bool) config('whatsapp.enabled');

        if (! $key) {
            return [
                'status' => 'not_configured',
                'enabled' => $enabled,
                'message' => 'WA_GATEWAY_KEY belum diisi di file .env.',
            ];
        }

        $baseUrl = rtrim((string) config('whatsapp.url'), '/');
        if ($baseUrl === '') {
            return [
                'status' => 'not_configured',
                'enabled' => $enabled,
                'message' => 'WA_GATEWAY_URL belum diisi di file .env.',
            ];
        }

        $ttl = max(0, (int) config('whatsapp.health_cache_seconds', 30));
        $cacheKey = self::healthCacheKey($baseUrl, (string) $key);

        if ($fresh) {
            Cache::forget($cacheKey);
        } elseif ($ttl > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeader('X-Api-Key', (string) $key)
                ->connectTimeout((int) config('whatsapp.connect_timeout', 3))
                ->timeout(max((int) config('whatsapp.timeout', 5), 8))
                ->acceptJson()
                ->get('/api/v1/health');
        } catch (\Throwable $e) {
            Log::error('Gagal menghubungi WA Gateway: ' . $e->getMessage());

            return [
                'status' => 'offline',
                'enabled' => $enabled,
                'message' => 'Gateway tidak dapat dihubungi: ' . $e->getMessage(),
                'last_webhook_event' => Cache::get(self::LAST_SESSION_EVENT_CACHE_KEY),
                'checked_at' => now()->toIso8601String(),
            ];
        }

        if ($response->failed()) {
            return [
                'status' => 'error',
                'enabled' => $enabled,
                'http_status' => $response->status(),
                'message' => $response->json('error.message') ?? 'Gateway menolak permintaan.',
                'last_webhook_event' => Cache::get(self::LAST_SESSION_EVENT_CACHE_KEY),
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $data = $response->json('data') ?? [];
        $workspaceStatus = (string) ($data['status'] ?? 'active');
        $connectedCount = (int) ($data['sessions']['connected'] ?? 0);
        $totalCount = (int) ($data['sessions']['total'] ?? 0);

        $status = match (true) {
            $workspaceStatus !== 'active' => 'inactive',
            $connectedCount <= 0 => 'disconnected',
            $connectedCount < $totalCount => 'partial',
            default => 'ready',
        };

        $message = match ($status) {
            'inactive' => 'Workspace WA Gateway berstatus ' . $workspaceStatus . '. Pengiriman ditolak hingga workspace aktif.',
            'partial' => "Sebagian sesi WhatsApp terputus ({$connectedCount} dari {$totalCount} sesi terhubung).",
            default => null,
        };

        $failedNow = (int) ($data['usage']['messages_failed'] ?? 0);
        $prevFailed = Cache::get(self::LAST_FAILED_CACHE_KEY);
        $failureSpike = $prevFailed !== null && ($failedNow - (int) $prevFailed) > 10;
        Cache::put(self::LAST_FAILED_CACHE_KEY, $failedNow, now()->addDay());

        $sessionList = array_values(array_map(static function (array $session): array {
            return [
                'id' => $session['id'] ?? null,
                'name' => $session['name'] ?? null,
                'status' => $session['status'] ?? null,
                'phone_number' => $session['phone_number'] ?? null,
                'push_name' => $session['push_name'] ?? null,
                'connected_at' => $session['connected_at'] ?? null,
                'last_error' => $session['last_error'] ?? null,
            ];
        }, array_filter(self::sessions(), 'is_array')));

        $result = [
            'status' => $status,
            'enabled' => $enabled,
            'message' => $message,
            'workspace' => $data['workspace'] ?? null,
            'workspace_status' => $workspaceStatus,
            'sessions' => $data['sessions'] ?? null,
            'usage' => $data['usage'] ?? null,
            'session_list' => $sessionList,
            'failure_spike' => $failureSpike,
            'last_webhook_event' => Cache::get(self::LAST_SESSION_EVENT_CACHE_KEY),
            'checked_at' => now()->toIso8601String(),
        ];

        if ($ttl > 0) {
            Cache::put($cacheKey, $result, $ttl);
        }

        return $result;
    }

    public static function flushHealthCache(): void
    {
        $baseUrl = rtrim((string) config('whatsapp.url'), '/');
        $key = (string) config('whatsapp.key');

        if ($baseUrl !== '' && $key !== '') {
            Cache::forget(self::healthCacheKey($baseUrl, $key));
        }
    }

    /**
     * Memverifikasi tanda tangan HMAC-SHA256 webhook masuk (X-VexaHost-Signature).
     */
    public static function verifyWebhookSignature(string $rawPayload, string $signature, ?string $secret = null): bool
    {
        $resolvedSecret = $secret ?? (string) config('whatsapp.webhook_secret', '');

        if ($resolvedSecret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $resolvedSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * Normalisasi nomor telepon ke format internasional 62xxxxxxxxxx,
     * atau mempertahankan chat ID grup WhatsApp (*@g.us).
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^[0-9-]+@g\.us$/', $trimmed) === 1) {
            return $trimmed;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

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
        if (str_ends_with($phone, '@g.us')) {
            $local = substr($phone, 0, -5);

            return (strlen($local) <= 4 ? '****' : substr($local, 0, 4) . '****') . '@g.us';
        }

        if (strlen($phone) <= 6) {
            return '****';
        }

        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }

    private static function request(string $apiKey): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('whatsapp.url'), '/'))
            ->withHeader('X-Api-Key', $apiKey)
            ->connectTimeout((int) config('whatsapp.connect_timeout', 3))
            ->timeout((int) config('whatsapp.timeout', 5))
            ->acceptJson();
    }

    private static function healthCacheKey(string $baseUrl, string $apiKey): string
    {
        return self::HEALTH_CACHE_PREFIX . sha1($baseUrl . '|' . $apiKey);
    }

    /**
     * @return array{
     *     ok: bool,
     *     http_status: ?int,
     *     id: ?string,
     *     status: ?string,
     *     scheduled_at: ?string,
     *     session_id: ?string,
     *     to: ?string,
     *     error: ?string,
     *     retry_after: ?int
     * }
     */
    private static function failedResult(
        ?int $httpStatus = null,
        ?string $to = null,
        ?string $error = null,
        ?int $retryAfter = null,
    ): array {
        return [
            'ok' => false,
            'http_status' => $httpStatus,
            'id' => null,
            'status' => null,
            'scheduled_at' => null,
            'session_id' => null,
            'to' => $to,
            'error' => $error,
            'retry_after' => $retryAfter,
        ];
    }
}
