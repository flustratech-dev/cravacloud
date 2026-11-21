<?php

namespace Tests\Feature;

use App\Jobs\Provisioning\InstallStackJob;
use App\Jobs\Provisioning\ProvisionVpsJob;
use App\Jobs\Provisioning\RenewVpsJob;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\SupplierPurchase;
use App\Models\UpstreamQuotaPool;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\AdminAlertService;
use App\Services\Provisioning\StackInstallerService;
use App\Services\Provisioning\Tencent\BalanceGuard;
use App\Services\SettingsService;
use App\Services\SupplierCoverageService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Wave3AdminControlPlaneTest extends TestCase
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
            'full_name' => 'Admin W3',
            'username' => 'admw3' . uniqid(),
            'email' => 'admw3' . uniqid() . '@cravacloud.id',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);
    }

    private function customer(): User
    {
        return User::create([
            'full_name' => 'Klien W3',
            'username' => 'cliw3' . uniqid(),
            'email' => 'cliw3' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
    }

    public function test_stack_installer_result_captures_last_4000_chars_tail(): void
    {
        $head = str_repeat('H', 1500);
        $tail = 'TAIL_ERROR_MARKER_' . str_repeat('T', 3900);
        $fullLog = $head . $tail;

        Http::fake([
            'tat.*' => Http::response([
                'Response' => [
                    'InvocationTaskBasicInfoSet' => [[
                        'TaskStatus' => 'FAILED',
                        'TaskResult' => base64_encode($fullLog),
                    ]],
                    'RequestId' => 'req-tat-tail',
                ],
            ], 200),
        ]);

        $res = app(StackInstallerService::class)->result('inv-1', 'lhins-1', 'ap-jakarta');

        $this->assertSame('failed', $res['status']);
        $this->assertSame(4000, mb_strlen($res['output']));
        $this->assertStringContainsString('TAIL_ERROR_MARKER_', $res['output']);
        $this->assertStringNotContainsString(str_repeat('H', 100), $res['output']);
    }

    public function test_balance_guard_persists_last_and_checked_at_and_admin_refresh_works(): void
    {
        Http::fake([
            'billing.*' => Http::response([
                'Response' => ['Balance' => 4525, 'RequestId' => 'req-bal-1'],
            ], 200),
        ]);

        $usd = app(BalanceGuard::class)->balanceUsd(fresh: true);
        $this->assertSame(45.25, $usd);
        $this->assertSame(45.25, Cache::get('tencent:balance:last'));
        $this->assertNotNull(Cache::get('tencent:balance:checked_at'));

        $admin = $this->admin();
        $this->actingAs($admin)
            ->post(route('admin.monitoring.refresh-balance'))
            ->assertRedirect(route('admin.monitoring', ['tab' => 'upstream']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'upstream.balance_refreshed',
        ]);
    }

    public function test_provisioning_alert_stuck_command_detects_stuck_task_and_held_paid_order(): void
    {
        app(SettingsService::class)->set('provisioning_auto_enabled', true, 'boolean');
        $spec = VpsSpec::whereNotNull('upstream_bundle_id')->first();
        if (! $spec) {
            $spec = VpsSpec::first();
            $spec->update([
                'upstream_bundle_id' => 'bundle_starter_nmc_lin_med2_01',
                'upstream_bundle_type' => 'STARTER_BUNDLE',
            ]);
        }
        $customer = $this->customer();

        $orderStuck = Order::create([
            'customer_id' => $customer->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'order_type' => 'new',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
            'paid_at' => now()->subMinutes(20),
        ]);

        $task = ProvisioningTask::create([
            'order_id' => $orderStuck->id,
            'kind' => 'provision',
            'status' => 'running',
            'provider' => 'tencent',
        ]);
        ProvisioningTask::whereKey($task->id)->update(['updated_at' => now()->subMinutes(25)]);

        $alerts = $this->mock(AdminAlertService::class);
        $alerts->shouldReceive('alert')
            ->once()
            ->withArgs(fn (string $key, array $ctx) => $key === 'provisioning.stuck'
                && str_contains($ctx['body'] ?? '', "Task #{$task->id}"))
            ->andReturn(['email' => true, 'whatsapp' => true, 'skipped_dedupe' => false]);

        $this->artisan('provisioning:alert-stuck')->assertSuccessful();
    }

    public function test_supplier_coverage_excludes_tencent_instances(): void
    {
        $customer = $this->customer();
        VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'cv-tencent-excl',
            'status' => 'running',
            'provider' => 'tencent',
            'expires_at' => now()->addDays(2),
        ]);
        $manual = VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'cv-manual-incl',
            'status' => 'running',
            'provider' => 'manual',
            'expires_at' => now()->addDays(2),
        ]);

        $rows = app(SupplierCoverageService::class)->rows();
        $ids = $rows->map(fn (array $r) => $r['instance']->id)->all();

        $this->assertContains($manual->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_admin_can_retry_cancel_tasks_retry_stack_retry_renew_and_update_provisioning_settings(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $spec = VpsSpec::first();
        $spec->update([
            'upstream_bundle_id' => 'bundle_starter_nmc_lin_med2_01',
            'upstream_bundle_type' => 'STARTER_BUNDLE',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'failed',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'hostname' => 'srv-w3-control',
            'status' => 'running',
            'provider' => 'tencent',
            'provider_resource_id' => 'lhins-w3-ctrl',
            'control_panel' => 'coolify',
            'provider_meta' => ['renew_pending' => true],
        ]);

        $failedTask = ProvisioningTask::create([
            'order_id' => $order->id,
            'vps_instance_id' => $instance->id,
            'kind' => 'provision',
            'status' => 'failed',
            'provider' => 'tencent',
            'error' => 'Temporary glitch',
        ]);

        $runningTask = ProvisioningTask::create([
            'order_id' => $order->id,
            'vps_instance_id' => $instance->id,
            'kind' => 'install_stack',
            'status' => 'running',
            'provider' => 'tencent',
        ]);

        // 1. Retry task
        $this->actingAs($admin)->post(route('admin.tasks.retry', $failedTask->id))
            ->assertRedirect(route('admin.monitoring', ['tab' => 'tasks']));
        $this->assertSame('pending', $failedTask->fresh()->status);
        $this->assertSame('provisioning', $order->fresh()->status);
        Queue::assertPushed(ProvisionVpsJob::class);

        // 2. Cancel stuck task
        $this->actingAs($admin)->post(route('admin.tasks.cancel', $runningTask->id), ['reason' => 'Timeout TAT'])
            ->assertRedirect(route('admin.monitoring', ['tab' => 'tasks']));
        $this->assertSame('cancelled', $runningTask->fresh()->status);
        $this->assertStringContainsString('Timeout TAT', $runningTask->fresh()->error);

        // 3. Retry stack only
        $this->actingAs($admin)->post(route('admin.instances.retry-stack', $instance->id))
            ->assertSessionHas('success');
        Queue::assertPushed(InstallStackJob::class);

        // 4. Retry renew upstream
        $this->actingAs($admin)->post(route('admin.instances.retry-renew', $instance->id))
            ->assertSessionHas('success');
        Queue::assertPushed(RenewVpsJob::class);

        // 5. Provisioning settings & quota pool update
        $pool = UpstreamQuotaPool::firstOrCreate(
            ['region_code' => 'ap-jakarta', 'bundle_type' => 'STARTER_BUNDLE'],
            ['capacity' => 20, 'reserved' => 0, 'used' => 0]
        );

        $this->actingAs($admin)->post(route('admin.settings.provisioning'), [
            'provisioning_auto_enabled' => '1',
            'quota_guard_mode' => 'enforce',
            'tencent_usd_idr' => 16900,
            'whitelist_specs' => [$spec->id],
        ])->assertRedirect(route('admin.settings.index', ['tab' => 'provisioning']));

        $settings = app(SettingsService::class);
        $this->assertTrue($settings->bool('provisioning_auto_enabled', false));
        $this->assertSame('enforce', $settings->get('quota_guard_mode'));
        $this->assertSame(16900, $settings->int('tencent_usd_idr'));

        $this->actingAs($admin)->put(route('admin.settings.quota-pools.update', $pool->id), [
            'capacity' => 45,
        ])->assertRedirect(route('admin.settings.index', ['tab' => 'provisioning']));
        $this->assertSame(45, $pool->fresh()->capacity);

        // 6. All monitoring tabs render HTTP 200
        $this->actingAs($admin)->get(route('admin.monitoring', ['tab' => 'overview']))->assertOk();
        $this->actingAs($admin)->get(route('admin.monitoring', ['tab' => 'tasks']))->assertOk();
        $this->actingAs($admin)->get(route('admin.monitoring', ['tab' => 'upstream']))->assertOk();
        $this->actingAs($admin)->get(route('admin.monitoring', ['tab' => 'failed-jobs']))->assertOk();

        $this->assertGreaterThanOrEqual(5, AdminAuditLog::count());
    }
}
