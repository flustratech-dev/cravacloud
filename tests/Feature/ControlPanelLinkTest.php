<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Link control panel yang tampil ke pelanggan harus berasal dari isian admin
 * (kolom app_url), bukan tebakan http://IP:8000.
 */
class ControlPanelLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
    }

    private function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    private function customer(string $username = 'panelcustomer'): User
    {
        $customer = User::create([
            'username' => $username,
            'email' => $username . '@example.com',
            'full_name' => 'Pelanggan Panel',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $customer->switchToOrganization($customer->createPersonalOrganization());

        return $customer->fresh();
    }

    private function paidOrder(User $customer, string $controlPanel = 'coolify'): Order
    {
        $spec = VpsSpec::where('category', 'vps')->first() ?? VpsSpec::first();

        return Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => $controlPanel,
            'provider' => 'tencent',
            'hostname' => 'panel-node-' . uniqid(),
            'root_password' => 'PasswordPelanggan#1',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $spec->sell_price,
            'paid_at' => now(),
        ]);
    }

    private function runningVps(User $customer, array $attributes = []): VpsInstance
    {
        $order = $this->paidOrder($customer);

        return VpsInstance::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => 'srv-panel',
            'public_ip' => '139.180.200.30',
            'os' => 'ubuntu2404',
            'status' => 'running',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'expires_at' => now()->addDays(20),
        ], $attributes));
    }

    // ======================= Tampilan pelanggan =======================

    public function test_customer_sees_admin_provided_panel_link_instead_of_ip_guess(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, ['app_url' => 'https://coolify.pelanggan.id']);

        $response = $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}");

        $response->assertOk()
            ->assertSee('href="https://coolify.pelanggan.id"', false)
            ->assertSee('Buka Control Panel')
            ->assertDontSee('139.180.200.30:8000');
    }

    public function test_customer_without_panel_link_gets_honest_message_not_ip_guess(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, ['app_url' => null]);

        $response = $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}");

        $response->assertOk()
            ->assertSee('Alamat panel belum tersedia')
            ->assertDontSee('139.180.200.30:8000')
            ->assertDontSee('>Panel Web<', false);
    }

    public function test_regular_vps_with_panel_link_is_not_treated_as_ai_package(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, [
            'order_id' => null, // tanpa spec order, deteksi memakai kolom instance
            'app_url' => 'https://coolify.pelanggan.id',
        ]);

        $this->assertFalse($vps->isAiPackage());
        $this->assertSame('https://coolify.pelanggan.id', $vps->panel_url);
    }

    public function test_database_package_web_manager_prefers_admin_link(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, [
            'order_id' => null,
            'control_panel' => 'managed_database',
            'db_engine' => 'postgres',
            'db_manager' => 'cloudbeaver',
            'app_url' => 'https://db-panel.pelanggan.id',
        ]);

        $this->assertSame('https://db-panel.pelanggan.id', $vps->web_manager_url_resolved);

        $vps->update(['app_url' => null]);
        $this->assertSame('https://139.180.200.30:8080', $vps->fresh()->web_manager_url_resolved);
    }

    // ======================= Input admin =======================

    public function test_provision_rejects_panel_link_without_scheme(): void
    {
        $order = $this->paidOrder($this->customer());

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->id}/provision", [
                'public_ip' => '103.150.10.5',
                'app_url' => 'coolify.pelanggan.id',
            ])
            ->assertSessionHasErrors('app_url');

        $this->assertNull(VpsInstance::where('order_id', $order->id)->first());
    }

    public function test_provisioned_panel_link_reaches_customer_dashboard(): void
    {
        $customer = $this->customer();
        $order = $this->paidOrder($customer);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->id}/provision", [
                'public_ip' => '103.150.10.6',
                'app_url' => 'http://103.150.10.6:8000',
                'root_password' => 'CravaSecretPass123!',
            ])
            ->assertSessionHas('success');

        $vps = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $vps->update(['status' => 'running']);

        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertSee('href="http://103.150.10.6:8000"', false);
    }

    public function test_admin_can_fix_panel_link_from_instances_page(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, ['app_url' => null]);

        $this->actingAs($this->admin())->get('/admin/instances')
            ->assertOk()
            ->assertSee('Link panel belum diisi')
            ->assertSee(route('admin.instances.panel-url', $vps->id), false);

        $this->actingAs($this->admin())
            ->post(route('admin.instances.panel-url', $vps->id), ['app_url' => 'https://panel.baru.id'])
            ->assertSessionHas('success');

        $this->assertSame('https://panel.baru.id', $vps->fresh()->panel_url);
        $this->assertDatabaseHas('vps_activity_logs', ['vps_instance_id' => $vps->id, 'action' => 'panel_url_updated']);

        // Format salah ditolak, data lama tetap.
        $this->actingAs($this->admin())
            ->post(route('admin.instances.panel-url', $vps->id), ['app_url' => 'panel tanpa skema'])
            ->assertSessionHasErrors('app_url');
        $this->assertSame('https://panel.baru.id', $vps->fresh()->panel_url);

        // Mengosongkan link diperbolehkan.
        $this->actingAs($this->admin())
            ->post(route('admin.instances.panel-url', $vps->id), ['app_url' => ''])
            ->assertSessionHas('success');
        $this->assertNull($vps->fresh()->panel_url);
    }

    public function test_admin_instances_category_ignores_panel_link(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, [
            'hostname' => 'coolify-dengan-link',
            'app_url' => 'https://coolify.pelanggan.id',
        ]);

        // VPS biasa dengan link panel tetap masuk kategori Cloud VPS, bukan AI Agent.
        $this->actingAs($this->admin())->get('/admin/instances?category=vps')
            ->assertOk()
            ->assertSee($vps->hostname);

        $this->actingAs($this->admin())->get('/admin/instances?category=ai')
            ->assertOk()
            ->assertDontSee($vps->hostname);
    }

    public function test_customer_cannot_change_panel_link(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, ['app_url' => 'https://asli.pelanggan.id']);

        $this->actingAs($customer)
            ->post(route('admin.instances.panel-url', $vps->id), ['app_url' => 'https://palsu.id']);

        $this->assertSame('https://asli.pelanggan.id', $vps->fresh()->panel_url);
    }

    public function test_shopee_auto_provision_stores_panel_link(): void
    {
        $spec = VpsSpec::where('category', 'vps')->first() ?? VpsSpec::first();

        $this->actingAs($this->admin())->post('/admin/shopee/process', [
            'shopee_order_id' => 'SHOPEE-PANEL-001',
            'customer_name' => 'Pembeli Shopee',
            'customer_email' => 'pembeli.panel@example.com',
            'vps_spec_id' => $spec->id,
            'provider' => 'tencent',
            'control_panel' => 'coolify',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'auto_provision' => 1,
            'public_ip' => '103.150.20.7',
            'app_url' => 'http://103.150.20.7:8000',
        ])->assertSessionHasNoErrors();

        $order = Order::where('shopee_order_id', 'SHOPEE-PANEL-001')->firstOrFail();
        $this->assertSame('http://103.150.20.7:8000', $order->vpsInstance->panel_url);
    }

    public function test_shopee_rejects_panel_link_without_scheme(): void
    {
        $spec = VpsSpec::where('category', 'vps')->first() ?? VpsSpec::first();

        $this->actingAs($this->admin())->post('/admin/shopee/process', [
            'shopee_order_id' => 'SHOPEE-PANEL-002',
            'customer_name' => 'Pembeli Shopee',
            'customer_email' => 'pembeli.panel2@example.com',
            'vps_spec_id' => $spec->id,
            'provider' => 'tencent',
            'control_panel' => 'coolify',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'auto_provision' => 1,
            'public_ip' => '103.150.20.8',
            'app_url' => 'panel.tanpa-skema.id',
        ])->assertSessionHasErrors('app_url');

        $this->assertNull(Order::where('shopee_order_id', 'SHOPEE-PANEL-002')->first());
    }
}
