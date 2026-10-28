<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LynkCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        \App\Models\PaymentGateway::where('code', 'lynk')->update(['is_active' => true]);
    }

    public function test_checkout_with_lynk_redirects_to_payment_url_when_configured()
    {
        $spec = VpsSpec::create([
            'name' => 'Student Basic',
            'cpu' => 1,
            'ram' => 1,
            'disk' => 20,
            'bandwidth' => 1000,
            'base_price' => 50000,
            'cost_price' => 30000,
            'sell_price' => 1000,
            'is_active' => true,
            'payment_url' => 'https://lynk.id/cravacloud/student-basic-test',
        ]);

        $user = User::create([
            'username' => 'testuser1',
            'email' => 'test1@example.com',
            'password' => bcrypt('password123'),
            'full_name' => 'Test User 1',
        ]);

        $response = $this->actingAs($user)->post(route('order.store'), [
            'vps_spec_id' => $spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'billing_cycle' => 'monthly',
            'hostname' => 'cv-test-lynk',
            'root_password' => 'secret12345',
            'terms_accepted' => 1,
            'payment_method' => 'lynk',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', [
            'customer_id' => $user->id,
            'vps_spec_id' => $spec->id,
            'payment_method' => 'lynk',
            'status' => 'pending',
        ]);

        $response->assertRedirect('https://lynk.id/cravacloud/student-basic-test');
    }

    public function test_checkout_with_lynk_redirects_to_payment_gateway_when_no_payment_url()
    {
        $spec = VpsSpec::create([
            'name' => 'Student Basic',
            'cpu' => 1,
            'ram' => 1,
            'disk' => 20,
            'bandwidth' => 1000,
            'base_price' => 50000,
            'cost_price' => 30000,
            'sell_price' => 80000,
            'is_active' => true,
            'payment_url' => null,
        ]);

        $user = User::create([
            'username' => 'testuser2',
            'email' => 'test2@example.com',
            'password' => bcrypt('password123'),
            'full_name' => 'Test User 2',
        ]);

        $response = $this->actingAs($user)->post(route('order.store'), [
            'vps_spec_id' => $spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'billing_cycle' => 'monthly',
            'hostname' => 'cv-test-gateway',
            'root_password' => 'secret12345',
            'terms_accepted' => 1,
            'payment_method' => 'lynk',
        ]);

        $order = Order::where('customer_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('lynk', $order->payment_method);
        $this->assertEquals('Lynk.id Checkout', $order->payment_method_name);

        $response->assertRedirect(route('order.payment', $order->id));
    }

    public function test_checkout_json_request_returns_redirect_url()
    {
        $spec = VpsSpec::create([
            'name' => 'Student Basic',
            'cpu' => 1,
            'ram' => 1,
            'disk' => 20,
            'bandwidth' => 1000,
            'base_price' => 50000,
            'cost_price' => 30000,
            'sell_price' => 1000,
            'is_active' => true,
            'payment_url' => 'https://lynk.id/cravacloud/n4qjjoj28r07/checkout',
        ]);

        $user = User::create([
            'username' => 'testuserjson',
            'email' => 'testjson@example.com',
            'password' => bcrypt('password123'),
            'full_name' => 'Test User JSON',
        ]);

        $response = $this->actingAs($user)->postJson(route('order.store'), [
            'vps_spec_id' => $spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'billing_cycle' => 'monthly',
            'hostname' => 'cv-test-json',
            'root_password' => 'secret12345',
            'terms_accepted' => 1,
            'payment_method' => 'lynk',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'redirect_url' => 'https://lynk.id/cravacloud/n4qjjoj28r07/checkout',
        ]);
    }
}
