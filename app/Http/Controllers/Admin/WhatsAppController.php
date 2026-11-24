<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Services\WhatsAppGateway;
use App\Services\WhatsAppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Pemantauan & Pengujian WhatsApp Gateway di Panel Admin CravaCloud.
 *
 * Seluruh kredensial dan konfigurasi gateway dibaca penuh dari .env (WA_GATEWAY_*).
 */
class WhatsAppController extends Controller
{
    use LogsAdminAudit;

    public function index(): View
    {
        return view('admin.whatsapp', [
            'gatewayUrl' => rtrim((string) config('whatsapp.url', 'https://wa.vexahostcloud.my.id'), '/'),
            'pengaturan' => WhatsAppSettings::summary(),
            'webhookUrl' => url('/api/webhooks/whatsapp'),
            'webhookConfigured' => filled(config('whatsapp.webhook_secret')),
        ]);
    }

    /**
     * Endpoint status live gateway (sesi terhubung, daftar sesi, dan kuota pesan).
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json(WhatsAppGateway::healthCheck($request->boolean('fresh')));
    }

    /**
     * Kirim pesan uji ke nomor atau ID grup tujuan untuk memvalidasi integrasi gateway.
     */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'   => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        if (! config('whatsapp.enabled')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengiriman WhatsApp sedang nonaktif. Atur WA_GATEWAY_ENABLED=true di file .env.',
            ], 422);
        }

        if (! config('whatsapp.key')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'WA_GATEWAY_KEY belum diisi pada file .env.',
            ], 422);
        }

        $phone = WhatsAppGateway::normalize($data['phone']);

        if ($phone === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Nomor tujuan tidak valid. Masukkan format 08xx, 62xx, +62xx, atau ID grup (@g.us).',
            ], 422);
        }

        $result = WhatsAppGateway::sendWithResult($phone, $data['message']);

        if (! $result['ok']) {
            if ($result['http_status'] === null) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gateway tidak dapat dihubungi: ' . ($result['error'] ?? 'Koneksi gagal.'),
                ], 502);
            }

            return response()->json(array_filter([
                'status'      => 'error',
                'message'     => $result['error'] ?? 'Gateway menolak pesan.',
                'http_status' => $result['http_status'],
                'retry_after' => $result['retry_after'],
            ], static fn ($value) => $value !== null), 422);
        }

        $messageId = $result['id'] ?? 'OK';
        $queueStatus = $result['status'] ?? 'queued';
        $target = $result['to'] ?? $phone;

        $this->audit('whatsapp.test_sent', 'Mengirim pesan uji WhatsApp Admin ke ' . WhatsAppGateway::mask($target) . '.', null, [
            'message_id'   => $messageId,
            'queue_status' => $queueStatus,
            'scheduled_at' => $result['scheduled_at'],
        ]);

        Log::info('Uji kirim WhatsApp berhasil', [
            'oleh'         => $request->user()?->id,
            'tujuan'       => WhatsAppGateway::mask($target),
            'message_id'   => $messageId,
            'queue_status' => $queueStatus,
        ]);

        return response()->json([
            'status'       => 'ok',
            'message'      => 'Pesan uji berhasil diterima oleh WhatsApp Gateway dan masuk dalam antrean kirim. Silakan periksa WhatsApp tujuan.',
            'to'           => $target,
            'message_id'   => $messageId,
            'queue_status' => $queueStatus,
            'scheduled_at' => $result['scheduled_at'],
        ]);
    }
}
