<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VpsSpec;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LynkWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $secretKey = 'test_lynk_secret_merchant_key_12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        Notification::fake();
        Config::set('services.lynk.merchant_key', $this->secretKey);
    }

    public function test_rejects_when_merchant_key_not_configured(): void
    {
        Config::set('services.lynk.merchant_key', null);

        $response = $this->postJson(route('api.webhooks.lynk'), [
            'event' => 'payment.received',
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Lynk payment gateway not configured on server.',
        ]);
    }

    public function test_rejects_when_signature_header_is_missing(): void
    {
        $response = $this->postJson(route('api.webhooks.lynk'), [
            'event' => 'payment.received',
            'data' => [
                'message_action' => 'SUCCESS',
            ],
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Missing X-Lynk-Signature header.',
        ]);
    }

    public function test_rejects_when_signature_is_invalid(): void
    {
        $payload = [
            'event' => 'payment.received',
            'data' => [
                'message_action' => 'SUCCESS',
                'message_id' => 'API_CALL_1744270275143115_4624014',
                'message_data' => [
                    'refId' => 'ref_12345678',
                    'totals' => ['grandTotal' => 110000],
                ],
            ],
        ];

        $response = $this->postJson(route('api.webhooks.lynk'), $payload, [
            'X-Lynk-Signature' => 'invalid_fake_signature_hash',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid signature.',
        ]);
    }

    public function test_successfully_processes_valid_lynk_payment_and_creates_user_and_order(): void
    {
        $refId = 'lynk_tx_998877';
        $grandTotal = 110000;
        $messageId = 'API_CALL_1744270275143115_4624014';
        $customerEmail = 'customer_lynk@example.com';
        $customerName = 'Budi Lynk';

        // Rumus signature resmi Lynk: sha256(amount + refId + messageId + secretKey)
        $signatureString = (string)$grandTotal . $refId . $messageId . $this->secretKey;
        $signature = hash('sha256', $signatureString);

        $payload = [
            'event' => 'payment.received',
            'data' => [
                'message_action' => 'SUCCESS',
                'message_code' => '0',
                'message_id' => $messageId,
                'message_data' => [
                    'createdAt' => '2026-09-16T10:00:00',
                    'customer' => [
                        'email' => $customerEmail,
                        'name' => $customerName,
                        'phone' => '081234567890',
                    ],
                    'items' => [
                        [
                            'title' => 'CravaCloud VPS - Standard (2C / 4GB)',
                            'price' => 110000,
                            'qty' => 1,
                            'uuid' => 'prod-uuid-12345',
                        ],
                    ],
                    'refId' => $refId,
                    'totals' => [
                        'grandTotal' => $grandTotal,
                        'totalPrice' => 110000,
                    ],
                ],
            ],
        ];

        $response = $this->postJson(route('api.webhooks.lynk'), $payload, [
            'X-Lynk-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Lynk payment processed successfully.',
            'is_new_user' => true,
        ]);

        // Verifikasi User dibuat
        $user = User::where('email', $customerEmail)->first();
        $this->assertNotNull($user);
        $this->assertEquals($customerName, $user->full_name);

        // Verifikasi Order dibuat dengan status 'paid'
        $order = Order::where('customer_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('paid', $order->status);
        $this->assertEquals('lynk', $order->payment_method);
        $this->assertEquals(110000, (float)$order->amount);

        // Verifikasi PaymentTransaction & WebhookEvent
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'provider' => 'lynk',
            'provider_transaction_id' => $refId,
            'status' => 'settled',
        ]);

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'lynk',
            'event_id' => $refId,
            'processing_status' => 'processed',
        ]);
    }

    public function test_idempotent_duplicate_ref_id_is_skipped(): void
    {
        $refId = 'lynk_idempotent_123';
        $grandTotal = 150000;
        $messageId = 'API_CALL_999999999';

        $signatureString = (string)$grandTotal . $refId . $messageId . $this->secretKey;
        $signature = hash('sha256', $signatureString);

        $payload = [
            'event' => 'payment.received',
            'data' => [
                'message_action' => 'SUCCESS',
                'message_code' => '0',
                'message_id' => $messageId,
                'message_data' => [
                    'createdAt' => '2026-09-16T10:00:00',
                    'customer' => [
                        'email' => 'duplicate@example.com',
                        'name' => 'Duplicate Buyer',
                    ],
                    'items' => [
                        [
                            'title' => 'CravaCloud AI - Cloud AI Workstation',
                            'price' => 150000,
                            'qty' => 1,
                        ],
                    ],
                    'refId' => $refId,
                    'totals' => [
                        'grandTotal' => $grandTotal,
                    ],
                ],
            ],
        ];

        // First call
        $first = $this->postJson(route('api.webhooks.lynk'), $payload, [
            'X-Lynk-Signature' => $signature,
        ]);
        $first->assertStatus(200);

        // Second call with same refId
        $second = $this->postJson(route('api.webhooks.lynk'), $payload, [
            'X-Lynk-Signature' => $signature,
        ]);
        $second->assertStatus(200);
        $second->assertJson([
            'success' => true,
            'message' => 'Event already processed.',
        ]);

        // Verifikasi hanya 1 order yang dibuat
        $this->assertEquals(1, Order::where('payment_method', 'lynk')->count());
    }

    public function test_lynk_dashboard_test_url_event_returns_200(): void
    {
        // 1. Test URL dengan refId dummy 'test'
        $response = $this->postJson(route('api.webhooks.lynk'), [
            'event' => 'payment.received',
            'data' => [
                'message_action' => 'SUCCESS',
                'message_data' => [
                    'refId' => 'TEST_REF_12345',
                    'customer' => ['email' => 'user@lynk.id'],
                ],
            ],
        ], [
            'X-Lynk-Signature' => 'dummy_test_signature',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Lynk test webhook received successfully.',
        ]);

        // 2. Test ping event
        $responsePing = $this->postJson(route('api.webhooks.lynk'), [
            'event' => 'test_event',
        ]);
        $responsePing->assertStatus(200);
        $responsePing->assertJson([
            'success' => true,
            'message' => 'Lynk test webhook received successfully.',
        ]);
    }

    public function test_processes_valid_payment_when_message_id_is_inside_message_data(): void
    {
        $refId = 'lynk_nested_id_7788';
        $grandTotal = 110000;
        $messageId = 'API_CALL_NESTED_991122';
        $customerEmail = 'nested_msg_id@example.com';
        $customerName = 'Rian Nested';

        $signatureString = (string)$grandTotal . $refId . $messageId . $this->secretKey;
        $signature = hash('sha256', $signatureString);

        // message_id ada di DALAM message_data (format Lynk aktual)
        $payload = [
            'event' => 'payment.received',
            'data' => [
                'message_action' => 'SUCCESS',
                'message_code' => '0',
                'message_data' => [
                    'message_id' => $messageId,
                    'createdAt' => '2026-09-16T10:00:00',
                    'customer' => [
                        'email' => $customerEmail,
                        'name' => $customerName,
                        'phone' => '081234567890',
                    ],
                    'items' => [
                        [
                            'title' => 'CravaCloud VPS - Standard (2C / 4GB)',
                            'price' => 110000,
                            'qty' => 1,
                        ],
                    ],
                    'refId' => $refId,
                    'totals' => [
                        'grandTotal' => $grandTotal,
                    ],
                ],
            ],
        ];

        $response = $this->postJson(route('api.webhooks.lynk'), $payload, [
            'X-Lynk-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Lynk payment processed successfully.',
        ]);
    }

    public function test_handles_secret_key_with_quotes_or_whitespace(): void
    {
        // Simulasi jika di Coolify .env diset dengan tanda petik: "test_lynk_secret_merchant_key_12345"
        Config::set('services.lynk.merchant_key', '  "' . $this->secretKey . '"  ');

        $refId = 'lynk_quoted_key_1122';
        $grandTotal = 110000;
        $messageId = 'API_CALL_QUOTED_KEY';
        $customerEmail = 'quoted_key@example.com';

        $signatureString = (string)$grandTotal . $refId . $messageId . $this->secretKey;
        $signature = hash('sha256', $signatureString);

        $payload = [
            'event' => 'payment.received',
            'data' => [
                'message_action' => 'SUCCESS',
                'message_id' => $messageId,
                'message_data' => [
                    'customer' => [
                        'email' => $customerEmail,
                        'name' => 'Quoted User',
                    ],
                    'items' => [
                        [
                            'title' => 'CravaCloud VPS - Standard (2C / 4GB)',
                            'price' => 110000,
                            'qty' => 1,
                        ],
                    ],
                    'refId' => $refId,
                    'totals' => [
                        'grandTotal' => $grandTotal,
                    ],
                ],
            ],
        ];

        $response = $this->postJson(route('api.webhooks.lynk'), $payload, [
            'X-Lynk-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Lynk payment processed successfully.',
        ]);
    }
}
