<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderPaymentPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_payment_page_redirects_midtrans_method_to_status_page(): void
    {
        $user = User::create([
            'username' => 'customertest1',
            'email' => 'customer1@test.com',
            'password' => bcrypt('password123'),
            'full_name' => 'Customer Test 1',
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $spec = VpsSpec::find(1);
        $org = $user->createPersonalOrganization();

        PaymentGateway::where('code', 'midtrans')->firstOrFail()->update([
            'is_active' => true,
            'mode' => 'sandbox',
            'credentials' => [
                'server_key' => 'SB-Mid-server-test',
                'client_key' => 'SB-Mid-client-test',
            ],
        ]);

        Http::fake([
            'https://api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404'], 404),
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-page-test-001',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-page-test-001',
            ], 201),
        ]);

        $order = Order::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'cv-test-payment-page',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'online_payment',
            'amount' => 100000,
        ]);

        $response = $this->actingAs($user)->get(route('order.payment', $order->id));
        $response->assertRedirect(route('order.payment.status', $order->id));

        $statusResponse = $this->actingAs($user)->get(route('order.payment.status', $order->id));
        $statusResponse->assertStatus(200);
        $statusResponse->assertSee('Menunggu Konfirmasi Pembayaran');
        $statusResponse->assertSee('snap-page-test-001');
    }

    public function test_payment_page_loads_midtrans_snap_for_order_without_explicit_method(): void
    {
        $user = User::create([
            'username' => 'customertest2',
            'email' => 'customer2@test.com',
            'password' => bcrypt('password123'),
            'full_name' => 'Customer Test 2',
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $spec = VpsSpec::find(1);
        $org = $user->createPersonalOrganization();

        $order = Order::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'cv-test-payment-page-midtrans',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => null,
            'amount' => $spec->sell_price,
        ]);

        $response = $this->actingAs($user)->get(route('order.payment', $order->id));

        $response->assertStatus(200);
        $response->assertSee('Midtrans');
    }
}
