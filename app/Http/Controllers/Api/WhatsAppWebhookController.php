<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminAlertService;
use App\Services\WhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Penerima webhook resmi VexaHost WA Gateway (WEBHOOK.md & MEMANTAU_KONEKSI.md).
 *
 * Seluruh payload diverifikasi memakai HMAC-SHA256 atas body mentah sebelum
 * diproses (header X-VexaHost-Signature), dideduplikasi lewat delivery_id,
 * dan membalas 204 secepat mungkin.
 */
class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request): Response|JsonResponse
    {
        $secret = (string) config('whatsapp.webhook_secret', '');

        if ($secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp webhook secret belum dikonfigurasi.',
            ], 503);
        }

        $rawPayload = $request->getContent();
        $signature = (string) $request->header('X-VexaHost-Signature', '');

        if (! WhatsAppGateway::verifyWebhookSignature($rawPayload, $signature, $secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 401);
        }

        $decoded = json_decode($rawPayload, true);
        $payload = is_array($decoded) ? $decoded : $request->all();

        $event = (string) ($request->header('X-VexaHost-Event') ?: ($payload['event'] ?? ''));
        $deliveryId = (string) ($payload['delivery_id'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if ($deliveryId !== '' && ! Cache::add('whatsapp:webhook:delivery:' . sha1($deliveryId), true, 86400)) {
            return response()->noContent();
        }

        match ($event) {
            'session.status' => $this->handleSessionStatus($data),
            'message.status' => $this->handleMessageStatus($data),
            'message.received' => $this->handleMessageReceived($data),
            default => Log::debug('Webhook WA Gateway event diabaikan', ['event' => $event]),
        };

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleSessionStatus(array $data): void
    {
        $status = (string) ($data['status'] ?? '');
        $reason = $data['reason'] ?? null;

        Cache::put(WhatsAppGateway::LAST_SESSION_EVENT_CACHE_KEY, [
            'event' => 'session.status',
            'session_id' => $data['session_id'] ?? null,
            'name' => $data['name'] ?? null,
            'status' => $status !== '' ? $status : null,
            'phone_number' => $data['phone_number'] ?? null,
            'reason' => $reason,
            'received_at' => now()->toIso8601String(),
        ], now()->addDay());

        WhatsAppGateway::flushHealthCache();

        $requiresManualAction = $status === 'failed'
            || ($status === 'disconnected' && in_array($reason, ['logged_out', 'credentials_missing', 'banned'], true));

        if (! $requiresManualAction) {
            return;
        }

        try {
            app(AdminAlertService::class)->alert(
                'whatsapp.session_disconnected',
                [
                    'severity' => 'CRITICAL',
                    'title' => 'Sesi WhatsApp Gateway Terputus',
                    'details' => [
                        'Sesi' => (string) ($data['name'] ?? '-'),
                        'Nomor' => (string) ($data['phone_number'] ?? '-'),
                        'Status' => $status,
                        'Alasan' => (string) ($reason ?: '-'),
                    ],
                    'body' => 'Nomor WhatsApp Gateway memerlukan tindakan manual (scan QR ulang / cek ponsel).',
                    'action_url' => '/admin/whatsapp',
                    'action_label' => 'Buka Status WA Gateway',
                    'send_email' => true,
                    'send_whatsapp' => false,
                ],
                dedupeKey: 'wa-session-' . ($data['session_id'] ?? 'default') . '-' . $status . '-' . ($reason ?: 'none'),
                cooldownSeconds: 1800,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim email alert sesi WA terputus', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleMessageStatus(array $data): void
    {
        if (($data['status'] ?? null) === 'failed') {
            Log::warning('Webhook WA: pesan gagal terkirim', [
                'message_id' => $data['message_id'] ?? null,
                'to' => isset($data['to']) ? WhatsAppGateway::mask((string) $data['to']) : null,
                'error' => $data['error'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleMessageReceived(array $data): void
    {
        if (! empty($data['is_backlog'])) {
            Log::debug('Webhook WA: pesan backlog saat reconnect dilewati', [
                'message_id' => $data['message_id'] ?? null,
                'session_id' => $data['session_id'] ?? null,
            ]);

            return;
        }

        Log::info('Webhook WA: pesan masuk diterima', [
            'message_id' => $data['message_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'from' => isset($data['from']) ? WhatsAppGateway::mask((string) $data['from']) : null,
            'type' => $data['type'] ?? 'text',
            'is_group' => (bool) ($data['is_group'] ?? false),
        ]);
    }
}
