<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave5AdminArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private VpsSpec $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->customer = User::factory()->create([
            'is_admin' => false,
        ]);
        $org = $this->customer->createPersonalOrganization();
        $this->customer->switchToOrganization($org);

        $this->spec = VpsSpec::where('is_active', true)->where('category', 'vps')->firstOrFail();
    }

    public function test_sidebar_renders_4_groups_and_11_menus(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.index'));
        $response->assertOk();

        // 4 Groups
        $response->assertSee('Operasional Cloud');
        $response->assertSee('Katalog & Keuangan', false);
        $response->assertSee('Pelanggan & Dukungan', false);
        $response->assertSee('Sistem');

        // 11 Menus
        $response->assertSee('Ringkasan');
        $response->assertSee('Orders & Shopee', false);
        $response->assertSee('Cloud Instances');
        $response->assertSee('Provisioning & Health', false);
        $response->assertSee('Paket & Stack', false);
        $response->assertSee('Billing & Margin', false);
        $response->assertSee('Payment & Webhook', false);
        $response->assertSee('Laporan');
        $response->assertSee('Pelanggan');
        $response->assertSee('Helpdesk & Abuse', false);
        $response->assertSee('Pengaturan & Audit', false);

        // Decommissioned items not in sidebar
        $response->assertDontSee('Papan Fulfillment');
        $response->assertDontSee('Pembelian Supplier');
    }

    public function test_admin_dashboard_kpis_render_correctly(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.index'));
        $response->assertOk();

        $response->assertSee('Perlu Tindakan Admin');
        $response->assertSee('VPS Running');
        $response->assertSee('Saldo Tencent &amp; Kuota', false);
        $response->assertSee('Omzet &amp; Margin Bulan Ini', false);
    }

    public function test_decommissioned_routes_redirect_or_404(): void
    {
        // /admin/payments -> 404
        $this->actingAs($this->admin)->get('/admin/payments')->assertNotFound();

        // /admin/fulfillment -> 302 redirect to /admin/orders?status=needs_provision
        $this->actingAs($this->admin)->get('/admin/fulfillment')
            ->assertRedirect(route('admin.orders', ['status' => 'needs_provision']));
    }

    public function test_split_domain_controllers_accessible(): void
    {
        // Dashboard
        $this->actingAs($this->admin)->get(route('admin.index'))->assertOk();

        // Orders
        $this->actingAs($this->admin)->get(route('admin.orders'))->assertOk();

        // Shopee
        $this->actingAs($this->admin)->get(route('admin.shopee'))->assertOk();

        // Instances
        $this->actingAs($this->admin)->get(route('admin.instances'))->assertOk();

        // Monitoring
        $this->actingAs($this->admin)->get(route('admin.monitoring'))->assertOk();

        // Packages
        $this->actingAs($this->admin)->get(route('admin.packages.index'))->assertOk();

        // Reports
        $this->actingAs($this->admin)->get(route('admin.reports'))->assertOk();

        // Customers
        $this->actingAs($this->admin)->get(route('admin.customers'))->assertOk();

        // Tickets
        $this->actingAs($this->admin)->get(route('admin.tickets'))->assertOk();
    }

    public function test_vps_instance_customer_status_label_tencent_vs_manual(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'organization_id' => $this->customer->current_organization_id,
            'vps_spec_id' => $this->spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 100000,
            'paid_at' => now(),
        ]);

        $tencentInstance = VpsInstance::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'organization_id' => $this->customer->current_organization_id,
            'hostname' => 'tc-vps-test',
            'public_ip' => '103.150.10.99',
            'status' => 'reinstalling',
            'provider' => 'tencent',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'billing_cycle' => 'monthly',
        ]);

        $manualInstance = VpsInstance::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'organization_id' => $this->customer->current_organization_id,
            'hostname' => 'mn-vps-test',
            'public_ip' => '103.150.10.100',
            'status' => 'reinstalling',
            'provider' => 'manual',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'billing_cycle' => 'monthly',
        ]);

        $this->assertSame('Sedang Diproses', $tencentInstance->customer_status['label']);
        $this->assertSame('Sedang Diproses Tim', $manualInstance->customer_status['label']);
    }

    public function test_vps_instance_app_url_attribute_uses_stack_registry(): void
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'organization_id' => $this->customer->current_organization_id,
            'vps_spec_id' => $this->spec->id,
            'control_panel' => 'coolify',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 100000,
            'paid_at' => now(),
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'organization_id' => $this->customer->current_organization_id,
            'hostname' => 'coolify-app-test',
            'public_ip' => '103.150.10.55',
            'control_panel' => 'coolify',
            'status' => 'running',
            'provider' => 'tencent',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'billing_cycle' => 'monthly',
        ]);

        // coolify appUrlTemplate is "http://{ip}:8000"
        $this->assertSame('http://103.150.10.55:8000', $instance->app_url);
    }

    public function test_dashboard_uses_region_code_quota_pools_and_balance_divisor(): void
    {
        \App\Models\UpstreamQuotaPool::where('region_code', 'ap-jakarta')
            ->where('bundle_type', 'STARTER_BUNDLE')
            ->update(['capacity' => 20, 'used' => 3, 'reserved' => 2]);
        \App\Models\UpstreamQuotaPool::where('region_code', 'ap-jakarta')
            ->where('bundle_type', 'GENERAL_BUNDLE')
            ->update(['capacity' => 20, 'used' => 1, 'reserved' => 0]);

        \Illuminate\Support\Facades\Cache::put('tencent:balance', 4550, 60); // 4550 cents = $45.50

        $response = $this->actingAs($this->admin)->get(route('admin.index'));
        $response->assertOk();
        $response->assertSee('$45.50');
        $response->assertSee('JKT: 6/40');
    }

    public function test_merged_sub_tabs_and_alias_redirects_work(): void
    {
        // Orders & Shopee merged tabs + alias redirect
        $this->actingAs($this->admin)->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('Input Order Shopee');
        $this->actingAs($this->admin)->get(route('admin.orders', ['tab' => 'shopee']))
            ->assertRedirect(route('admin.shopee'));

        // Billing & Biaya Upstream merged tabs + alias redirect
        $this->actingAs($this->admin)->get(route('admin.billing.index'))
            ->assertOk()
            ->assertSee('Biaya Upstream');
        $this->actingAs($this->admin)->get(route('admin.billing.index', ['tab' => 'supplier']))
            ->assertRedirect(route('admin.supplier.index'));

        // Helpdesk, Abuse & Broadcast merged tabs + alias redirects
        $this->actingAs($this->admin)->get(route('admin.tickets'))
            ->assertOk()
            ->assertSee('Kasus Pelanggaran')
            ->assertSee('Broadcast Email');
        $this->actingAs($this->admin)->get(route('admin.tickets', ['tab' => 'abuse']))
            ->assertRedirect(route('admin.abuse.index'));
        $this->actingAs($this->admin)->get(route('admin.tickets', ['tab' => 'broadcast']))
            ->assertRedirect(route('admin.broadcast.index'));

        // System Settings, WA Gateway, Templates, Maintenance & Audit Log merged tabs + alias redirects
        $this->actingAs($this->admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('WA Gateway Admin')
            ->assertSee('Template Surel')
            ->assertSee('Jadwal Maintenance')
            ->assertSee('Audit Log');
        $this->actingAs($this->admin)->get(route('admin.settings.index', ['tab' => 'whatsapp']))
            ->assertRedirect(route('admin.whatsapp.index'));
        $this->actingAs($this->admin)->get(route('admin.settings.index', ['tab' => 'templates']))
            ->assertRedirect(route('admin.templates.index'));
        $this->actingAs($this->admin)->get(route('admin.settings.index', ['tab' => 'audit']))
            ->assertRedirect(route('admin.audit.index'));
    }

    public function test_instances_show_renew_pending_region_code_and_terminate_modal(): void
    {
        $region = \App\Models\DatacenterRegion::firstOrCreate(
            ['code' => 'ap-jakarta'],
            ['name' => 'Jakarta', 'country' => 'ID', 'provider' => 'tencent', 'is_active' => true]
        );

        $instance = VpsInstance::create([
            'customer_id' => $this->customer->id,
            'organization_id' => $this->customer->current_organization_id,
            'hostname' => 'tc-renew-pending-test',
            'public_ip' => '103.150.10.77',
            'status' => 'running',
            'provider' => 'tencent',
            'provider_resource_id' => 'lhins-test999',
            'datacenter_region_id' => $region->id,
            'provider_meta' => ['renew_pending' => true],
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'billing_cycle' => 'monthly',
        ]);

        $this->assertTrue($instance->renew_pending);
        $this->assertSame('ap-jakarta', $instance->region_code);

        $response = $this->actingAs($this->admin)->get(route('admin.instances'));
        $response->assertOk();
        $response->assertSee('RENEW PENDING');
        $response->assertSee('ap-jakarta');
        $response->assertSee('Konfirmasi Terminate Permanen');
        $response->assertSee('Override DB Manual (Darurat)');
        $response->assertSee('day2-open-' . $instance->id);
    }

    public function test_day2_provider_exception_returns_clean_502_json_instead_of_500(): void
    {
        $region = \App\Models\DatacenterRegion::firstOrCreate(
            ['code' => 'ap-jakarta'],
            ['name' => 'Jakarta', 'country' => 'ID', 'provider' => 'tencent', 'is_active' => true]
        );

        $instance = VpsInstance::create([
            'customer_id' => $this->customer->id,
            'organization_id' => $this->customer->current_organization_id,
            'hostname' => 'tc-malformed-demo',
            'public_ip' => '103.150.10.88',
            'status' => 'running',
            'provider' => 'tencent',
            'provider_resource_id' => 'lhins-demo01',
            'datacenter_region_id' => $region->id,
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'billing_cycle' => 'monthly',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '*.tencentcloudapi.com*' => \Illuminate\Support\Facades\Http::response([
                'Response' => [
                    'Error' => [
                        'Code' => 'InvalidParameterValue.InstanceIdMalformed',
                        'Message' => 'The specified instance ID lhins-demo01 is malformed.',
                    ],
                    'RequestId' => 'req-demo-err',
                ],
            ], 200),
        ]);

        // Admin endpoint: clean 502 with diagnostic message
        $this->actingAs($this->admin)
            ->getJson(route('admin.instances.day2.firewall.index', $instance->id))
            ->assertStatus(502)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'InvalidParameterValue.InstanceIdMalformed');

        // Customer endpoint: clean 502 white-label (zero vendor/lhins leak)
        $custResp = $this->actingAs($this->customer)
            ->getJson(route('dashboard.vps.day2.firewall.index', $instance->id))
            ->assertStatus(502)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'upstream_unavailable');
        $this->assertStringNotContainsString('lhins', $custResp->getContent());
        $this->assertStringNotContainsString('lighthouse', strtolower($custResp->getContent()));
    }
}
