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
 * PHASE 4 - Payment Gateway dan Webhook Log.
 */
class PaymentGatewayAndWebhookLogTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'kunci_rahasia_lynk_uji_123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        Config::set('services.lynk.merchant_key', $this->secret);
    }

    protected function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    protected function customer(): User
    {
        return User::create([
            'full_name' => 'Pelanggan Gateway',
            'username' => 'gw' . uniqid(),
            'email' => 'gw' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
    }

    /**
     * Payload Lynk sah beserta signature-nya.
     */
    protected function lynkPayload(string $refId, array $overrides = [], ?string $secret = null): array
    {
        $grandTotal = 110000;
        $messageId = 'MSG_' . $refId;

        $payload = array_replace_recursive([
            'event' => 'payment.received',
            'data' => [
                'message_action' => 'SUCCESS',
                'message_id' => $messageId,
                'message_data' => [
                    'customer' => ['email' => 'bayar' . $refId . '@example.com', 'name' => 'Pembayar Uji'],
                    'items' => [['title' => 'CravaCloud VPS - Standard (2C / 4GB)', 'price' => 110000, 'qty' => 1]],
                    'refId' => $refId,
                    'totals' => ['grandTotal' => $grandTotal],
                ],
            ],
        ], $overrides);

        $signature = hash('sha256', $grandTotal . $refId . $messageId . ($secret ?? $this->secret));

        return [$payload, $signature];
    }

    // ======================= Registry & checkout =======================

    public function test_seeder_keeps_previous_checkout_behaviour(): void
    {
        $this->assertTrue(PaymentGateway::where('code', 'xendit')->value('is_active'));
        $this->assertTrue(PaymentGateway::where('code', 'qris')->value('is_active'));
        $this->assertFalse(PaymentGateway::where('code', 'midtrans')->value('is_active'));
        $this->assertFalse(PaymentGateway::where('code', 'lynk')->value('is_active'));
    }

    public function test_checkout_rejects_method_of_inactive_gateway(): void
    {
        $spec = VpsSpec::where('is_active', true)->get()->first(fn ($s) => $s->isProviderAllowed('tencent'));

        $this->actingAs($this->customer())
            ->postJson('/checkout', [
                'vps_spec_id' => $spec->id,
                'control_panel' => 'none',
                'provider' => 'tencent',
                'datacenter_location' => 'singapore',
                'os' => 'ubuntu2404',
                'hostname' => 'cv-gw-' . uniqid(),
                'root_password' => 'RahasiaKuat123',
                'payment_method' => 'bca_va',
                'terms_accepted' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertSame(0, Order::count());
    }

    public function test_activating_gateway_opens_its_methods(): void
    {
        PaymentGateway::where('code', 'midtrans')->update(['is_active' => true]);

        $this->assertTrue(in_array('bca_va', PaymentGateway::activeMethods(), true));
        $this->assertTrue(in_array('gopay', PaymentGateway::activeMethods(), true));
    }

    public function test_checkout_page_renders_with_registry(): void
    {
        $this->get('/checkout')->assertStatus(200);
    }

    // ======================= Halaman & kredensial =======================

    public function test_admin_can_view_gateway_page(): void
    {
        $this->actingAs($this->admin())->get('/admin/payment-gateways')
            ->assertStatus(200)
            ->assertSee('Daftar Payment Gateway')
            ->assertSee('Lynk.id');
    }

    public function test_credentials_are_stored_encrypted_and_masked(): void
    {
        $gateway = PaymentGateway::where('code', 'midtrans')->first();

        $this->actingAs($this->admin())
            ->put("/admin/payment-gateways/{$gateway->id}", [
                'mode' => 'sandbox',
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
            'clear' => ['server_key' => 1],
        ]);

        $this->assertFalse($gateway->fresh()->hasCredential('server_key'));
    }

    public function test_audit_log_never_contains_secret_value(): void
    {
        $gateway = PaymentGateway::where('code', 'midtrans')->first();

        $this->actingAs($this->admin())->put("/admin/payment-gateways/{$gateway->id}", [
            'mode' => 'sandbox',
            'credentials' => ['server_key' => 'RAHASIA-TIDAK-BOLEH-MASUK-LOG'],
        ]);

        $log = AdminAuditLog::where('action', 'payment_gateway.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('RAHASIA-TIDAK-BOLEH-MASUK-LOG', (string) $log->description);
        $this->assertStringContainsString('server_key', (string) $log->description);
    }

    public function test_cannot_deactivate_last_active_gateway(): void
    {
        PaymentGateway::where('code', '!=', 'lynk')->update(['is_active' => false]);
        $lynk = PaymentGateway::where('code', 'lynk')->first();
        $lynk->update(['is_active' => true]);

        $this->actingAs($this->admin())
            ->put("/admin/payment-gateways/{$lynk->id}", ['mode' => 'production'])
            ->assertSessionHas('error');

        $this->assertTrue($lynk->fresh()->is_active);
    }

    public function test_lynk_webhook_uses_merchant_key_from_registry(): void
    {
        Config::set('services.lynk.merchant_key', null);
        PaymentGateway::where('code', 'lynk')->first()
            ->update(['credentials' => ['merchant_key' => 'kunci-dari-panel-777']]);

        [$payload, $signature] = $this->lynkPayload('reg_key_001', [], 'kunci-dari-panel-777');

        $this->postJson(route('api.webhooks.lynk'), $payload, ['X-Lynk-Signature' => $signature])
            ->assertStatus(200)
            ->assertJson(['message' => 'Lynk payment processed successfully.']);
    }

    // ======================= Pencatatan webhook =======================

    public function test_successful_webhook_is_logged_with_http_status(): void
    {
        [$payload, $signature] = $this->lynkPayload('log_ok_001');

        $this->postJson(route('api.webhooks.lynk'), $payload, ['X-Lynk-Signature' => $signature])->assertStatus(200);

        $event = WebhookEvent::where('event_id', 'log_ok_001')->first();
        $this->assertSame('processed', $event->processing_status);
        $this->assertSame(200, $event->http_status);
        $this->assertTrue($event->signature_verified);
    }

    /**
     * Sebelum Phase 4, kegagalan seperti ini hanya tersisa di file log.
     */
    public function test_failed_webhook_is_now_recorded_instead_of_lost(): void
    {
        [$payload, $signature] = $this->lynkPayload('log_fail_001', [
            'data' => ['message_data' => ['customer' => ['email' => '']]],
        ]);

        $this->postJson(route('api.webhooks.lynk'), $payload, ['X-Lynk-Signature' => $signature])->assertStatus(422);

        $event = WebhookEvent::where('event_id', 'log_fail_001')->first();
        $this->assertNotNull($event);
        $this->assertSame('failed', $event->processing_status);
        $this->assertSame(422, $event->http_status);
        $this->assertNotNull($event->processing_error);
        $this->assertTrue($event->isReplayable());
    }

    public function test_ignored_event_is_recorded(): void
    {
        [$payload, $signature] = $this->lynkPayload('log_ign_001', ['event' => 'payment.pending']);

        $this->postJson(route('api.webhooks.lynk'), $payload, ['X-Lynk-Signature' => $signature])
            ->assertStatus(200)
            ->assertJson(['message' => 'Event ignored.']);

        $this->assertSame('ignored', WebhookEvent::where('event_id', 'log_ign_001')->value('processing_status'));
    }

    public function test_invalid_signature_is_not_stored(): void
    {
        [$payload] = $this->lynkPayload('log_bad_sig');

        $this->postJson(route('api.webhooks.lynk'), $payload, ['X-Lynk-Signature' => 'palsu'])->assertStatus(403);

        $this->assertSame(0, WebhookEvent::where('event_id', 'log_bad_sig')->count());
    }

    // ======================= Proses ulang =======================

    public function test_admin_can_view_webhook_log(): void
    {
        [$payload, $signature] = $this->lynkPayload('log_view_001');
        $this->postJson(route('api.webhooks.lynk'), $payload, ['X-Lynk-Signature' => $signature]);

        $this->actingAs($this->admin())->get('/admin/webhooks')
            ->assertStatus(200)
            ->assertSee('log_view_001');

        $this->actingAs($this->admin())->get('/admin/webhooks?status=failed')->assertStatus(200);
    }

    public function test_replay_processes_a_failed_but_valid_event(): void
    {
        [$payload, $signature] = $this->lynkPayload('replay_001');

        $event = WebhookEvent::create([
            'provider' => 'lynk',
            'event_id' => 'replay_001',
            'event_type' => 'payment.received',
            'signature' => $signature,
            'signature_verified' => true,
            'payload' => $payload,
            'processing_status' => 'failed',
            'processing_error' => 'Simulasi kegagalan database sementara.',
            'http_status' => 500,
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/webhooks/{$event->id}/replay")
            ->assertSessionHas('success');

        $event->refresh();
        $this->assertSame('processed', $event->processing_status);
        $this->assertNotNull($event->order_id);
        $this->assertSame('paid', Order::find($event->order_id)->status);
        $this->assertSame(2, $event->attempts);
        $this->assertNotNull($event->last_replayed_at);
    }

    /**
     * Pengaman uang: proses ulang berkali-kali tidak boleh mencatat pembayaran ganda.
     */
    public function test_replay_never_creates_double_payment(): void
    {
        [$payload, $signature] = $this->lynkPayload('replay_dup_001');

        $event = WebhookEvent::create([
            'provider' => 'lynk',
            'event_id' => 'replay_dup_001',
            'signature' => $signature,
            'signature_verified' => true,
            'payload' => $payload,
            'processing_status' => 'failed',
        ]);

        $this->actingAs($this->admin())->post("/admin/webhooks/{$event->id}/replay");
        $this->actingAs($this->admin())->post("/admin/webhooks/{$event->id}/replay")->assertSessionHas('error');

        $this->assertSame(1, PaymentTransaction::where('provider_transaction_id', 'replay_dup_001')->count());
    }

    public function test_replay_detects_existing_settled_transaction(): void
    {
        [$payload, $signature] = $this->lynkPayload('replay_settled_001');

        // Pembayaran pertama berhasil lewat webhook asli.
        $this->postJson(route('api.webhooks.lynk'), $payload, ['X-Lynk-Signature' => $signature])->assertStatus(200);

        // Status event terlanjur ditimpa menjadi gagal (mis. salah ubah manual).
        WebhookEvent::where('event_id', 'replay_settled_001')->update(['processing_status' => 'failed']);
        $event = WebhookEvent::where('event_id', 'replay_settled_001')->first();

        $this->actingAs($this->admin())->post("/admin/webhooks/{$event->id}/replay");

        $this->assertSame(1, PaymentTransaction::where('provider_transaction_id', 'replay_settled_001')->count());
        $this->assertSame('processed', $event->fresh()->processing_status);
    }

    public function test_unverified_event_cannot_be_replayed(): void
    {
        [$payload] = $this->lynkPayload('replay_unverified');

        $event = WebhookEvent::create([
            'provider' => 'lynk',
            'event_id' => 'replay_unverified',
            'signature_verified' => false,
            'payload' => $payload,
            'processing_status' => 'failed',
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/webhooks/{$event->id}/replay")
            ->assertSessionHas('error');

        $this->assertSame(0, PaymentTransaction::where('provider_transaction_id', 'replay_unverified')->count());
    }

    public function test_customer_cannot_access_gateway_pages_or_replay(): void
    {
        $customer = $this->customer();
        $event = WebhookEvent::create([
            'provider' => 'lynk',
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
