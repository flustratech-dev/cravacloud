<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\VpsSpec;
use App\Services\Payments\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            'provider' => 'tencent',
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
            'provider' => 'tencent',
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
            'provider' => 'tencent',
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
        $spec = VpsSpec::where('is_active', true)->get()->first(fn ($s) => $s->isProviderAllowed('tencent'));
        $provider = 'tencent';
        $location = 'singapore';

        if (!$spec) {
            $spec = VpsSpec::where('is_active', true)->first();
            $provider = $spec->defaultProvider();
            $location = 'indonesia';
        }

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
        $spec = VpsSpec::where('is_active', true)->get()->first(fn ($s) => $s->isProviderAllowed('tencent'));
        $provider = 'tencent';
        $location = 'singapore';

        if (!$spec) {
            $spec = VpsSpec::where('is_active', true)->first();
            $provider = $spec->defaultProvider();
            $location = 'indonesia';
        }

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
}

