<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\VpsSpec;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Services\QrisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;
    protected VpsSpec $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin = User::where('is_admin', true)->first();

        $this->customer = User::create([
            'full_name' => 'Budi Santoso',
            'username' => 'budisantoso',
            'email' => 'budi@example.com',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);

        $this->spec = VpsSpec::where('is_active', true)->first();
    }

    public function test_qris_service_generates_valid_dynamic_payload_and_data_uri(): void
    {
        $service = app(QrisService::class);

        $payload80k = $service->generatePayload(80000);
        $this->assertStringContainsString('010212', $payload80k);
        $this->assertStringContainsString('540580000', $payload80k);
        $this->assertStringContainsString('5909MERCHANT', $payload80k);
        $this->assertStringEndsWith('6304071A', $payload80k);

        $svgUri = $service->generateDataUri(80000);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $svgUri);
    }

    public function test_public_qris_render_endpoint(): void
    {
        $response = $this->getJson(route('qris.render', ['amount' => 110000]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['amount', 'payload', 'svg_data_uri']);
        $this->assertStringContainsString('5406110000', $response->json('payload'));
    }

    public function test_admin_can_view_payments_page(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'tencent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'vps-test',
            'amount' => 80000,
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.payments'));

        $response->assertStatus(200);
        $response->assertSee('Verifikasi Pembayaran');
        $response->assertSee('MERCHANT');
        $response->assertSee('Rp 80.000');
    }

    public function test_admin_can_approve_payment_and_notification_is_sent(): void
    {
        Notification::fake();

        $order = Order::create([
            'customer_id' => $this->customer->id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'tencent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'vps-approve',
            'amount' => 80000,
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'billing_cycle' => 'monthly',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-TEST-001',
            'amount' => 80000,
            'status' => 'sent',
            'issued_at' => now(),
            'due_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.payments.approve', $order->id), [
            'reference' => 'DANA-REF-12345',
            'note' => 'Mutasi cocok',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $invoice->refresh();

        $this->assertEquals('paid', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertEquals('paid', $invoice->status);

        Notification::assertSentTo(
            $this->customer,
            PaymentReceivedNotification::class,
            function ($notification) use ($order) {
                return $notification->order->id === $order->id;
            }
        );
    }

    public function test_admin_can_hold_payment(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'tencent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'vps-hold',
            'amount' => 80000,
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.payments.hold', $order->id), [
            'note' => 'Mutasi belum tampak di aplikasi',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('info');

        $order->refresh();
        $this->assertEquals('pending', $order->status);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'reason' => 'Verifikasi ditunda: Mutasi belum tampak di aplikasi',
        ]);
    }

    public function test_admin_can_reject_payment_and_notification_is_sent(): void
    {
        Notification::fake();

        $order = Order::create([
            'customer_id' => $this->customer->id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'tencent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'vps-reject',
            'amount' => 80000,
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'billing_cycle' => 'monthly',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-TEST-002',
            'amount' => 80000,
            'status' => 'sent',
            'issued_at' => now(),
            'due_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.payments.reject', $order->id), [
            'reason' => 'Mutasi tidak ditemukan di akun DANA Bisnis',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $invoice->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('cancelled', $invoice->status);

        Notification::assertSentTo(
            $this->customer,
            PaymentRejectedNotification::class,
            function ($notification) use ($order) {
                return $notification->order->id === $order->id &&
                       $notification->reason === 'Mutasi tidak ditemukan di akun DANA Bisnis';
            }
        );
    }
}
