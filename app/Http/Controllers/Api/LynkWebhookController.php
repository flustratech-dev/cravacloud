<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\WebhookEvent;
use App\Services\OrderStateMachine;
use App\Services\Payments\LynkPaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LynkWebhookController extends Controller
{
    public function __construct(
        protected LynkPaymentProcessor $processor
    ) {
    }

    /**
     * Handle incoming webhook from Lynk.id
     * Spesifikasi: https://documenter.getpostman.com/view/43601478/2sBXc8o3kn
     */
    public function handle(Request $request)
    {
        // 1. Handle HTTP GET / HEAD ping check
        if ($request->isMethod('get') || $request->isMethod('head')) {
            return response()->json([
                'success' => true,
                'message' => 'Lynk webhook endpoint is active.',
            ], 200);
        }

        $payload = $request->all();

        // Ekstraksi dipakai bersama dengan LynkPaymentProcessor agar nilai yang
        // diverifikasi signature-nya identik dengan nilai yang diproses.
        $fields = LynkPaymentProcessor::extract($payload);
        $event = $fields['event'];
        $data = $fields['data'];
        $messageAction = $fields['message_action'];
        $messageData = $fields['message_data'];
        $refId = $fields['ref_id'];
        $grandTotal = $fields['grand_total'];
        $messageId = $fields['message_id'];
        $customerEmail = $fields['customer_email'];

        // 2. Handle Test Ping / Test Event dari Dashboard Lynk.id (tombol "Test URL")
        $isTestRequest = empty($payload)
            || in_array($event, ['test_event', 'ping', 'test', 'test_webhook', 'test_notification', 'webhook.test', 'webhook_test'], true)
            || str_contains($event, 'test')
            || str_contains($event, 'ping')
            || $request->query('test') == '1'
            || !empty($payload['test'])
            || !empty($payload['is_test'])
            || !empty($data['test'])
            || !empty($messageData['test'])
            || str_contains(strtolower($refId), 'test')
            || str_contains(strtolower($refId), 'dummy')
            || str_contains(strtolower($refId), 'mock')
            || str_contains(strtolower($messageAction), 'TEST')
            || in_array($customerEmail, ['user@lynk.id', 'test@lynk.id', 'email@contoh.id'], true)
            || (($request->header('User-Agent') && str_contains(strtolower($request->header('User-Agent')), 'lynk')) && empty($messageData));

        if ($isTestRequest) {
            Log::info('Lynk test webhook received successfully', [
                'ip' => $request->ip(),
                'payload' => $payload,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Lynk test webhook received successfully.',
            ], 200);
        }

        // PHASE 4 - kunci dibaca dari registry gateway (dapat dirotasi dari panel),
        // jatuh kembali ke .env bila registry belum diisi.
        $merchantKey = trim(
            (string) PaymentGateway::credential('lynk', 'merchant_key', config('services.lynk.merchant_key')),
            " \t\n\r\0\x0B\"'"
        );

        // Guard 1: Merchant key harus terkonfigurasi di server
        if (empty($merchantKey)) {
            Log::critical('Lynk webhook rejected: LYNK_MERCHANT_KEY not configured in .env');
            return response()->json([
                'success' => false,
                'message' => 'Lynk payment gateway not configured on server.',
            ], 500);
        }

        // Ekstraksi header signature
        $receivedSignature = trim((string) ($request->header('X-Lynk-Signature')
            ?? $request->header('x-lynk-signature')
            ?? ''));

        if (empty($receivedSignature)) {
            Log::warning('Lynk webhook missing X-Lynk-Signature header', [
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Lynk-Signature header.',
            ], 401);
        }

        // Guard 2: Validasi signature
        // Lynk formula standard: sha256(amount + ref_id + message_id + secret_key)
        $possibleSignatures = [
            hash('sha256', $grandTotal . $refId . $messageId . $merchantKey),
            hash('sha256', $grandTotal . $refId . $merchantKey),
            hash('sha256', $refId . $grandTotal . $messageId . $merchantKey),
        ];

        if (is_numeric($grandTotal)) {
            $intTotal = (string) (int) round((float) $grandTotal);
            $possibleSignatures[] = hash('sha256', $intTotal . $refId . $messageId . $merchantKey);
            $possibleSignatures[] = hash('sha256', $intTotal . $refId . $merchantKey);
        }

        $isValidSignature = false;
        foreach (array_unique($possibleSignatures) as $candidateSignature) {
            if (hash_equals($candidateSignature, $receivedSignature)) {
                $isValidSignature = true;
                break;
            }
        }

        if (!$isValidSignature) {
            Log::warning('Lynk webhook signature mismatch', [
                'refId' => $refId,
                'messageId' => $messageId,
                'grandTotal' => $grandTotal,
                'receivedSignature' => $receivedSignature,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 403);
        }

        // Guard 3: Idempotency check (Cegah proses ulang refId yang sama)
        $existingEvent = WebhookEvent::where('provider', 'lynk')
            ->where('event_id', $refId)
            ->first();

        if ($existingEvent && $existingEvent->processing_status === 'processed') {
            Log::info("Lynk webhook already processed (Idempotent skip): refId={$refId}");
            return response()->json([
                'success' => true,
                'message' => 'Event already processed.',
                'order_id' => $existingEvent->order_id,
            ], 200);
        }

        // PHASE 4 - catat event SEBELUM diproses. Signature sudah sah di titik ini,
        // sehingga event boleh diproses ulang dari panel admin bila pemrosesan gagal.
        // Tanpa pencatatan ini, pembayaran yang gagal hanya tersisa di file log.
        $webhookEvent = WebhookEvent::updateOrCreate(
            [
                'provider' => 'lynk',
                'event_id' => $refId !== '' ? $refId : 'noref-' . Str::uuid(),
            ],
            [
                'event_type' => $event !== '' ? $event : null,
                'signature' => $receivedSignature,
                'signature_verified' => true,
                'ip_address' => $request->ip(),
                'payload' => $payload,
                'processing_status' => 'received',
                'attempts' => $existingEvent ? $existingEvent->attempts + 1 : 1,
            ]
        );

        $result = $this->processor->process($webhookEvent);

        $response = response()->json($result['body'], $result['http']);

        if (function_exists('fastcgi_finish_request')) {
            ignore_user_abort(true);
            $response->send();
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            ignore_user_abort(true);
            $response->send();
            litespeed_finish_request();
        }

        return $response;
    }
}
