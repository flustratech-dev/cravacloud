<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VpsSpec;
use App\Models\WebhookEvent;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Payment Gateway (hanya Midtrans) dan Webhook Log / Replay.
 */
class PaymentGatewayAndWebhookLogTest extends TestCase
{
    use RefreshDatabase;

    protected string $serverKey = 'SB-Mid-server-uji_123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        Config::set('services.midtrans.server_key', $this->serverKey);
        Config::set('services.midtrans.client_key', 'SB-Mid-client-uji_123456');
    }

    protected function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    protected function customer(): User
    {
        $user = User::create([
            'full_name' => 'Pelanggan Gateway',
            'username' => 'gw' . uniqid(),
            'email' => 'gw' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return $user->fresh();
    }

    protected function pendingOrder(User $customer, int $amount = 110000): Order
    {
        $spec = VpsSpec::where('is_active', true)->firstOrFail();

        return Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'hostname' => 'cv-gw-' . uniqid(),
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'online_payment',
            'amount' => $amount,
            'currency' => 'IDR',
        ]);
    }

    /**
     * Payload Midtrans sah beserta signature SHA-512.
     */
    protected function midtransPayload(Order $order, string $txId, array $overrides = [], ?string $secret = null): array
    {
        $orderId = 'CRV-' . $order->id . '-1700000';
        $statusCode = (string) ($overrides['status_code'] ?? '200');
        $grossAmount = (string) ($overrides['gross_amount'] ?? number_format((float) $order->amount, 2, '.', ''));
        $serverKey = $secret ?? $this->serverKey;

        $payload = array_merge([
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'transaction_id' => $txId,
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
        ], $overrides);

        $payload['signature_key'] = $overrides['signature_key']
            ?? hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $serverKey);

        return $payload;
    }

    // ======================= Registry & checkout =======================

    public function test_seeder_keeps_only_midtrans_active(): void
    {
        $this->assertSame(1, PaymentGateway::count());
        $this->assertTrue(PaymentGateway::where('code', 'midtrans')->value('is_active'));
        $this->assertDatabaseMissing('payment_gateways', ['code' => 'xendit']);
        $this->assertDatabaseMissing('payment_gateways', ['code' => 'lynk']);
        $this->assertDatabaseMissing('payment_gateways', ['code' => 'qris']);
    }

    public function test_checkout_rejects_removed_or_inactive_gateway_method(): void
    {
        $spec = VpsSpec::where('is_active', true)->first();
        $customer = $this->customer();

        // Metode gateway lama (lynk) harus ditolak.
        $this->actingAs($customer)
            ->postJson('/checkout', [
                'vps_spec_id' => $spec->id,
                'control_panel' => 'none',
                'provider' => 'cravacloud',
                'datacenter_location' => 'singapore',
                'os' => 'ubuntu2404',
                'hostname' => 'cv-gw-' . uniqid(),
                'root_password' => 'RahasiaKuat123',
                'payment_method' => 'lynk',
                'terms_accepted' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        // Jika Midtrans dinonaktifkan dan config kosong, checkout menolak metode Midtrans.
        Config::set('services.midtrans.server_key', null);
        PaymentGateway::where('code', 'midtrans')->update(['is_active' => false]);

        $this->actingAs($customer)
            ->postJson('/checkout', [
                'vps_spec_id' => $spec->id,
                'control_panel' => 'none',
                'provider' => 'cravacloud',
                'datacenter_location' => 'singapore',
                'os' => 'ubuntu2404',
                'hostname' => 'cv-gw-' . uniqid(),
                'root_password' => 'RahasiaKuat123',
                'payment_method' => 'online_payment',
                'terms_accepted' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertSame(0, Order::count());
    }

    public function test_activating_midtrans_opens_all_midtrans_methods(): void
    {
        PaymentGateway::where('code', 'midtrans')->update(['is_active' => true]);

        $this->assertTrue(in_array('online_payment', PaymentGateway::activeMethods(), true));
        $this->assertTrue(in_array('bca_va', PaymentGateway::activeMethods(), true));
        $this->assertTrue(in_array('gopay', PaymentGateway::activeMethods(), true));
    }

    public function test_checkout_page_renders_with_midtrans_only(): void
    {
        $this->get('/checkout')
            ->assertStatus(200)
            ->assertSee('Midtrans')
            ->assertDontSee('AstraPay');
    }

    // ======================= Halaman & kredensial =======================

    public function test_admin_can_view_gateway_page(): void
    {
        $this->actingAs($this->admin())->get('/admin/payment-gateways')
            ->assertStatus(200)
            ->assertSee('Daftar Payment Gateway')
            ->assertSee('Midtrans')
            ->assertSee('(.env)')
            ->assertSee('/api/webhooks/payment')
            ->assertDontSee('Xendit Payment Gateway')
            ->assertDontSee('Lynk.id');
    }

    public function test_credentials_are_stored_encrypted_and_masked(): void
    {
        $gateway = PaymentGateway::where('code', 'midtrans')->first();

        $this->actingAs($this->admin())
            ->put("/admin/payment-gateways/{$gateway->id}", [
                'mode' => 'sandbox',
                'is_active' => 1,
                'credentials' => ['server_key' => 'SB-Mid-server-SANGATRAHASIA99'],
            ])
            ->assertRedirect();

        $raw = DB::table('payment_gateways')->where('id', $gateway->id)->value('credentials');
        $this->assertStringNotContainsString('SANGATRAHASIA99', $raw);

        $fresh = $gateway->fresh();
        $this->assertSame('SB-Mid-server-SANGATRAHASIA99', $fresh->decryptedCredentials()['server_key']);
        $this->assertStringNotContainsString('SANGATRAHASIA', $fresh->maskedCredential('server_key'));

        $this->actingAs($this->admin())->get('/admin/payment-gateways')
            ->assertDontSee('SB-Mid-server-SANGATRAHASIA99');
    }

    public function test_empty_credential_input_keeps_existing_value(): void
    {
        $gateway = PaymentGateway::where('code', 'midtrans')->first();
        $gateway->update(['credentials' => ['server_key' => 'nilai-lama-123456']]);

        $this->actingAs($this->admin())->put("/admin/payment-gateways/{$gateway->id}", [
            'mode' => 'sandbox',
            'is_active' => 1,
            'credentials' => ['server_key' => ''],
        ]);

        $this->assertSame('nilai-lama-123456', $gateway->fresh()->decryptedCredentials()['server_key']);
    }

    public function test_clear_checkbox_removes_credential(): void
    {
        $gateway = PaymentGateway::where('code', 'midtrans')->first();
        $gateway->update(['credentials' => ['server_key' => 'akan-dihapus-123']]);

        $this->actingAs($this->admin())->put("/admin/payment-gateways/{$gateway->id}", [
            'mode' => 'sandbox',
            'is_active' => 1,
            'clear' => ['server_key' => 1],
        ]);

        $this->assertFalse($gateway->fresh()->hasCredential('server_key'));
    }

    public function test_audit_log_never_contains_secret_value(): void
    {
        $gateway = PaymentGateway::where('code', 'midtrans')->first();

        $this->actingAs($this->admin())->put("/admin/payment-gateways/{$gateway->id}", [
            'mode' => 'sandbox',
            'is_active' => 1,
            'credentials' => ['server_key' => 'RAHASIA-TIDAK-BOLEH-MASUK-LOG'],
        ]);

        $log = AdminAuditLog::where('action', 'payment_gateway.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('RAHASIA-TIDAK-BOLEH-MASUK-LOG', (string) $log->description);
        $this->assertStringContainsString('server_key', (string) $log->description);
    }

    public function test_cannot_deactivate_last_active_gateway(): void
    {
        $midtrans = PaymentGateway::where('code', 'midtrans')->firstOrFail();
        $midtrans->update(['is_active' => true]);

        $this->actingAs($this->admin())
            ->put("/admin/payment-gateways/{$midtrans->id}", ['mode' => 'production'])
            ->assertSessionHas('error');

        $this->assertTrue($midtrans->fresh()->is_active);
    }

    public function test_midtrans_webhook_uses_server_key_from_registry(): void
    {
        Config::set('services.midtrans.server_key', null);
        PaymentGateway::where('code', 'midtrans')->first()
            ->update(['credentials' => ['server_key' => 'kunci-dari-panel-777']]);

        $order = $this->pendingOrder($this->customer(), 110000);
        $payload = $this->midtransPayload($order, 'mid_reg_key_001', [], 'kunci-dari-panel-777');

        $this->postJson(route('api.webhooks.payment'), $payload)
            ->assertStatus(200)
            ->assertJson(['success' => true, 'order_status' => 'paid']);
    }

    // ======================= Pencatatan webhook =======================

    public function test_successful_webhook_is_logged_with_http_status(): void
    {
        $order = $this->pendingOrder($this->customer(), 110000);
        $payload = $this->midtransPayload($order, 'mid_log_ok_001');

        $this->postJson(route('api.webhooks.payment'), $payload)->assertStatus(200);

        $event = WebhookEvent::where('event_id', 'mid_log_ok_001')->first();
        $this->assertNotNull($event);
        $this->assertSame('midtrans', $event->provider);
        $this->assertSame('processed', $event->processing_status);
        $this->assertSame(200, $event->http_status);
        $this->assertTrue($event->signature_verified);
    }

    public function test_failed_webhook_is_recorded_instead_of_lost(): void
    {
        $order = $this->pendingOrder($this->customer(), 110000);
        // Nominal webhook mismatch -> ditolak 422, tapi tetap tercatat untuk forensik & replay.
        $payload = $this->midtransPayload($order, 'mid_log_fail_001', ['gross_amount' => '50000.00']);

        $this->postJson(route('api.webhooks.payment'), $payload)->assertStatus(422);

        $event = WebhookEvent::where('event_id', 'mid_log_fail_001')->first();
        $this->assertNotNull($event);
        $this->assertSame('failed', $event->processing_status);
        $this->assertSame(422, $event->http_status);
        $this->assertNotNull($event->processing_error);
        $this->assertTrue($event->signature_verified);
        $this->assertTrue($event->isReplayable());
    }

    public function test_ignored_event_is_recorded(): void
    {
        $order = $this->pendingOrder($this->customer(), 110000);
        $payload = $this->midtransPayload($order, 'mid_log_ign_001', ['fraud_status' => 'challenge']);

        $this->postJson(route('api.webhooks.payment'), $payload)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame('ignored', WebhookEvent::where('event_id', 'mid_log_ign_001')->value('processing_status'));
    }

    public function test_invalid_signature_is_not_stored(): void
    {
        $order = $this->pendingOrder($this->customer(), 110000);
        $payload = $this->midtransPayload($order, 'mid_log_bad_sig', ['signature_key' => 'palsu']);

        $this->postJson(route('api.webhooks.payment'), $payload)->assertStatus(403);

        $this->assertSame(0, WebhookEvent::where('event_id', 'mid_log_bad_sig')->count());
    }

    // ======================= Proses ulang =======================

    public function test_admin_can_view_webhook_log(): void
    {
        $order = $this->pendingOrder($this->customer(), 110000);
        $payload = $this->midtransPayload($order, 'mid_log_view_001');
        $this->postJson(route('api.webhooks.payment'), $payload);

        $this->actingAs($this->admin())->get('/admin/webhooks')
            ->assertStatus(200)
            ->assertSee('mid_log_view_001');

        $this->actingAs($this->admin())->get('/admin/webhooks?status=failed')->assertStatus(200);
    }

    public function test_replay_processes_a_failed_but_valid_event(): void
    {
        $order = $this->pendingOrder($this->customer(), 110000);
        $payload = $this->midtransPayload($order, 'mid_replay_001');

        $event = WebhookEvent::create([
            'provider' => 'midtrans',
            'event_id' => 'mid_replay_001',
            'event_type' => 'settlement',
            'signature' => $payload['signature_key'],
            'signature_verified' => true,
            'payload' => $payload,
            'processing_status' => 'failed',
            'processing_error' => 'Simulasi kegagalan database sementara.',
            'http_status' => 500,
            'order_id' => $order->id,
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/webhooks/{$event->id}/replay")
            ->assertSessionHas('success');

        $event->refresh();
        $this->assertSame('processed', $event->processing_status);
        $this->assertSame($order->id, $event->order_id);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(2, $event->attempts);
        $this->assertNotNull($event->last_replayed_at);
    }

    public function test_replay_never_creates_double_payment(): void
    {
        $order = $this->pendingOrder($this->customer(), 110000);
        $payload = $this->midtransPayload($order, 'mid_replay_dup_001');

        $event = WebhookEvent::create([
            'provider' => 'midtrans',
            'event_id' => 'mid_replay_dup_001',
            'event_type' => 'settlement',
            'signature' => $payload['signature_key'],
            'signature_verified' => true,
            'payload' => $payload,
            'processing_status' => 'failed',
            'order_id' => $order->id,
        ]);

        $this->actingAs($this->admin())->post("/admin/webhooks/{$event->id}/replay");
        $this->actingAs($this->admin())->post("/admin/webhooks/{$event->id}/replay")->assertSessionHas('error');

        $this->assertSame(1, PaymentTransaction::where('provider_transaction_id', 'mid_replay_dup_001')->count());
    }

    public function test_replay_detects_existing_settled_transaction(): void
    {
        $order = $this->pendingOrder($this->customer(), 110000);
        $payload = $this->midtransPayload($order, 'mid_replay_settled_001');

        $this->postJson(route('api.webhooks.payment'), $payload)->assertStatus(200);

        WebhookEvent::where('event_id', 'mid_replay_settled_001')->update(['processing_status' => 'failed']);
        $event = WebhookEvent::where('event_id', 'mid_replay_settled_001')->first();

        $this->actingAs($this->admin())->post("/admin/webhooks/{$event->id}/replay");

        $this->assertSame(1, PaymentTransaction::where('provider_transaction_id', 'mid_replay_settled_001')->count());
        $this->assertSame('processed', $event->fresh()->processing_status);
    }

    public function test_unverified_event_cannot_be_replayed(): void
    {
        $order = $this->pendingOrder($this->customer(), 110000);
        $payload = $this->midtransPayload($order, 'mid_replay_unverified');

        $event = WebhookEvent::create([
            'provider' => 'midtrans',
            'event_id' => 'mid_replay_unverified',
            'signature_verified' => false,
            'payload' => $payload,
            'processing_status' => 'failed',
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/webhooks/{$event->id}/replay")
            ->assertSessionHas('error');

        $this->assertSame(0, PaymentTransaction::where('provider_transaction_id', 'mid_replay_unverified')->count());
    }

    public function test_customer_cannot_access_gateway_pages_or_replay(): void
    {
        $customer = $this->customer();
        $event = WebhookEvent::create([
            'provider' => 'midtrans',
            'event_id' => 'akses_001',
            'signature_verified' => true,
            'payload' => [],
            'processing_status' => 'failed',
        ]);

        $this->actingAs($customer)->get('/admin/payment-gateways')->assertStatus(403);
        $this->actingAs($customer)->get('/admin/webhooks')->assertStatus(403);
        $this->actingAs($customer)->post("/admin/webhooks/{$event->id}/replay")->assertStatus(403);
    }
}
