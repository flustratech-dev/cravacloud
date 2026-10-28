<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopeeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_shopee_admin_page_renders_with_specs_and_operating_systems(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();

        $response = $this->actingAs($admin)->get('/admin/shopee');
        $response->assertStatus(200);
        $response->assertSee('Proses Pesanan Shopee dan Kelola Kredensial');
        $response->assertSee('Ekstrak Cepat dari Chat Shopee (Smart Paste)');
        $response->assertSee('Riwayat Pesanan Shopee');
    }

    public function test_admin_can_process_shopee_order_with_windows_os(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $spec = VpsSpec::where('name', 'Standard')->first();

        $response = $this->actingAs($admin)->post('/admin/shopee/process', [
            'shopee_order_id' => '240915SHPWIN99',
            'customer_name' => 'Windows Buyer',
            'customer_email' => 'winbuyer@test.id',
            'customer_phone' => '081234567890',
            'vps_spec_id' => $spec->id,
            'provider' => 'tencent',
            'control_panel' => 'none',
            'datacenter_location' => 'singapore',
            'os' => 'windows2022',
            'hostname' => 'win-server-bot',
        ]);

        $response->assertSessionHas('shopee_credentials');
        $credentials = session('shopee_credentials');
        $this->assertEquals('Windows Buyer', $credentials['customer_name']);
        $this->assertEquals('240915SHPWIN99', $credentials['shopee_order_id']);

        $order = Order::where('shopee_order_id', '240915SHPWIN99')->first();
        $this->assertNotNull($order);
        $this->assertEquals('paid', $order->status);
        $this->assertEquals('windows2022', $order->os);
        $this->assertEquals('win-server-bot', $order->hostname);
    }

    public function test_admin_can_auto_provision_shopee_order(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $spec = VpsSpec::where('name', 'Student Basic')->first();

        $response = $this->actingAs($admin)->post('/admin/shopee/process', [
            'shopee_order_id' => '240915AUTOPROV1',
            'customer_name' => 'Auto Provision Buyer',
            'customer_email' => 'autoprov@test.id',
            'customer_phone' => '081299998888',
            'vps_spec_id' => $spec->id,
            'provider' => 'cloudeka',
            'control_panel' => 'coolify',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'auto_provision' => 1,
            'public_ip' => '103.150.12.99',
            'ssh_port' => 22,
        ]);

        $response->assertSessionHas('shopee_credentials');
        $credentials = session('shopee_credentials');
        $this->assertTrue($credentials['is_provisioned']);
        $this->assertEquals('103.150.12.99', $credentials['public_ip']);

        $order = Order::where('shopee_order_id', '240915AUTOPROV1')->first();
        $this->assertNotNull($order);
        $this->assertEquals('active', $order->status);

        $instance = VpsInstance::where('order_id', $order->id)->first();
        $this->assertNotNull($instance);
        $this->assertEquals('103.150.12.99', $instance->public_ip);
        $this->assertEquals('running', $instance->status);
    }

    public function test_admin_can_filter_shopee_orders(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $spec = VpsSpec::first();

        // Create 2 test orders
        Order::create([
            'customer_id' => $admin->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'tencent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'paid',
            'channel' => 'shopee',
            'shopee_order_id' => 'SHP-FILTER-AAA',
            'amount' => 80000,
        ]);

        Order::create([
            'customer_id' => $admin->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'tencent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'cancelled',
            'channel' => 'shopee',
            'shopee_order_id' => 'SHP-FILTER-BBB',
            'amount' => 80000,
        ]);

        $res = $this->actingAs($admin)->get('/admin/shopee?q=SHP-FILTER-AAA');
        $res->assertStatus(200);
        $res->assertSee('SHP-FILTER-AAA');
        $res->assertDontSee('SHP-FILTER-BBB');

        $resStatus = $this->actingAs($admin)->get('/admin/shopee?status=cancelled');
        $resStatus->assertStatus(200);
        $resStatus->assertSee('SHP-FILTER-BBB');
        $resStatus->assertDontSee('SHP-FILTER-AAA');
    }
}
