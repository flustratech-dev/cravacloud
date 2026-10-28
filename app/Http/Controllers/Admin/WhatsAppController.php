<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Services\WhatsAppGateway;
use App\Services\WhatsAppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Pengelolaan WhatsApp Gateway di Panel Admin CravaCloud.
 *
 * Mengontrol kredensial, koneksi, status live, dan pengujian pesan langsung dari UI admin.
 */
class WhatsAppController extends Controller
{
    use LogsAdminAudit;

    public function index(): View
    {
        return view('admin.whatsapp', [
            'gatewayUrl' => rtrim(config('whatsapp.url', 'https://wa.cravacloud.id'), '/'),
            'pengaturan' => WhatsAppSettings::summary(),
        ]);
    }

    /**
     * Menyimpan kredensial dan pengaturan WhatsApp Gateway ke basis data.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'wa_enabled' => ['nullable', 'boolean'],
            'wa_url'     => ['nullable', 'string', 'max:255', 'url'],
            'wa_key'     => ['nullable', 'string', 'max:255'],
            'wa_session' => ['nullable', 'string', 'max:100'],
            'wa_timeout' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], [
            'wa_url.url' => 'Format URL Gateway tidak valid (harus diawali http:// atau https://).',
        ]);

        $data['wa_enabled'] = $request->boolean('wa_enabled');

        WhatsAppSettings::save($data);

        Log::info('Pengaturan WhatsApp Gateway diperbarui', [
            'oleh' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.whatsapp.index')
            ->with('success', 'Pengaturan WhatsApp Gateway berhasil disimpan dan langsung aktif.');
    }

    /**
     * Menghapus nilai pengaturan database agar kembali mengikuti .env / config default.
     */
    public function forgetSetting(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string', 'in:' . implode(',', array_keys(WhatsAppSettings::FIELDS))],
        ]);

        WhatsAppSettings::forget($validated['field']);

        Log::info('Pengaturan WhatsApp Gateway dikembalikan ke default', [
            'oleh'  => $request->user()?->id,
            'kolom' => $validated['field'],
        ]);

        return redirect()
            ->route('admin.whatsapp.index')
            ->with('success', "Pengaturan {$validated['field']} dikembalikan ke nilai default.");
    }

    /**
     * Endpoint status live gateway (sesi terhubung dan kuota pesan).
     */
    public function status(): JsonResponse
    {
        $health = WhatsAppGateway::healthCheck();

        return response()->json($health);
    }

    /**
     * Kirim pesan uji ke nomor tujuan untuk memvalidasi integrasi gateway.
     */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'   => ['required', 'string', 'max:25'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $key = config('whatsapp.key');
        $session = config('whatsapp.session');

        if (! config('whatsapp.enabled')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pengiriman WhatsApp sedang nonaktif. Aktifkan saklar "Aktifkan Pengiriman WhatsApp" di halaman ini terlebih dahulu.',
            ], 422);
        }

        if (! $key) {
            return response()->json([
                'status'  => 'error',
                'message' => 'API Key Gateway belum diisi. Masukkan API Key Anda pada formulir kredensial di atas.',
            ], 422);
        }

        $phone = WhatsAppGateway::normalize($data['phone']);

        if ($phone === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Nomor tujuan tidak valid. Masukkan nomor dengan format 08xx, 62xx, atau +62xx.',
            ], 422);
        }

        try {
            $response = Http::baseUrl(rtrim(config('whatsapp.url'), '/'))
                ->withHeader('X-Api-Key', $key)
                ->connectTimeout(5)
                ->timeout(15)
                ->acceptJson()
                ->post('/api/v1/messages/text', array_filter([
                    'session_id' => $session,
                    'to'         => $phone,
                    'message'    => $data['message'],
                ]));
        } catch (\Throwable $e) {
            Log::warning('Uji kirim WhatsApp gagal menghubungi gateway', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Gateway tidak dapat dihubungi: ' . $e->getMessage(),
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'status'      => 'error',
                'message'     => $response->json('error.message') ?? 'Gateway menolak pesan.',
                'http_status' => $response->status(),
            ], 422);
        }

        $messageId = $response->json('data.id') ?? $response->json('data.message_id') ?? 'OK';

        Log::info('Uji kirim WhatsApp berhasil', [
            'oleh'       => $request->user()?->id,
            'tujuan'     => WhatsAppGateway::mask($phone),
            'message_id' => $messageId,
        ]);

        return response()->json([
            'status'     => 'ok',
            'message'    => 'Pesan uji berhasil diterima oleh WhatsApp Gateway dan masuk dalam antrean kirim. Silakan periksa nomor WhatsApp tujuan.',
            'to'         => $phone,
            'message_id' => $messageId,
        ]);
    }
}
