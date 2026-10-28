<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerProvisioningStandardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_checkout_allows_null_root_password_from_client(): void
    {
        $spec = VpsSpec::first();

        // 1. Client checkout does NOT require root_password (generated upstream by retail provider)
        $responseValid = $this->post('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'test-vps-server',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'John Doe',
            'email' => 'john.valid@test.com',
            'password' => 'UserAccountPass123!',
        ]);

        $responseValid->assertSessionHasNoErrors();
        $order = Order::whereHas('customer', fn($q) => $q->where('email', 'john.valid@test.com'))->first();
        $this->assertNotNull($order);
        $this->assertNull($order->root_password);
    }

    public function test_admin_provision_locks_customer_parameters_and_requires_root_password(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();

        $customer = User::create([
            'username' => 'clientlocked',
            'email' => 'clientlocked@test.com',
            'full_name' => 'Client Locked',
            'password' => bcrypt('password123'),
            'channel' => 'website',
        ]);
        $org = $customer->createPersonalOrganization();
        $customer->switchToOrganization($org);

        $spec = VpsSpec::first();

        // Order with NO control panel ('none'), initially no root password
        $orderNoPanel = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cloudeka',
            'hostname' => 'custom-client-hostname',
            'root_password' => null,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 80000,
            'paid_at' => now(),
        ]);

        // When admin provisions without root_password, validation fails
        $responseMissingPw = $this->actingAs($admin)->post('/admin/orders/' . $orderNoPanel->id . '/provision', [
            'public_ip' => '103.150.10.1',
        ]);
        $responseMissingPw->assertSessionHasErrors('root_password');

        // When order has control_panel 'none', app_url is NOT required, root_password IS required
        $responseNoPanel = $this->actingAs($admin)->post('/admin/orders/' . $orderNoPanel->id . '/provision', [
            'public_ip' => '103.150.10.1',
            'root_password' => 'AdminRetailPass@99',
        ]);

        $responseNoPanel->assertSessionHas('success');
        $this->assertDatabaseHas('orders', ['id' => $orderNoPanel->id, 'status' => 'active']);

        $instance = VpsInstance::where('order_id', $orderNoPanel->id)->first();
        $this->assertNotNull($instance);
        $this->assertEquals('custom-client-hostname', $instance->hostname);
        $this->assertEquals('AdminRetailPass@99', $instance->initial_root_password);
        $this->assertEquals('ubuntu2404', $instance->os);
        $this->assertNull($instance->app_url);

        $orderNoPanel->refresh();
        $this->assertEquals('AdminRetailPass@99', $orderNoPanel->root_password);

        // Order WITH control panel ('coolify')
        $orderWithPanel = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'hostname' => 'coolify-app-node',
            'root_password' => null,
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 80000,
            'paid_at' => now(),
        ]);

        // When order has panel, app_url IS required
        $responseMissingAppUrl = $this->actingAs($admin)->post('/admin/orders/' . $orderWithPanel->id . '/provision', [
            'public_ip' => '103.150.10.2',
            'root_password' => 'CustomerSuperPass2026!',
        ]);
        $responseMissingAppUrl->assertSessionHasErrors('app_url');

        // With app_url and root_password provided
        $responseWithAppUrl = $this->actingAs($admin)->post('/admin/orders/' . $orderWithPanel->id . '/provision', [
            'public_ip' => '103.150.10.2',
            'root_password' => 'CustomerSuperPass2026!',
            'app_url' => 'https://103.150.10.2:8000',
        ]);
        $responseWithAppUrl->assertSessionHas('success');

        $panelInstance = VpsInstance::where('order_id', $orderWithPanel->id)->first();
        $this->assertNotNull($panelInstance);
        $this->assertEquals('coolify-app-node', $panelInstance->hostname);
        $this->assertEquals('CustomerSuperPass2026!', $panelInstance->initial_root_password);
        $this->assertEquals('https://103.150.10.2:8000', $panelInstance->app_url);
    }

    public function test_admin_orders_page_renders_provision_button_with_order_id_and_orders_data(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $customer = User::create([
            'username' => 'clienttestbtn',
            'email' => 'clienttestbtn@test.com',
            'full_name' => 'Client Test Button',
            'password' => bcrypt('password123'),
            'channel' => 'website',
        ]);
        $org = $customer->createPersonalOrganization();
        $spec = VpsSpec::first();

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'cloudeka',
            'hostname' => 'node-test-button',
            'root_password' => 'PassBtnClick123!',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/orders');

        $response->assertStatus(200);
        $response->assertSee('@click.stop="openProvision(' . $order->id . ')"', false);
        $response->assertSee('PassBtnClick123!');
        $response->assertSee('Setup VPS');
    }
}
