<?php

namespace Tests\Feature;

use App\Jobs\Provisioning\DestroyVpsJob;
use App\Jobs\Provisioning\ProvisionVpsJob;
use App\Jobs\Provisioning\ReinstallVpsJob;
use App\Jobs\Provisioning\StopVpsJob;
use App\Models\AbuseCase;
use App\Models\AdminAuditLog;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\MidtransService;
use App\Services\Provisioning\Stacks\StackRegistry;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class Wave4OperationalUpgradesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('is_admin', true)->first() ?? User::create([
            'full_name' => 'Admin W4',
            'username' => 'admw4' . uniqid(),
            'email' => 'admw4' . uniqid() . '@cravacloud.id',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);
    }

    private function customer(float $credit = 0): User
    {
        $user = User::create([
            'full_name' => 'Klien W4',
            'username' => 'cliw4' . uniqid(),
            'email' => 'cliw4' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);

        if ($credit > 0) {
            \App\Models\CreditTransaction::create([
                'customer_id' => $user->id,
                'type' => 'credit',
                'amount' => $credit,
                'balance_after' => $credit,
                'reason' => 'Saldo uji Wave 4',
            ]);
        }

        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return $user->fresh();
    }

    private function enableTencentMapping(VpsSpec $spec): void
    {
        $spec->update([
            'upstream_bundle_id' => 'bundle_starter_nmc_lin_med2_01',
            'upstream_bundle_type' => 'STARTER_BUNDLE',
        ]);

        $settings = app(SettingsService::class);
        $settings->set('provisioning_auto_enabled', true, 'boolean', ['group' => 'provisioning']);
        $settings->set('provisioning_auto_spec_whitelist', [$spec->id], 'json', ['group' => 'provisioning']);
    }

    public function test_checkout_coupon_preview_monthly_lock_and_rp0_auto_settle(): void
    {
        $spec = VpsSpec::where('is_active', true)->where('category', 'vps')->orderBy('sell_price')->firstOrFail();
        $customer = $this->customer(credit: (float) $spec->sell_price + 50000);

        Coupon::create([
            'code' => 'HEMAT50',
            'name' => 'Diskon 50 Persen',
            'type' => 'percent',
            'value' => 50,
            'is_active' => true,
        ]);

        $preview = $this->actingAs($customer)->postJson(route('checkout.apply-coupon'), [
            'spec_id' => $spec->id,
            'coupon_code' => 'HEMAT50',
            'apply_credit' => true,
        ]);
        $preview->assertOk()->assertJson(['ok' => true]);
        $this->assertTrue((float) $preview->json('breakdown.discount') > 0);

        $badCycle = $this->actingAs($customer)->post(route('order.store'), [
            'vps_spec_id' => $spec->id,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'bad-cycle-01',
            'billing_cycle' => 'annual',
            'payment_method' => 'midtrans_snap',
            'terms_accepted' => '1',
        ]);
        $badCycle->assertSessionHasErrors('billing_cycle');

        $ok = $this->actingAs($customer)->post(route('order.store'), [
            'vps_spec_id' => $spec->id,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'rp0-node-01',
            'billing_cycle' => 'monthly',
            'coupon_code' => 'HEMAT50',
            'apply_credit' => '1',
            'payment_method' => 'midtrans_snap',
            'terms_accepted' => '1',
        ]);

        $order = Order::where('hostname', 'rp0-node-01')->firstOrFail();
        $this->assertSame('paid', $order->status);
        $this->assertEquals(0.0, (float) $order->amount);
        $ok->assertRedirect(route('order.success', ['id' => $order->id]));
    }

    public function test_admin_retry_provision_dispatches_auto_orchestrator_and_sync_midtrans(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $spec = VpsSpec::where('is_active', true)->where('category', 'vps')->orderBy('sell_price')->firstOrFail();
        $this->enableTencentMapping($spec);

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'retry-auto-01',
            'amount' => $spec->sell_price,
            'status' => 'failed',
            'channel' => 'website',
            'failure_reason' => 'API timeout sementara',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.orders.retry-provision', $order->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('provisioning', $order->fresh()->status);
        Queue::assertPushed(ProvisionVpsJob::class);

        $pendingOrder = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'sync-mid-01',
            'amount' => $spec->sell_price,
            'status' => 'pending',
            'channel' => 'website',
        ]);

        config(['midtrans.server_key' => 'SB-Mid-server-test', 'services.midtrans.server_key' => 'SB-Mid-server-test']);
        Http::fake([
            '*midtrans*' => Http::response([
                'transaction_status' => 'pending',
                'status_code' => '201',
                'order_id' => 'CRV-' . $pendingOrder->id,
            ], 200),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.orders.sync-midtrans', $pendingOrder->id))
            ->assertRedirect()
            ->assertSessionHas('info');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'order.midtrans_synced',
            'subject_id' => $pendingOrder->id,
        ]);
    }

    public function test_shopee_dual_mode_auto_vs_manual_and_ubuntu_default_ssh_user(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $spec = VpsSpec::where('is_active', true)->where('category', 'vps')->orderBy('sell_price')->firstOrFail();
        $this->enableTencentMapping($spec);

        $this->actingAs($admin)->post(route('admin.shopee.process'), [
            'shopee_order_id' => 'SHP-AUTO-001',
            'customer_name' => 'Pembeli Auto',
            'customer_email' => 'pembeli-auto@test.id',
            'vps_spec_id' => $spec->id,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'shp-auto-01',
            'shopee_mode' => 'auto',
        ])->assertRedirect()->assertSessionHas('success');

        $autoOrder = Order::where('shopee_order_id', 'SHP-AUTO-001')->firstOrFail();
        $this->assertSame('auto', $autoOrder->metadata['shopee_mode'] ?? null);
        $this->assertNotNull($autoOrder->vpsInstance);
        $this->assertSame('tencent', $autoOrder->vpsInstance->provider);
        $this->assertSame('provisioning', $autoOrder->vpsInstance->status);
        $this->assertNull($autoOrder->vpsInstance->public_ip);
        Queue::assertPushed(ProvisionVpsJob::class);

        $this->actingAs($admin)->post(route('admin.shopee.process'), [
            'shopee_order_id' => 'SHP-MAN-001',
            'customer_name' => 'Pembeli Manual',
            'customer_email' => 'pembeli-manual@test.id',
            'vps_spec_id' => $spec->id,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'hostname' => 'shp-manual-01',
            'shopee_mode' => 'manual',
            'auto_provision' => '1',
            'public_ip' => '103.55.66.77',
            'root_password' => 'StrongPass#1234',
        ])->assertRedirect()->assertSessionHas('success');

        $manualOrder = Order::where('shopee_order_id', 'SHP-MAN-001')->firstOrFail();
        $this->assertTrue((bool) ($manualOrder->metadata['manual_fulfillment'] ?? false));
        $this->assertSame('ubuntu', $manualOrder->vpsInstance->ssh_username);
    }

    public function test_admin_sync_upstream_and_day2_reinstall_override(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $customer = $this->customer();

        $vps = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'hostname' => 'adm-reinstall-01',
            'public_ip' => '103.10.10.1',
            'status' => 'running',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'provider' => 'tencent',
            'provider_resource_id' => 'lhins-w4-sync',
            'region_code' => 'ap-jakarta',
            'ssh_username' => 'ubuntu',
        ]);

        Http::fake([
            'lighthouse.*' => Http::response([
                'Response' => [
                    'InstanceSet' => [[
                        'InstanceId' => 'lhins-w4-sync',
                        'InstanceState' => 'RUNNING',
                        'PublicAddresses' => ['103.10.10.99'],
                        'PrivateAddresses' => ['10.0.0.99'],
                        'ExpiredTime' => now()->addDays(25)->toIso8601String(),
                    ]],
                    'TotalCount' => 1,
                    'RequestId' => 'req-sync-w4',
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.instances.sync-upstream', $vps->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('103.10.10.99', $vps->fresh()->public_ip);
        $this->assertNotNull($vps->fresh()->last_reconciled_at);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'vps.sync_upstream',
            'subject_id' => $vps->id,
        ]);

        $badConfirm = $this->actingAs($admin)->postJson(route('admin.instances.day2.reinstall', $vps->id), [
            'os' => 'ubuntu2404',
            'control_panel' => 'docker',
            'confirm_hostname' => 'wrong-name',
        ]);
        $badConfirm->assertStatus(422);

        $goodConfirm = $this->actingAs($admin)->postJson(route('admin.instances.day2.reinstall', $vps->id), [
            'os' => 'ubuntu2404',
            'control_panel' => 'docker',
            'confirm_hostname' => 'adm-reinstall-01',
        ]);
        $goodConfirm->assertOk()->assertJson(['ok' => true, 'status' => 'reinstalling']);

        $this->assertSame('reinstalling', $vps->fresh()->status);
        $this->assertDatabaseHas('provisioning_tasks', [
            'vps_instance_id' => $vps->id,
            'kind' => 'reinstall',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'vps.reinstall',
            'subject_id' => $vps->id,
        ]);
        Queue::assertPushed(ReinstallVpsJob::class);
    }

    public function test_packages_hpp_bundle_mapping_and_stack_kill_switch(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $spec = VpsSpec::where('is_active', true)->where('category', 'vps')->orderBy('sell_price')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.packages.update', $spec->id), [
            'name' => $spec->name,
            'category' => 'vps',
            'cpu' => $spec->cpu,
            'ram' => max(2, $spec->ram),
            'disk' => $spec->disk,
            'bandwidth' => $spec->bandwidth,
            'cost_price' => $spec->cost_price,
            'sell_price' => $spec->sell_price,
            'is_active' => '1',
            'is_renewable' => '1',
            'upstream_bundle_id' => 'bundle_starter_nmc_lin_med2_01',
            'upstream_bundle_type' => 'STARTER_BUNDLE',
            'canary_whitelist_present' => '1',
            'canary_whitelisted' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $spec->refresh();
        $this->assertSame('bundle_starter_nmc_lin_med2_01', $spec->upstream_bundle_id);
        $this->assertSame('STARTER_BUNDLE', $spec->upstream_bundle_type);
        $whitelist = app(SettingsService::class)->get('provisioning_auto_spec_whitelist', []);
        $this->assertContains($spec->id, array_map('intval', (array) $whitelist));

        // Kill-switch disable docker stack
        $this->actingAs($admin)->post(route('admin.packages.stacks.toggle'), [
            'stack_key' => 'docker',
        ])->assertRedirect();

        $registry = app(StackRegistry::class);
        $this->assertNotContains('docker', $registry->checkoutKeys());

        $blockedCheckout = $this->actingAs($customer)->post(route('order.store'), [
            'vps_spec_id' => $spec->id,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'docker',
            'hostname' => 'blocked-stack-01',
            'billing_cycle' => 'monthly',
            'payment_method' => 'midtrans_snap',
            'terms_accepted' => '1',
        ]);
        $blockedCheckout->assertSessionHasErrors('control_panel');

        // Re-enable docker stack
        $this->actingAs($admin)->post(route('admin.packages.stacks.toggle'), [
            'stack_key' => 'docker',
        ])->assertRedirect();
        $this->assertContains('docker', app(StackRegistry::class)->checkoutKeys());
    }

    public function test_abuse_upstream_hooks_and_admin_api_audit(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $spec = VpsSpec::where('is_active', true)->orderBy('sell_price')->firstOrFail();

        $vps = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'hostname' => 'abuse-vps-01',
            'public_ip' => '103.22.33.44',
            'status' => 'running',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'os' => 'ubuntu2404',
            'control_panel' => 'none',
            'provider' => 'tencent',
            'provider_resource_id' => 'lhins-abuse-1',
            'region_code' => 'ap-jakarta',
            'ssh_username' => 'ubuntu',
        ]);

        $case = AbuseCase::create([
            'user_id' => $customer->id,
            'vps_instance_id' => $vps->id,
            'type' => 'ddos',
            'severity' => 'high',
            'status' => 'open',
            'evidence' => 'Outbound UDP flood',
        ]);

        $this->actingAs($admin)->post(route('admin.abuse.notify', $case->id), [
            'deadline_days' => 2,
            'suspend_upstream' => '1',
        ])->assertRedirect();

        Queue::assertPushed(StopVpsJob::class);

        $this->actingAs($admin)->post(route('admin.abuse.resolve', $case->id), [
            'status' => 'terminated',
            'resolution' => 'No response after deadline',
            'terminate_upstream' => '1',
        ])->assertRedirect();

        Queue::assertPushed(DestroyVpsJob::class);

        $apiRes = $this->actingAs($admin)->postJson(route('api.admin.shopee.process'), [
            'shopee_order_id' => 'API-SHP-W4-01',
            'customer_name' => 'API Buyer',
            'customer_email' => 'apibuyer@test.id',
            'package' => $spec->name,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
        ]);
        $apiRes->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(
            AdminAuditLog::where('action', 'api.shopee.process')->exists()
        );
    }
}
