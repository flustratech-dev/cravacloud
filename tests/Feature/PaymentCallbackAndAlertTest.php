<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VpsSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCallbackAndAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private VpsSpec $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->user = User::create([
            'username' => 'buyer_test',
            'email' => 'buyer_test@example.com',
            'password' => bcrypt('password123'),
            'full_name' => 'Buyer Test',
        ]);

        $this->spec = VpsSpec::first();
    }

    public function test_payment_callback_redirects_paid_order_to_dashboard_with_success_param(): void
    {
        $order = Order::create([
            'customer_id' => $this->user->id,
            'organization_id' => $this->user->current_organization_id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'coolify',
            'hostname' => 'srv-test-paid',
            'status' => 'paid',
            'paid_at' => now(),
            'channel' => 'website',
            'payment_method' => 'lynk',
            'amount' => 80000,
            'currency' => 'IDR',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('order.callback', ['order_id' => $order->id]));

        $response->assertRedirect(route('dashboard.index', [
            'payment_success' => 1,
            'order_id' => $order->id,
        ]));
    }

    public function test_payment_callback_does_not_give_success_param_if_order_is_pending(): void
    {
        $order = Order::create([
            'customer_id' => $this->user->id,
            'organization_id' => $this->user->current_organization_id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'coolify',
            'hostname' => 'srv-test-pending',
            'status' => 'pending',
            'paid_at' => null,
            'channel' => 'website',
            'payment_method' => 'lynk',
            'amount' => 80000,
            'currency' => 'IDR',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('order.callback', ['order_id' => $order->id]));

        $response->assertRedirect(route('order.payment.status', $order->id));
        $this->assertStringNotContainsString('payment_success=1', $response->headers->get('Location'));
    }

    public function test_dashboard_renders_success_alert_for_paid_order(): void
    {
        $order = Order::create([
            'customer_id' => $this->user->id,
            'organization_id' => $this->user->current_organization_id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'coolify',
            'hostname' => 'srv-alert-test',
            'status' => 'paid',
            'paid_at' => now(),
            'channel' => 'website',
            'payment_method' => 'lynk',
            'amount' => 80000,
            'currency' => 'IDR',
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $this->user->id,
            'organization_id' => $this->user->current_organization_id,
            'invoice_number' => 'INV-TEST-999',
            'amount' => 80000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.index', ['payment_success' => 1, 'order_id' => $order->id]));

        $response->assertOk();
        $response->assertSee('Pembayaran Berhasil Dikonfirmasi!');
        $response->assertSee('INV-TEST-999');
        $response->assertSee('srv-alert-test');
        $response->assertSee('LUNAS');
    }

    public function test_dashboard_does_not_render_success_alert_for_unpaid_order(): void
    {
        $order = Order::create([
            'customer_id' => $this->user->id,
            'organization_id' => $this->user->current_organization_id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'coolify',
            'hostname' => 'srv-unpaid-test',
            'status' => 'pending',
            'paid_at' => null,
            'channel' => 'website',
            'payment_method' => 'lynk',
            'amount' => 80000,
            'currency' => 'IDR',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['shown_payment_success_orders' => []])
            ->get(route('dashboard.index', ['payment_success' => 1, 'order_id' => $order->id]));

        $response->assertOk();
        $response->assertDontSee('Pembayaran Berhasil Dikonfirmasi!');
    }

    public function test_payment_callback_with_lynk_ref_id_and_session_recovery(): void
    {
        $order = Order::create([
            'customer_id' => $this->user->id,
            'organization_id' => $this->user->current_organization_id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'coolify',
            'hostname' => 'srv-lynk-paid',
            'status' => 'paid',
            'paid_at' => now(),
            'channel' => 'website',
            'payment_method' => 'lynk',
            'amount' => 1000,
            'currency' => 'IDR',
        ]);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'lynk',
            'provider_transaction_id' => 'LYNK-TX-998877',
            'provider_order_ref' => 'MSG-123456',
            'payment_method' => 'lynk',
            'amount' => 1000,
            'currency' => 'IDR',
            'status' => 'settled',
        ]);

        // Skenario: User tidak sedang login di session browser (misal dari redirect Lynk),
        // tapi browser memiliki session last_order_id
        $response = $this->withSession(['last_order_id' => $order->id])
            ->get(route('payment.callback', ['refId' => 'LYNK-TX-998877']));

        $response->assertRedirect(route('dashboard.index', [
            'payment_success' => 1,
            'order_id' => $order->id,
        ]));

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_payment_callback_resolves_order_from_session_without_query_params(): void
    {
        $order = Order::create([
            'customer_id' => $this->user->id,
            'organization_id' => $this->user->current_organization_id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'coolify',
            'hostname' => 'srv-lynk-session',
            'status' => 'paid',
            'paid_at' => now(),
            'channel' => 'website',
            'payment_method' => 'lynk',
            'amount' => 1000,
            'currency' => 'IDR',
        ]);

        $response = $this->withSession(['last_order_id' => $order->id])
            ->get(route('payment.callback'));

        $response->assertRedirect(route('dashboard.index', [
            'payment_success' => 1,
            'order_id' => $order->id,
        ]));

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_order_status_page_accessible_with_matching_session(): void
    {
        $order = Order::create([
            'customer_id' => $this->user->id,
            'organization_id' => $this->user->current_organization_id,
            'vps_spec_id' => $this->spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'coolify',
            'hostname' => 'srv-pending-session',
            'status' => 'pending',
            'paid_at' => null,
            'channel' => 'website',
            'payment_method' => 'lynk',
            'amount' => 1000,
            'currency' => 'IDR',
        ]);

        $response = $this->withSession(['last_order_id' => $order->id])
            ->get(route('order.payment.status', $order->id));

        $response->assertOk();
        $this->assertAuthenticatedAs($this->user);
    }
}