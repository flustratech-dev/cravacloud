<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\VpsSpec;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private VpsSpec $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->customer = User::factory()->create([
            'is_admin' => false,
        ]);
        $org = $this->customer->createPersonalOrganization();
        $this->customer->switchToOrganization($org);

        $this->spec = VpsSpec::create([
            'name' => 'Starter Test',
            'category' => 'vps',
            'cpu' => 1,
            'ram' => 1,
            'disk' => 20,
            'bandwidth' => 1000,
            'cost_price' => 50000,
            'sell_price' => 80000,
            'is_active' => true,
        ]);
    }

    public function test_admin_payments_route_is_decommissioned(): void
    {
        $this->actingAs($this->admin)->get('/admin/payments')->assertNotFound();
    }

    public function test_admin_can_mark_order_paid_and_notification_is_sent(): void
    {
        Notification::fake();

        $order = Order::create([
            'customer_id' => $this->customer->id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'cravacloud',
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

        $response = $this->actingAs($this->admin)->post(route('admin.orders.mark-paid', $order->id), [
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

    public function test_admin_can_cancel_pending_order(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'cravacloud',
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

        $response = $this->actingAs($this->admin)->post(route('admin.orders.cancel', $order->id), [
            'reason' => 'Mutasi tidak ditemukan di akun DANA Bisnis',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $invoice->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('cancelled', $invoice->status);
    }
}
