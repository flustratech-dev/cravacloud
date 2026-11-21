<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use App\Services\OrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Webhook payment gateway (Midtrans).
 *
 * Kontrak keamanan:
 *   1. MIDTRANS_SERVER_KEY WAJIB di-set. Tanpa itu, endpoint langsung 500.
 *   2. Signature SHA-512 diverifikasi sebelum apapun lainnya.
 *   3. Idempotent: webhook duplikat (provider + transaction_id sama) tidak
 *      pernah memproses ulang. Sekali sukses = terkunci.
 *   4. Amount verify: gross_amount di webhook harus == order.amount.
 *      Kalau tidak match → tolak, catat sebagai anomaly.
 *   5. Semua write dalam DB::transaction + lockForUpdate untuk cegah race.
 *   6. Perubahan status order lewat OrderStateMachine (validated transitions).
 *   7. Raw payload disimpan permanen di webhook_events + payment_transactions
 *      untuk forensik dan replay admin.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(protected OrderStateMachine $orderStateMachine)
    {
    }

    public function handle(Request $request)
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Midtrans payment webhook endpoint is active and ready.',
            ], 200);
        }

        $serverKey = PaymentGateway::credential('midtrans', 'server_key', config('services.midtrans.server_key'));

        // Guard 1: server key WAJIB dikonfigurasi.
        if (empty($serverKey)) {
            Log::critical('Payment webhook rejected: MIDTRANS_SERVER_KEY not configured');
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not configured on server.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
            'status_code' => 'nullable|string',
            'gross_amount' => 'nullable|string',
            'signature_key' => 'required|string',
            'transaction_status' => 'nullable|string',
            'status' => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'payment_type' => 'nullable|string',
            'fraud_status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook payload structure.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $rawOrderId = (string) $validated['order_id'];
        $statusCode = (string) ($validated['status_code'] ?? '');
        $grossAmount = (string) ($validated['gross_amount'] ?? '');
        $incomingSignature = (string) $validated['signature_key'];

        // Guard 2: verifikasi signature SHA-512 Midtrans.
        $expectedSignature = hash('sha512', $rawOrderId . $statusCode . $grossAmount . $serverKey);
        if (!hash_equals($expectedSignature, $incomingSignature)) {
            Log::warning('Payment webhook signature mismatch', [
                'order_id' => $rawOrderId,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature key.',
            ], 403);
        }

        $outcome = $this->processVerifiedPayload($validated, $request->all(), (string) $request->ip());

        $response = response()->json($outcome['body'], $outcome['http']);

        if (!empty($outcome['needs_notification']) && !empty($outcome['order'])) {
            if (function_exists('fastcgi_finish_request')) {
                ignore_user_abort(true);
                $response->send();
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                ignore_user_abort(true);
                $response->send();
                litespeed_finish_request();
            }

            $this->dispatchPostPaymentNotifications($outcome['order']);
        }

        return $response;
    }

    /**
     * Proses ulang event webhook Midtrans yang gagal dari Admin Panel.
     * Hanya dipanggil setelah signature_verified & isReplayable() lolos.
     *
     * @return array{http: int, body: array}
     */
    public function replayEvent(WebhookEvent $event): array
    {
        $payload = (array) ($event->payload ?? []);

        $validator = Validator::make($payload, [
            'order_id' => 'required|string',
            'status_code' => 'nullable|string',
            'gross_amount' => 'nullable|string',
            'signature_key' => 'nullable|string',
            'transaction_status' => 'nullable|string',
            'status' => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'payment_type' => 'nullable|string',
            'fraud_status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $event->update([
                'processing_status' => 'failed',
                'processing_error' => 'Struktur payload webhook tidak valid untuk diproses ulang.',
                'http_status' => 422,
            ]);

            return [
                'http' => 422,
                'body' => ['success' => false, 'message' => 'Invalid webhook payload structure.'],
            ];
        }

        $validated = $validator->validated();
        $validated['signature_key'] = $validated['signature_key'] ?? ($event->signature ?: 'verified-replay');

        $outcome = $this->processVerifiedPayload($validated, $payload, (string) ($event->ip_address ?: '127.0.0.1'), $event);

        if (!empty($outcome['needs_notification']) && !empty($outcome['order'])) {
            $this->dispatchPostPaymentNotifications($outcome['order']);
        }

        return [
            'http' => $outcome['http'],
            'body' => $outcome['body'],
        ];
    }

    /**
     * Eksekusi inti payload Midtrans yang signature-nya sudah terverifikasi.
     *
     * @return array{http: int, body: array, needs_notification: bool, order: ?Order}
     */
    protected function processVerifiedPayload(
        array $validated,
        array $rawPayload,
        string $ipAddress,
        ?WebhookEvent $existingEvent = null
    ): array {
        $provider = 'midtrans';
        $rawOrderId = (string) $validated['order_id'];
        $grossAmount = (string) ($validated['gross_amount'] ?? '');
        $incomingSignature = (string) ($validated['signature_key'] ?? ($existingEvent?->signature ?? ''));
        $providerTxId = (string) ($validated['transaction_id'] ?? $existingEvent?->event_id ?? $incomingSignature);

        $statusField = $validated['transaction_status'] ?? $validated['status'] ?? null;
        if (!$statusField) {
            return [
                'http' => 422,
                'body' => ['success' => false, 'message' => 'Missing transaction status.'],
                'needs_notification' => false,
                'order' => null,
            ];
        }
        $paymentStatus = strtolower($statusField);

        $order = $this->resolveOrder($rawOrderId);
        if (!$order) {
            $this->recordWebhook(
                $provider,
                $providerTxId,
                $paymentStatus,
                $incomingSignature,
                $rawPayload,
                $ipAddress,
                null,
                null,
                'ignored',
                'Order not found / Midtrans test ping',
                200,
                $existingEvent
            );

            return [
                'http' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Notification acknowledged (order not found or dashboard test).',
                ],
                'needs_notification' => false,
                'order' => null,
            ];
        }

        // Guard amount match untuk nominal webhook.
        $webhookAmount = (float) $grossAmount;
        $orderAmount = (float) $order->amount;
        if (abs($webhookAmount - $orderAmount) > 0.01) {
            Log::critical('Payment webhook amount mismatch', [
                'order_id' => $order->id,
                'webhook_amount' => $webhookAmount,
                'order_amount' => $orderAmount,
                'ip' => $ipAddress,
            ]);
            $this->recordWebhook(
                $provider,
                $providerTxId,
                $paymentStatus,
                $incomingSignature,
                $rawPayload,
                $ipAddress,
                $order->id,
                null,
                'failed',
                'Amount mismatch',
                422,
                $existingEvent
            );

            try {
                app(\App\Services\AdminAlertService::class)->alert(
                    'payment.failed',
                    [
                        'title' => "Amount Mismatch Webhook Order #{$order->id}",
                        'order_id' => $order->id,
                        'details' => [
                            'Order' => "#{$order->id}",
                            'Nominal Webhook' => 'Rp ' . number_format($webhookAmount, 0, ',', '.'),
                            'Nominal Order' => 'Rp ' . number_format($orderAmount, 0, ',', '.'),
                            'IP Sumber' => $ipAddress,
                        ],
                        'action_url' => url('/admin/webhooks?status=failed'),
                    ],
                    dedupeKey: 'wh-amt-' . $order->id,
                    cooldownSeconds: 3600
                );
            } catch (\Throwable) {
                // ignore
            }

            return [
                'http' => 422,
                'body' => ['success' => false, 'message' => 'Amount mismatch.'],
                'needs_notification' => false,
                'order' => $order,
            ];
        }

        $needsNotification = false;

        try {
            $result = DB::transaction(function () use (
                $provider, $providerTxId, $paymentStatus, $incomingSignature,
                $rawPayload, $ipAddress, $order, $validated, $grossAmount, $existingEvent, &$needsNotification
            ) {
                $lockedEvent = $existingEvent
                    ? WebhookEvent::whereKey($existingEvent->id)->lockForUpdate()->first()
                    : WebhookEvent::where('provider', $provider)
                        ->where('event_id', $providerTxId)
                        ->lockForUpdate()
                        ->first();

                if ($lockedEvent && $lockedEvent->processing_status === 'processed') {
                    return [
                        'status' => 'duplicate',
                        'message' => 'Webhook already processed (idempotent).',
                        'order_status' => $order->status,
                    ];
                }

                $event = $lockedEvent ?: WebhookEvent::create([
                    'provider' => $provider,
                    'event_id' => $providerTxId,
                    'event_type' => $paymentStatus,
                    'signature' => $incomingSignature,
                    'signature_verified' => true,
                    'ip_address' => $ipAddress,
                    'payload' => $rawPayload,
                    'processing_status' => 'received',
                    'http_status' => 200,
                    'order_id' => $order->id,
                ]);

                if ($lockedEvent && !$lockedEvent->signature_verified) {
                    $lockedEvent->signature_verified = true;
                }

                $tx = PaymentTransaction::where('provider', $provider)
                    ->where('provider_transaction_id', $providerTxId)
                    ->lockForUpdate()
                    ->first();

                if (!$tx) {
                    $tx = PaymentTransaction::create([
                        'order_id' => $order->id,
                        'invoice_id' => $order->invoice?->id,
                        'provider' => $provider,
                        'provider_transaction_id' => $providerTxId,
                        'provider_order_ref' => $validated['order_id'] ?? $order->id,
                        'payment_method' => $validated['payment_type'] ?? $order->payment_method,
                        'amount' => $grossAmount,
                        'currency' => 'IDR',
                        'status' => 'pending',
                        'fraud_status' => $validated['fraud_status'] ?? null,
                        'signature_key' => $incomingSignature,
                        'raw_payload' => $rawPayload,
                    ]);
                }

                // Jika transaksi atau order sudah terlanjur lunas sebelumnya, sinkronkan event saja (idempoten).
                if ($tx->status === 'settled' || ($order->paid_at && in_array($order->status, ['paid', 'provisioning', 'active'], true))) {
                    $event->update([
                        'processing_status' => 'processed',
                        'processing_error' => null,
                        'http_status' => 200,
                        'processed_at' => now(),
                        'order_id' => $order->id,
                        'payment_transaction_id' => $tx->id,
                    ]);

                    return [
                        'status' => 'duplicate',
                        'message' => 'Payment already settled (idempotent).',
                        'order_status' => $order->status,
                    ];
                }

                $fraudStatus = $validated['fraud_status'] ?? null;
                if ($fraudStatus === 'challenge') {
                    $tx->update(['fraud_status' => 'challenge']);
                    $event->update([
                        'processing_status' => 'ignored',
                        'processing_error' => 'Fraud challenge - awaiting manual review',
                        'http_status' => 200,
                        'processed_at' => now(),
                        'order_id' => $order->id,
                        'payment_transaction_id' => $tx->id,
                    ]);

                    try {
                        app(\App\Services\AdminAlertService::class)->alert(
                            'payment.failed',
                            [
                                'title' => "Fraud Challenge Midtrans Order #{$order->id}",
                                'order_id' => $order->id,
                                'details' => [
                                    'Order' => "#{$order->id}",
                                    'Pelanggan' => $order->customer?->full_name ?? '-',
                                    'Nominal' => 'Rp ' . number_format((float) $order->amount, 0, ',', '.'),
                                    'Status' => 'challenge (perlu tinjauan manual)',
                                ],
                                'action_url' => url('/admin/webhooks?status=failed'),
                            ],
                            dedupeKey: 'wh-challenge-' . $order->id,
                            cooldownSeconds: 3600
                        );
                    } catch (\Throwable) {
                        // ignore
                    }

                    return [
                        'status' => 'challenge',
                        'message' => 'Payment on fraud challenge, awaiting manual review.',
                        'order_status' => $order->status,
                    ];
                }

                if (in_array($paymentStatus, ['settlement', 'capture', 'paid'], true)) {
                    $this->handleSettlement($order, $tx, $event, $validated);
                    $needsNotification = true;

                    return [
                        'status' => 'settled',
                        'message' => 'Payment recorded. Order transitioned to paid.',
                        'order_status' => 'paid',
                    ];
                }

                if (in_array($paymentStatus, ['cancel', 'deny', 'expire', 'failure'], true)) {
                    $this->handleFailure($order, $tx, $event, $paymentStatus);

                    if (in_array($paymentStatus, ['deny', 'failure'], true)) {
                        try {
                            app(\App\Services\AdminAlertService::class)->alert(
                                'payment.failed',
                                [
                                    'title' => "Pembayaran Gagal/Ditolak Order #{$order->id}",
                                    'order_id' => $order->id,
                                    'details' => [
                                        'Order' => "#{$order->id}",
                                        'Status Gateway' => strtoupper($paymentStatus),
                                        'Nominal' => 'Rp ' . number_format((float) $order->amount, 0, ',', '.'),
                                    ],
                                    'action_url' => url('/admin/webhooks?status=failed'),
                                ],
                                dedupeKey: 'wh-deny-' . $order->id,
                                cooldownSeconds: 3600
                            );
                        } catch (\Throwable) {
                            // ignore
                        }
                    }

                    return [
                        'status' => 'cancelled',
                        'message' => 'Payment cancelled/denied recorded.',
                        'order_status' => 'cancelled',
                    ];
                }

                $tx->update([
                    'status' => $this->mapProviderStatus($paymentStatus),
                    'raw_payload' => $rawPayload,
                ]);
                $event->update([
                    'processing_status' => 'processed',
                    'processing_error' => null,
                    'http_status' => 200,
                    'processed_at' => now(),
                    'order_id' => $order->id,
                    'payment_transaction_id' => $tx->id,
                ]);

                return [
                    'status' => 'pending',
                    'message' => 'Payment status pending recorded.',
                    'order_status' => $order->status,
                ];
            });
        } catch (InvalidStateTransitionException $e) {
            Log::warning('Payment webhook state transition rejected', [
                'order_id' => $order->id,
                'status' => $order->status,
                'payment_status' => $paymentStatus,
                'error' => $e->getMessage(),
            ]);

            $recordedTx = null;
            if (in_array($paymentStatus, ['settlement', 'capture', 'paid'], true)) {
                $recordedTx = PaymentTransaction::updateOrCreate(
                    [
                        'provider' => $provider,
                        'provider_transaction_id' => $providerTxId,
                    ],
                    [
                        'order_id' => $order->id,
                        'invoice_id' => $order->invoice?->id,
                        'provider_order_ref' => $validated['order_id'] ?? $order->id,
                        'payment_method' => $validated['payment_type'] ?? $order->payment_method,
                        'amount' => $grossAmount,
                        'currency' => 'IDR',
                        'status' => 'settled',
                        'settled_at' => now(),
                        'fraud_status' => $validated['fraud_status'] ?? 'accept',
                        'signature_key' => $incomingSignature,
                        'raw_payload' => $rawPayload,
                    ]
                );

                Log::critical('webhook.late_settled_on_closed_order', [
                    'order_id' => $order->id,
                    'order_status' => $order->status,
                    'tx_id' => $recordedTx->id,
                ]);

                try {
                    app(\App\Services\AdminAlertService::class)->alert(
                        'payment.late_settled',
                        [
                            'title' => 'Dana Masuk pada Order Kedaluwarsa / Dibatalkan',
                            'order_id' => $order->id,
                            'details' => [
                                'Order' => "#{$order->id} (status: {$order->status})",
                                'Nominal' => 'Rp ' . number_format((float) $grossAmount, 0, ',', '.'),
                                'Payment Tx' => "#{$recordedTx->id}",
                            ],
                            'body' => "Order #{$order->id} (status DB: {$order->status}) menerima webhook settlement Midtrans "
                                . "(Rp " . number_format((float) $grossAmount, 0, ',', '.') . "). Dana tercatat pada PaymentTransaction #{$recordedTx->id}.",
                            'action_url' => url('/admin/orders'),
                        ],
                        dedupeKey: 'late-wh-' . $order->id,
                        cooldownSeconds: 86400
                    );
                } catch (\Throwable) {
                    // ignore
                }
            }

            $this->recordWebhook(
                $provider,
                $providerTxId,
                $paymentStatus,
                $incomingSignature,
                $rawPayload,
                $ipAddress,
                $order->id,
                $recordedTx?->id,
                'ignored',
                $e->getMessage(),
                200,
                $existingEvent
            );

            return [
                'http' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Webhook received but state transition ignored: ' . $e->getMessage(),
                ],
                'needs_notification' => false,
                'order' => $order,
            ];
        } catch (\Throwable $e) {
            Log::error('Payment webhook processing failed', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->recordWebhook(
                $provider,
                $providerTxId,
                $paymentStatus,
                $incomingSignature,
                $rawPayload,
                $ipAddress,
                $order->id ?? null,
                null,
                'failed',
                $e->getMessage(),
                500,
                $existingEvent
            );

            try {
                app(\App\Services\AdminAlertService::class)->alert(
                    'payment.failed',
                    [
                        'title' => 'Webhook Processing Error (HTTP 500)',
                        'order_id' => $order->id ?? null,
                        'details' => [
                            'Order' => '#' . ($order->id ?? $rawOrderId),
                            'Event' => $providerTxId,
                            'Error' => \Illuminate\Support\Str::limit($e->getMessage(), 150),
                        ],
                        'action_url' => url('/admin/webhooks?status=failed'),
                    ],
                    dedupeKey: 'wh-500-' . $providerTxId,
                    cooldownSeconds: 1800
                );
            } catch (\Throwable) {
                // ignore
            }

            return [
                'http' => 500,
                'body' => ['success' => false, 'message' => 'Webhook processing error.'],
                'needs_notification' => false,
                'order' => $order,
            ];
        }

        return [
            'http' => 200,
            'body' => [
                'success' => true,
                'message' => $result['message'],
                'order_id' => $order->id,
                'order_status' => $result['order_status'],
            ],
            'needs_notification' => $needsNotification,
            'order' => $order,
        ];
    }

    /**
     * Resolve Order dari berbagai format order_id Midtrans:
     * "CRV-123-ts", "ORDER-123-ts", "VX-123", "INV-YYYYMM-XXXX", atau ID angka.
     */
    protected function resolveOrder(string $rawOrderId): ?Order
    {
        $order = null;
        if (is_numeric($rawOrderId)) {
            $order = Order::with('invoice')->find((int) $rawOrderId);
        }
        if (!$order && preg_match('/^(?:CRV|ORDER|VX)[-_]?(\d+)(?:[-_].*)?$/i', $rawOrderId, $matches)) {
            $order = Order::with('invoice')->find((int) $matches[1]);
        }
        if (!$order) {
            $order = Order::whereHas('invoice', fn ($q) => $q->where('invoice_number', $rawOrderId))->with('invoice')->first();
        }
        if (!$order) {
            $tx = PaymentTransaction::where('provider_order_ref', $rawOrderId)->with('order.invoice')->first();
            if ($tx && $tx->order) {
                $order = $tx->order;
            }
        }

        return $order;
    }

    /**
     * Sukses payment: transisi order pending → paid, tandai invoice paid.
     */
    protected function handleSettlement(Order $order, PaymentTransaction $tx, WebhookEvent $event, array $validated): void
    {
        $this->orderStateMachine->transition(
            $order,
            'paid',
            [
                'reason' => 'Payment gateway webhook settlement received.',
                'actor_type' => 'webhook',
                'metadata' => [
                    'provider' => $tx->provider,
                    'provider_tx_id' => $tx->provider_transaction_id,
                    'payment_method' => $validated['payment_type'] ?? null,
                ],
                'onLocked' => function (Order $locked) use ($tx, $validated) {
                    $locked->paid_at = now();
                    $locked->payment_method = $validated['payment_type'] ?? $locked->payment_method;
                    $locked->save();

                    if ($locked->invoice && $locked->invoice->status !== 'paid') {
                        $locked->invoice->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'paid_via_transaction_id' => $tx->id,
                        ]);
                    }
                },
                'allowSame' => false,
            ]
        );

        $tx->update([
            'status' => 'settled',
            'settled_at' => now(),
            'fraud_status' => $validated['fraud_status'] ?? $tx->fraud_status,
        ]);

        $event->update([
            'processing_status' => 'processed',
            'processing_error' => null,
            'http_status' => 200,
            'processed_at' => now(),
            'order_id' => $order->id,
            'payment_transaction_id' => $tx->id,
        ]);

        if ($order->isRenewal()) {
            try {
                app(\App\Services\RenewalService::class)->handleRenewalPayment($order);
            } catch (\Throwable $e) {
                Log::error('handleSettlement.renewal_failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function dispatchPostPaymentNotifications(Order $order): void
    {
        app(\App\Services\PaymentCompletionService::class)->handle($order->id);
    }

    protected function handleFailure(Order $order, PaymentTransaction $tx, WebhookEvent $event, string $paymentStatus): void
    {
        if ($order->status === 'pending') {
            $this->orderStateMachine->transition(
                $order,
                'cancelled',
                [
                    'reason' => "Payment {$paymentStatus} from gateway webhook.",
                    'actor_type' => 'webhook',
                    'metadata' => [
                        'gateway_status' => $paymentStatus,
                        'provider_tx_id' => $tx->provider_transaction_id,
                    ],
                    'onLocked' => function (Order $locked) {
                        if ($locked->invoice && $locked->invoice->status !== 'cancelled') {
                            $locked->invoice->update(['status' => 'cancelled']);
                        }
                    },
                ]
            );
        }

        $tx->update([
            'status' => in_array($paymentStatus, ['expire'], true) ? 'expired' : 'failed',
            'failed_at' => now(),
            'failure_reason' => $paymentStatus,
        ]);

        $event->update([
            'processing_status' => 'processed',
            'processing_error' => null,
            'http_status' => 200,
            'processed_at' => now(),
            'order_id' => $order->id,
            'payment_transaction_id' => $tx->id,
        ]);
    }

    protected function recordWebhook(
        string $provider,
        string $eventId,
        string $eventType,
        string $signature,
        array $payload,
        string $ipAddress,
        ?int $orderId,
        ?int $paymentTxId,
        string $status,
        ?string $error,
        int $httpStatus = 200,
        ?WebhookEvent $existingEvent = null
    ): void {
        try {
            $attributes = [
                'event_type' => $eventType,
                'signature' => $signature,
                'signature_verified' => true,
                'ip_address' => $ipAddress,
                'payload' => $payload,
                'processing_status' => $status,
                'processing_error' => $error,
                'http_status' => $httpStatus,
                'processed_at' => now(),
                'order_id' => $orderId,
                'payment_transaction_id' => $paymentTxId,
            ];

            if ($existingEvent) {
                $existingEvent->update($attributes);
                return;
            }

            WebhookEvent::updateOrCreate(
                ['provider' => $provider, 'event_id' => $eventId],
                $attributes
            );
        } catch (\Throwable $e) {
            Log::error('Failed to record webhook event', ['error' => $e->getMessage()]);
        }
    }

    protected function mapProviderStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'settlement', 'capture', 'paid' => 'settled',
            'authorize' => 'authorized',
            'pending' => 'pending',
            'cancel', 'deny', 'failure' => 'failed',
            'expire' => 'expired',
            'refund', 'partial_refund' => 'refunded',
            default => 'pending',
        };
    }
}
