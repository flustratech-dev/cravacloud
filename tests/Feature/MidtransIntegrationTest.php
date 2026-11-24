<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VpsSpec;
use App\Models\WebhookEvent;
use App\Notifications\PaymentReceivedNotification;
use App\Services\Payments\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClientFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MidtransIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function customer(): User
    {
        $user = User::create([
            'full_name' => 'Pelanggan Midtrans Test',
            'username' => 'mid_' . uniqid(),
            'email' => 'mid_' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
            'channel' => 'website',
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return $user;
    }

    public function test_midtrans_service_snap_generation(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::first();

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'hostname' => 'vps-midtrans-test',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'midtrans_snap',
            'amount' => 100000,
        ]);

        $gw = PaymentGateway::where('code', 'midtrans')->first();
        $gw->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => [
                'server_key' => 'SB-Mid-server-test12345',
                'client_key' => 'SB-Mid-client-test12345',
            ],
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'mock-snap-token-xyz',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/mock-snap-token-xyz',
            ], 201),
        ]);

        $result = MidtransService::createSnapTransaction($order);

        $this->assertTrue($result['success']);
        $this->assertSame('mock-snap-token-xyz', $result['token']);
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v2/vtweb/mock-snap-token-xyz', $result['redirect_url']);
        $this->assertStringStartsWith('CRV-' . $order->id . '-', $result['midtrans_order_id']);
    }

    public function test_online_payment_checkout_routes_to_midtrans_without_channel_lock(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::where('is_active', true)->first();

        $gw = PaymentGateway::where('code', 'midtrans')->first();
        $gw->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => [
                'server_key' => 'SB-Mid-server-test12345',
                'client_key' => 'SB-Mid-client-test12345',
            ],
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-online-token-001',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-online-token-001',
            ], 201),
        ]);

        $response = $this->actingAs($customer)->postJson('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'hostname' => 'cv-online-midtrans',
            'root_password' => 'RahasiaKuat123',
            'payment_method' => 'online_payment',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'snap_token' => 'snap-online-token-001',
                'snap_redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-online-token-001',
            ]);

        Http::assertSent(function ($req) {
            $body = $req->data();

            return str_starts_with($body['transaction_details']['order_id'] ?? '', 'CRV-')
                && !array_key_exists('enabled_payments', $body)
                && !$req->hasHeader('X-Append-Notification');
        });
    }

    public function test_midtrans_webhook_handles_crv_prefixed_order_id(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::first();

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'dokploy',
            'provider' => 'cravacloud',
            'hostname' => 'vps-webhook-crv',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'online_payment',
            'amount' => 150000,
        ]);

        $serverKey = 'Mid-server-prod-test-key';
        PaymentGateway::where('code', 'midtrans')->first()->update([
            'is_active' => true,
            'mode' => 'production',
            'credentials' => [
                'server_key' => $serverKey,
                'client_key' => 'Mid-client-prod-test-key',
            ],
        ]);

        $orderIdParam = "CRV-{$order->id}-" . time();
        $statusCode = '200';
        $grossAmount = '150000.00';
        $signature = hash('sha512', $orderIdParam . $statusCode . $grossAmount . $serverKey);

        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '200',
                'order_id' => $orderIdParam,
                'gross_amount' => $grossAmount,
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'payment_type' => 'qris',
                'transaction_id' => 'TXN-MIDTRANS-CRV-1',
            ], 200),
        ]);

        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'TXN-MIDTRANS-CRV-1',
        ])->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_midtrans_webhook_handles_prefixed_and_timestamped_order_id(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::first();

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'dokploy',
            'provider' => 'cravacloud',
            'hostname' => 'vps-webhook-prefixed',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'mandiri_va',
            'amount' => 150000,
        ]);

        $serverKey = 'Mid-server-prod-test-key';
        $gw = PaymentGateway::where('code', 'midtrans')->first();
        $gw->update([
            'is_active' => true,
            'mode' => 'production',
            'credentials' => [
                'server_key' => $serverKey,
                'client_key' => 'Mid-client-prod-test-key',
            ],
        ]);

        $orderIdParam = "ORDER-{$order->id}-" . time();
        $statusCode = '200';
        $grossAmount = '150000.00';
        $signature = hash('sha512', $orderIdParam . $statusCode . $grossAmount . $serverKey);

        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '200',
                'order_id' => $orderIdParam,
                'gross_amount' => $grossAmount,
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'payment_type' => 'echannel',
                'transaction_id' => 'TXN-MIDTRANS-12345',
            ], 200),
        ]);

        $response = $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'echannel',
            'transaction_id' => 'TXN-MIDTRANS-12345',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_payment_page_loads_with_midtrans_snap(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::first();

        $gw = PaymentGateway::where('code', 'midtrans')->first();
        $gw->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => [
                'server_key' => 'SB-Mid-server-test12345',
                'client_key' => 'SB-Mid-client-test12345',
            ],
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'vps-midtrans-page',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'midtrans_snap',
            'amount' => 80000,
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'mock-token-page-test',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/mock-token-page-test',
            ], 201),
        ]);

        $response = $this->actingAs($customer)->get("/order/payment/{$order->id}");
        $response->assertRedirect(route('order.payment.status', $order->id));

        $statusResponse = $this->actingAs($customer)->get(route('order.payment.status', $order->id));
        $statusResponse->assertStatus(200);
        $statusResponse->assertSee('mock-token-page-test');
    }

    public function test_checkout_with_midtrans_snap_returns_snap_token_and_redirect_url(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::where('is_active', true)->first();
        $provider = 'cravacloud';
        $location = 'singapore';

        $gw = PaymentGateway::where('code', 'midtrans')->first();
        $gw->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => [
                'server_key' => 'SB-Mid-server-test12345',
                'client_key' => 'SB-Mid-client-test12345',
            ],
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-checkout-token-999',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-checkout-token-999',
            ], 201),
        ]);

        $response = $this->actingAs($customer)->postJson('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => $provider,
            'datacenter_location' => $location,
            'os' => 'ubuntu2404',
            'hostname' => 'cv-snap-test',
            'root_password' => 'RahasiaKuat123',
            'payment_method' => 'midtrans_snap',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'snap_token' => 'snap-checkout-token-999',
            'snap_redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-checkout-token-999',
        ]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'payment_method' => 'midtrans_snap',
            'status' => 'pending',
        ]);
    }

    public function test_checkout_with_mandiri_va_and_other_va_returns_snap_token(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::where('is_active', true)->first();
        $provider = 'cravacloud';
        $location = 'singapore';

        $gw = PaymentGateway::where('code', 'midtrans')->first();
        $gw->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => [
                'server_key' => 'SB-Mid-server-test12345',
                'client_key' => 'SB-Mid-client-test12345',
            ],
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-mandiri-token-123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-mandiri-token-123',
            ], 201),
        ]);

        $response = $this->actingAs($customer)->postJson('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => $provider,
            'datacenter_location' => $location,
            'os' => 'ubuntu2404',
            'hostname' => 'cv-mandiri-test',
            'root_password' => 'RahasiaKuat123',
            'payment_method' => 'mandiri_va',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'snap_token' => 'snap-mandiri-token-123',
            'snap_redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-mandiri-token-123',
        ]);
    }

    public function test_webhook_get_healthcheck(): void
    {
        $response = $this->getJson('/api/webhooks/payment');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
        ]);
    }

    public function test_webhook_test_ping_with_valid_signature_returns_200(): void
    {
        $serverKey = 'Mid-server-test12345';
        $gw = PaymentGateway::where('code', 'midtrans')->first();
        $gw->update([
            'is_active' => true,
            'credentials' => [
                'server_key' => $serverKey,
                'client_key' => 'Mid-client-test12345',
            ],
        ]);

        $orderId = 'test-dummy-order-9999';
        $statusCode = '200';
        $grossAmount = '10000.00';
        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        $response = $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'transaction_id' => 'tx-dummy-123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_webhook_with_missing_fields_returns_422_json_without_redirect(): void
    {
        $serverKey = 'Mid-server-test12345';
        $gw = PaymentGateway::where('code', 'midtrans')->first();
        $gw->update([
            'is_active' => true,
            'credentials' => [
                'server_key' => $serverKey,
                'client_key' => 'Mid-client-test12345',
            ],
        ]);

        // Post raw request without Accept: application/json header
        $response = $this->call('POST', '/api/webhooks/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['foo' => 'bar']));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid webhook payload structure.',
        ]);
    }

    public function test_midtrans_webhook_transitions_pending_to_settlement_with_same_transaction_id(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $spec = VpsSpec::first();
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'vps-pending-to-settled',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'bca_va',
            'amount' => 150000,
        ]);

        $serverKey = 'SB-Mid-server-pending-flow';
        PaymentGateway::where('code', 'midtrans')->first()->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => ['server_key' => $serverKey, 'client_key' => 'SB-Mid-client-pending-flow'],
        ]);

        $orderIdParam = "CRV-{$order->id}-170000001";
        $grossAmount = '150000.00';
        $txId = 'TXN-SAME-ID-001';

        PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'midtrans',
            'provider_transaction_id' => null,
            'provider_order_ref' => $orderIdParam,
            'payment_method' => 'bca_va',
            'amount' => 150000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        // 1) Webhook pending saat VA dibuat
        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '201',
                'order_id' => $orderIdParam,
                'gross_amount' => $grossAmount,
                'transaction_status' => 'pending',
                'payment_type' => 'bank_transfer',
                'transaction_id' => $txId,
            ], 200),
        ]);

        $sigPending = hash('sha512', $orderIdParam . '201' . $grossAmount . $serverKey);
        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => '201',
            'gross_amount' => $grossAmount,
            'signature_key' => $sigPending,
            'transaction_status' => 'pending',
            'payment_type' => 'bank_transfer',
            'transaction_id' => $txId,
        ])->assertStatus(200)->assertJson(['order_status' => 'pending']);

        $this->assertSame('pending', $order->fresh()->status);

        // 2) Webhook settlement dengan transaction_id yang sama persis
        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '200',
                'order_id' => $orderIdParam,
                'gross_amount' => $grossAmount,
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'payment_type' => 'bank_transfer',
                'transaction_id' => $txId,
            ], 200),
        ]);

        $sigSettled = hash('sha512', $orderIdParam . '200' . $grossAmount . $serverKey);
        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => $sigSettled,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'transaction_id' => $txId,
        ])->assertStatus(200)->assertJson(['order_status' => 'paid']);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame(1, PaymentTransaction::where('order_id', $order->id)->count());
        $this->assertSame('settled', PaymentTransaction::where('order_id', $order->id)->value('status'));
        Notification::assertSentToTimes($customer, PaymentReceivedNotification::class, 1);

        // 3) Webhook settlement duplikat ketiga harus diabaikan (idempoten)
        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => $sigSettled,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'transaction_id' => $txId,
        ])->assertStatus(200)->assertJson(['message' => 'Webhook already processed (idempotent).']);

        Notification::assertSentToTimes($customer, PaymentReceivedNotification::class, 1);
    }

    public function test_midtrans_webhook_transitions_pending_to_expire_with_same_transaction_id(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::first();
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'vps-pending-to-expire',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 120000,
        ]);

        $serverKey = 'SB-Mid-server-expire-flow';
        PaymentGateway::where('code', 'midtrans')->first()->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => ['server_key' => $serverKey, 'client_key' => 'SB-Mid-client-expire-flow'],
        ]);

        $orderIdParam = "CRV-{$order->id}-170000002";
        $grossAmount = '120000.00';
        $txId = 'TXN-SAME-EXPIRE-002';

        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '201',
                'order_id' => $orderIdParam,
                'gross_amount' => $grossAmount,
                'transaction_status' => 'pending',
                'payment_type' => 'qris',
                'transaction_id' => $txId,
            ], 200),
        ]);

        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => '201',
            'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderIdParam . '201' . $grossAmount . $serverKey),
            'transaction_status' => 'pending',
            'payment_type' => 'qris',
            'transaction_id' => $txId,
        ])->assertStatus(200);

        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '407',
                'order_id' => $orderIdParam,
                'gross_amount' => $grossAmount,
                'transaction_status' => 'expire',
                'payment_type' => 'qris',
                'transaction_id' => $txId,
            ], 200),
        ]);

        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => '407',
            'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderIdParam . '407' . $grossAmount . $serverKey),
            'transaction_status' => 'expire',
            'payment_type' => 'qris',
            'transaction_id' => $txId,
        ])->assertStatus(200)->assertJson(['order_status' => 'cancelled']);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('expired', PaymentTransaction::where('provider_transaction_id', $txId)->value('status'));
    }

    public function test_midtrans_webhook_rejects_forged_settlement_when_core_api_says_pending(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::first();
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'vps-forged-settlement',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
        ]);

        $serverKey = 'SB-Mid-server-forge-check';
        PaymentGateway::where('code', 'midtrans')->first()->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => ['server_key' => $serverKey, 'client_key' => 'SB-Mid-client-forge-check'],
        ]);

        $orderIdParam = "CRV-{$order->id}-170000003";
        $grossAmount = '150000.00';

        // Core API menjawab transaksi sebenarnya masih pending!
        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '201',
                'order_id' => $orderIdParam,
                'gross_amount' => $grossAmount,
                'transaction_status' => 'pending',
                'payment_type' => 'qris',
                'transaction_id' => 'TXN-FORGED-003',
            ], 200),
        ]);

        // Penyerang mengirim signature valid (hasil replay notifikasi) dengan transaction_status dipalsukan -> settlement
        $signature = hash('sha512', $orderIdParam . '200' . $grossAmount . $serverKey);
        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'TXN-FORGED-003',
        ])->assertStatus(200)->assertJson(['order_status' => 'pending']);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_midtrans_webhook_returns_503_and_does_not_settle_when_core_api_unreachable(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::first();
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'vps-api-unreachable',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
        ]);

        $serverKey = 'SB-Mid-server-503';
        PaymentGateway::where('code', 'midtrans')->first()->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => ['server_key' => $serverKey, 'client_key' => 'SB-Mid-client-503'],
        ]);

        $orderIdParam = "CRV-{$order->id}-170000004";
        $grossAmount = '150000.00';

        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderIdParam . '200' . $grossAmount . $serverKey),
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'TXN-503-004',
        ])->assertStatus(503)->assertJson(['success' => false]);

        $this->assertSame('pending', $order->fresh()->status);
        $event = WebhookEvent::where('event_id', 'TXN-503-004')->first();
        $this->assertNotNull($event);
        $this->assertSame('failed', $event->processing_status);
        $this->assertSame(503, $event->http_status);
        $this->assertTrue($event->isReplayable());
    }

    public function test_midtrans_webhook_rejects_404_unknown_transaction_from_core_api(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::first();
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'vps-api-404',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
        ]);

        $serverKey = 'SB-Mid-server-404';
        PaymentGateway::where('code', 'midtrans')->first()->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => ['server_key' => $serverKey, 'client_key' => 'SB-Mid-client-404'],
        ]);

        $orderIdParam = "CRV-{$order->id}-170000005";
        $grossAmount = '150000.00';

        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '404',
                'status_message' => "Transaction doesn't exist.",
            ], 200),
        ]);

        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderIdParam . '200' . $grossAmount . $serverKey),
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'TXN-404-005',
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_midtrans_webhook_does_not_settle_capture_with_fraud_deny(): void
    {
        $customer = $this->customer();
        $spec = VpsSpec::first();
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'vps-fraud-deny',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'credit_card',
            'amount' => 150000,
        ]);

        $serverKey = 'SB-Mid-server-deny';
        PaymentGateway::where('code', 'midtrans')->first()->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => ['server_key' => $serverKey, 'client_key' => 'SB-Mid-client-deny'],
        ]);

        $orderIdParam = "CRV-{$order->id}-170000006";
        $grossAmount = '150000.00';

        Http::swap(new HttpClientFactory());
        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '200',
                'order_id' => $orderIdParam,
                'gross_amount' => $grossAmount,
                'transaction_status' => 'capture',
                'fraud_status' => 'deny',
                'payment_type' => 'credit_card',
                'transaction_id' => 'TXN-DENY-006',
            ], 200),
        ]);

        $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderIdParam,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderIdParam . '200' . $grossAmount . $serverKey),
            'transaction_status' => 'capture',
            'fraud_status' => 'deny',
            'payment_type' => 'credit_card',
            'transaction_id' => 'TXN-DENY-006',
        ])->assertStatus(200)->assertJson(['order_status' => 'cancelled']);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_check_midtrans_status_command_syncs_pending_order_and_respects_hours_window(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $spec = VpsSpec::first();

        PaymentGateway::where('code', 'midtrans')->first()->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => [
                'server_key' => 'SB-Mid-server-cmd-test',
                'client_key' => 'SB-Mid-client-cmd-test',
            ],
        ]);

        $recentOrder = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'vps-cmd-recent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
        ]);

        $oldOrder = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'vps-cmd-old',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
        ]);
        Order::whereKey($oldOrder->id)->update(['created_at' => now()->subHours(72)]);

        Http::swap(new HttpClientFactory());
        Http::fake([
            "https://api.sandbox.midtrans.com/v2/CRV-{$recentOrder->id}/status" => Http::response([
                'status_code' => '200',
                'order_id' => "CRV-{$recentOrder->id}",
                'gross_amount' => '150000.00',
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'payment_type' => 'qris',
                'transaction_id' => 'TXN-CMD-RECENT',
            ], 200),
            '*' => Http::response(['status_code' => '404'], 200),
        ]);

        $this->artisan('midtrans:check-status --hours=48')
            ->expectsOutputToContain('1 pesanan berhasil disinkronkan menjadi LUNAS')
            ->assertSuccessful();

        $this->assertSame('paid', $recentOrder->fresh()->status);
        $this->assertSame('pending', $oldOrder->fresh()->status);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), "CRV-{$oldOrder->id}"));
    }
}

