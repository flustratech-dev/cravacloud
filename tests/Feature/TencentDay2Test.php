<?php

namespace Tests\Feature;

use App\Jobs\Provisioning\DestroyVpsJob;
use App\Jobs\Provisioning\InstallStackJob;
use App\Jobs\Provisioning\ProvisionVpsJob;
use App\Jobs\Provisioning\RebootVpsJob;
use App\Jobs\Provisioning\ReinstallVpsJob;
use App\Jobs\Provisioning\ResetPasswordJob;
use App\Jobs\Provisioning\StartVpsJob;
use App\Jobs\Provisioning\StopVpsJob;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\Subscription;
use App\Models\SupplierPurchase;
use App\Models\SupportTicket;
use App\Models\UpstreamQuotaPool;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\ServiceTerminatedNotification;
use App\Notifications\VpsPasswordResetNotification;
use App\Services\OrderStateMachine;
use App\Services\Provisioning\ProviderManager;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 5 — Day-2 self-service, UI gating, Patch Audit Phase 4, and
 * automated reinstall via TAT (STRICT Rp0 — Http::fake ONLY).
 */
class TencentDay2Test extends TestCase
{
    use RefreshDatabase;

    private const UPSTREAM_ID = 'lhins-day201';
    private const PUBLIC_IP = '43.157.88.10';

    private int $seq = 0;

    /** @var array<string, mixed>|null */
    private ?array $tencentHandlers = null;

    /** @var array<string, int> */
    private array $actionCounts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        Queue::fake();

        config([
            'services.tencent.secret_id' => 'test-id',
            'services.tencent.secret_key' => 'test-key',
            'services.tencent.poll_interval_seconds' => 0,
            'services.tencent.poll_max_seconds' => 3,
            'whatsapp.enabled' => true,
            'whatsapp.key' => 'test-key',
            'whatsapp.url' => 'https://wa.test',
        ]);

        app(SettingsService::class)->setMany([
            'mail_admin_addresses' => 'ops@cravacloud.test',
            'admin_notification_email' => 'ops@cravacloud.test',
            'admin_notification_whatsapp' => '0811-1111-1111',
            'provisioning_auto_enabled' => true,
            'provisioning_auto_spec_whitelist' => [$this->spec('Starter XS')->id],
            'tencent_usd_idr' => 16500,
        ]);
        \App\Support\MailIdentity::flushCache();

        Http::fake(function (HttpRequest $request) {
            $action = $request->header('X-TC-Action')[0] ?? null;

            if ($action === null || $this->tencentHandlers === null) {
                return Http::response(['success' => true], 200);
            }

            $this->actionCounts[$action] = ($this->actionCounts[$action] ?? 0) + 1;

            if (! array_key_exists($action, $this->tencentHandlers)) {
                throw new \RuntimeException("Unexpected Tencent action in test: {$action}");
            }

            $body = json_decode($request->body(), true) ?: [];
            $handler = $this->tencentHandlers[$action];
            $response = is_callable($handler) ? $handler($body, $request) : $handler;

            return Http::response(['Response' => $response + ['RequestId' => 'req-day2']]);
        });
    }

    // ============================== helpers ==============================

    private function spec(string $name): VpsSpec
    {
        return VpsSpec::where('category', 'vps')->where('name', $name)->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    private function customer(string $username = 'day2'): User
    {
        $user = User::create([
            'username' => $username,
            'email' => $username.'@example.com',
            'full_name' => 'Pelanggan '.ucfirst($username),
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return $user->fresh();
    }

    private function fakeTencent(array $handlers): void
    {
        $this->tencentHandlers = $handlers;
        $this->actionCounts = [];
    }

    private function remote(array $overrides = []): array
    {
        return array_merge([
            'InstanceId' => self::UPSTREAM_ID,
            'InstanceName' => 'crava-day2',
            'InstanceState' => 'RUNNING',
            'LatestOperation' => 'ResetInstancesPassword',
            'LatestOperationState' => 'SUCCESS',
            'PublicAddresses' => [self::PUBLIC_IP],
            'PrivateAddresses' => ['10.0.0.2'],
            'BundleId' => 'bundle_starter_nmc_lin_med2_01',
            'ExpiredTime' => now()->addMonth()->format('Y-m-d\TH:i:s\Z'),
        ], $overrides);
    }

    /** Provision one running tencent VPS via ProvisionVpsJob (without stack install). */
    private function provisionedTencent(string $stack = 'none', string $os = 'ubuntu2404'): array
    {
        $customer = $this->customer('d2'.(++$this->seq));
        $spec = $this->spec('Starter XS');
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => $stack,
            'hostname' => 'srv-'.$customer->username,
            'datacenter_location' => 'indonesia',
            'os' => $os,
            'billing_cycle' => 'monthly',
            'order_type' => 'new',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $spec->sell_price,
            'currency' => 'IDR',
        ]);

        $this->fakeTencent([
            'DescribeAccountBalance' => ['Balance' => 100000],
            'DescribeInstances' => function (array $body) {
                if (isset($body['Filters'])) {
                    return ['TotalCount' => 0, 'InstanceSet' => []];
                }

                return ['TotalCount' => 1, 'InstanceSet' => [$this->remote()]];
            },
            'CreateInstances' => ['InstanceIdSet' => [self::UPSTREAM_ID]],
            'ResetInstancesPassword' => [],
            'DescribeAutomationAgentStatus' => ['AutomationAgentStatusSet' => [['AgentStatus' => 'Online']]],
            'DescribeFirewallRules' => ['FirewallRuleSet' => [
                ['Protocol' => 'TCP', 'Port' => '22', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
            ]],
            'CreateFirewallRules' => [],
            'RunCommand' => ['InvocationId' => 'invt-init01'],
            'DescribeInvocationTasks' => ['InvocationTaskBasicInfoSet' => [[
                'TaskStatus' => 'SUCCESS',
                'TaskResult' => base64_encode('CRAVA_INSTALL_OK '.$stack),
            ]]],
        ]);

        app(OrderStateMachine::class)->transition($order, 'paid', [
            'reason' => 'Paid test.',
            'actor_type' => 'system',
            'onLocked' => function (Order $locked) {
                $locked->paid_at = now();
                $locked->save();
            },
        ]);

        $task = ProvisioningTask::where('order_id', $order->id)->where('kind', 'provision')->firstOrFail();
        (new ProvisionVpsJob($task->id))->handle(app(ProviderManager::class));

        if ($stack !== 'none') {
            $installTask = ProvisioningTask::where('order_id', $order->id)->where('kind', 'install_stack')->firstOrFail();
            (new InstallStackJob($installTask->id))->handle(app(ProviderManager::class));
        }

        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $instance->update(['expires_at' => now()->addDays(25)->endOfDay()]);

        return [$order->fresh(), $instance->fresh(), $customer];
    }

    private function manualVps(): array
    {
        $customer = $this->customer('man'.(++$this->seq));
        $spec = $this->spec('Starter XS');
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'hostname' => 'srv-'.$customer->username,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $spec->sell_price,
            'paid_at' => now(),
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => 'srv-'.$customer->username,
            'public_ip' => '103.10.10.10',
            'os' => 'ubuntu2404',
            'status' => 'running',
            'cpu' => 2, 'ram' => 2, 'disk' => 40,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'initial_root_password' => 'ManualSecret#123',
            'expires_at' => now()->addDays(20),
        ]);

        return [$order, $instance, $customer];
    }

    // ============ 1. Patch Audit Phase 4: Terminate & Subscription ============

    public function test_terminate_cron_keeps_subscription_suspended_until_destroy_succeeds(): void
    {
        [$order, $instance, $customer] = $this->provisionedTencent();

        $sub = Subscription::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $order->vps_spec_id,
            'order_id' => $order->id,
            'vps_instance_id' => $instance->id,
            'status' => 'suspended',
            'billing_cycle' => 'monthly',
            'unit_amount' => 115000,
            'currency' => 'IDR',
        ]);
        // Force updated_at past 30-day customer deadline without touching Eloquent auto-timestamp.
        Subscription::where('id', $sub->id)->update(['updated_at' => now()->subDays(35)]);

        $instance->update(['status' => 'suspended']);

        $this->artisan('billing:terminate-expired')->assertSuccessful();

        // Subscription MUST stay suspended until DestroyVpsJob succeeds upstream!
        $this->assertSame('suspended', $sub->fresh()->status);
        $this->assertNull($sub->fresh()->ended_at);
        Notification::assertNotSentTo($customer, ServiceTerminatedNotification::class);
        Queue::assertPushed(DestroyVpsJob::class, 1);

        $destroyTask = ProvisioningTask::where('vps_instance_id', $instance->id)
            ->where('kind', 'destroy')
            ->firstOrFail();

        $this->fakeTencent([
            'DescribeInstances' => fn () => ['TotalCount' => 1, 'InstanceSet' => [$this->remote(['InstanceState' => 'SHUTDOWN'])]],
            'TerminateInstances' => [],
        ]);

        (new DestroyVpsJob($destroyTask->id))->handle(app(ProviderManager::class));

        $this->assertSame('terminated', $sub->fresh()->status);
        $this->assertNotNull($sub->fresh()->ended_at);
        $this->assertSame('terminated', $instance->fresh()->status);
        Notification::assertSentTo($customer, ServiceTerminatedNotification::class);
    }

    public function test_terminate_failure_leaves_subscription_suspended_for_retry(): void
    {
        [$order, $instance, $customer] = $this->provisionedTencent();

        $sub = Subscription::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $order->vps_spec_id,
            'order_id' => $order->id,
            'vps_instance_id' => $instance->id,
            'status' => 'suspended',
            'billing_cycle' => 'monthly',
            'unit_amount' => 115000,
            'currency' => 'IDR',
        ]);
        Subscription::where('id', $sub->id)->update(['updated_at' => now()->subDays(35)]);
        $instance->update(['status' => 'suspended']);

        $this->artisan('billing:terminate-expired')->assertSuccessful();

        $destroyTask = ProvisioningTask::where('vps_instance_id', $instance->id)
            ->where('kind', 'destroy')
            ->firstOrFail();

        $this->fakeTencent([
            'DescribeInstances' => ['Error' => ['Code' => 'OperationDenied.InvalidState', 'Message' => 'Locked']],
        ]);

        try {
            (new DestroyVpsJob($destroyTask->id))->handle(app(ProviderManager::class));
        } catch (\Throwable) {
            // non-retryable fail()
        }

        $this->assertSame('failed', $destroyTask->fresh()->status);
        $this->assertSame('suspended', $sub->fresh()->status);
        $this->assertSame('suspended', $instance->fresh()->status);
        Notification::assertNotSentTo($customer, ServiceTerminatedNotification::class);
    }

    // ============ 2. Patch Audit Phase 4: Power Polling & D2 OS Catalog ============

    public function test_power_jobs_poll_until_target_state_and_operation_success(): void
    {
        [, $instance] = $this->provisionedTencent();
        $instance->update(['status' => 'stopped']);

        $startTask = ProvisioningTask::create([
            'order_id' => $instance->order_id,
            'vps_instance_id' => $instance->id,
            'kind' => 'start',
            'status' => 'pending',
            'provider' => 'tencent',
        ]);

        $polls = 0;
        $this->fakeTencent([
            'StartInstances' => [],
            'DescribeInstances' => function () use (&$polls) {
                $polls++;
                $ready = $polls >= 2;

                return ['TotalCount' => 1, 'InstanceSet' => [$this->remote([
                    'InstanceState' => $ready ? 'RUNNING' : 'STARTING',
                    'LatestOperation' => 'StartInstances',
                    'LatestOperationState' => $ready ? 'SUCCESS' : 'OPERATING',
                ])]];
            },
        ]);

        (new StartVpsJob($startTask->id))->handle(app(ProviderManager::class));

        $this->assertSame(2, $polls);
        $this->assertSame('running', $instance->fresh()->status);
        $this->assertSame('succeeded', $startTask->fresh()->status);
    }

    public function test_checkout_rejects_unverified_os_and_reinstall_restricts_tencent_os(): void
    {
        $customer = $this->customer('d2os');
        $spec = $this->spec('Starter XS');

        // Unverified Windows OS rejected at checkout.
        $this->actingAs($customer)
            ->post(route('order.store'), [
                'vps_spec_id' => $spec->id,
                'hostname' => 'srv-win-reject',
                'datacenter_location' => 'indonesia',
                'os' => 'windows2022',
                'control_panel' => 'none',
                'payment_method' => 'qris',
                'terms_accepted' => '1',
            ])
            ->assertSessionHasErrors('os');

        // Incompatible OS + stack rejected cleanly at checkout (prevents silent manual hold).
        $this->actingAs($customer)
            ->post(route('order.store'), [
                'vps_spec_id' => $spec->id,
                'hostname' => 'srv-combo-reject',
                'datacenter_location' => 'indonesia',
                'os' => 'rocky94',
                'control_panel' => 'coolify',
                'payment_method' => 'qris',
                'terms_accepted' => '1',
            ])
            ->assertSessionHasErrors('os');

        // Historical label lookup still preserves legacy OS + matches live Tencent OS versions.
        $labels = Order::osLabels();
        $this->assertArrayHasKey('windows2022', $labels);
        $this->assertSame('Ubuntu Server 26.04 LTS 64bit', $labels['ubuntu2604']);
        $this->assertSame('Debian 13.2 Trixie 64bit', $labels['debian13']);
        $this->assertSame('Rocky Linux 10.2 64bit', $labels['rocky10']);
        $this->assertSame('CentOS Stream 9 64bit', $labels['centos_stream9']);

        // Checkout catalog keeps verified non-EOL Linux only & drops EOL CentOS 7 / Debian 10 / Windows.
        $checkoutOs = Order::checkoutOperatingSystems();
        $this->assertArrayNotHasKey('centos76', $checkoutOs);
        $this->assertArrayNotHasKey('debian10', $checkoutOs);
        $this->assertArrayNotHasKey('windows2022', $checkoutOs);
        foreach (['ubuntu2404', 'ubuntu2204', 'ubuntu2604', 'ubuntu2004', 'debian13', 'debian12', 'debian11', 'rocky10', 'rocky94', 'centos_stream9'] as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $checkoutOs);
        }

        // Tencent instance reinstall OS options exclude Windows/CentOS7, manual preserves them.
        [, $tencentVps] = $this->provisionedTencent();
        [, $manualVps] = $this->manualVps();

        $this->assertArrayNotHasKey('windows2022', $tencentVps->reinstallOsOptions());
        $this->assertArrayHasKey('ubuntu2404', $tencentVps->reinstallOsOptions());
        $this->assertArrayHasKey('rocky10', $tencentVps->reinstallOsOptions());
        $this->assertArrayHasKey('centos_stream9', $tencentVps->reinstallOsOptions());
        $this->assertArrayHasKey('windows2022', $manualVps->reinstallOsOptions());
    }

    // ============ 3. Customer Day-2: Power, Console, Reset Password ============

    public function test_customer_power_actions_validation_busy_and_rate_limit(): void
    {
        [, $instance, $customer] = $this->provisionedTencent();

        // Start while already running → 422 invalid_state.
        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.day2.power', $instance->id), ['action' => 'start'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_state');

        // Reboot dispatches RebootVpsJob & transitions VPS to rebooting.
        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.day2.power', $instance->id), ['action' => 'reboot'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        Queue::assertPushed(RebootVpsJob::class, 1);
        $this->assertSame('rebooting', $instance->fresh()->status);

        // Second reboot while task is live → 422 (instance is rebooting/busy).
        $instance->refresh()->update(['status' => 'running']);
        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.day2.power', $instance->id), ['action' => 'reboot'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'busy');

        // Rate limit vps-power (10/min) → 11th request hits 429.
        $hit429 = false;
        for ($i = 0; $i < 12; $i++) {
            $res = $this->actingAs($customer)
                ->postJson(route('dashboard.vps.day2.power', $instance->id), ['action' => 'start']);
            if ($res->status() === 429) {
                $hit429 = true;
                break;
            }
        }
        $this->assertTrue($hit429, 'Expected vps-power rate limiter to trigger 429.');
    }

    public function test_vnc_console_is_on_demand_uncached_and_requires_running(): void
    {
        [, $instance, $customer] = $this->provisionedTencent();

        $call = 0;
        $this->fakeTencent([
            'DescribeInstanceVncUrl' => function () use (&$call) {
                $call++;

                return ['InstanceVncUrl' => 'https://vnc.example.test/session-'.$call];
            },
        ]);

        // Two requests must produce TWO distinct upstream API calls (never cached).
        $r1 = $this->actingAs($customer)
            ->getJson(route('dashboard.vps.day2.console', $instance->id))
            ->assertOk()
            ->assertJsonPath('expires_in', 15);
        $r2 = $this->actingAs($customer)
            ->getJson(route('dashboard.vps.day2.console', $instance->id))
            ->assertOk();

        $this->assertSame(2, $call);
        $this->assertNotSame($r1->json('url'), $r2->json('url'));

        // Stopped instance is rejected before hitting upstream.
        $instance->update(['status' => 'stopped']);
        $this->actingAs($customer)
            ->getJson(route('dashboard.vps.day2.console', $instance->id))
            ->assertStatus(422)
            ->assertJsonPath('code', 'not_running');
        $this->assertSame(2, $call);
    }

    public function test_reset_password_stores_encrypted_and_never_leaks_to_task_output(): void
    {
        [, $instance, $customer] = $this->provisionedTencent();
        $oldPassword = $instance->initial_root_password;

        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.day2.reset-password', $instance->id))
            ->assertOk();

        Queue::assertPushed(ResetPasswordJob::class, 1);
        $task = ProvisioningTask::where('vps_instance_id', $instance->id)
            ->where('kind', 'reset_password')
            ->firstOrFail();

        $sentPassword = null;
        $this->fakeTencent([
            'ResetInstancesPassword' => function (array $body) use (&$sentPassword) {
                $sentPassword = $body['Password'] ?? null;

                return [];
            },
            'DescribeInstances' => ['TotalCount' => 1, 'InstanceSet' => [$this->remote([
                'LatestOperation' => 'ResetInstancesPassword',
                'LatestOperationState' => 'SUCCESS',
                'InstanceState' => 'RUNNING',
            ])]],
        ]);

        (new ResetPasswordJob($task->id))->handle(app(ProviderManager::class));

        $instance->refresh();
        $task->refresh();
        $this->assertNotNull($sentPassword);
        $this->assertNotSame($oldPassword, $instance->initial_root_password);
        $this->assertSame($sentPassword, $instance->initial_root_password);
        $this->assertSame('succeeded', $task->status);
        $this->assertStringNotContainsString($sentPassword, json_encode($task->output));
        $this->assertStringNotContainsString($sentPassword, json_encode($task->input));
        Notification::assertSentTo($customer, VpsPasswordResetNotification::class);
    }

    public function test_reset_password_failure_keeps_server_running(): void
    {
        [, $instance] = $this->provisionedTencent();
        $task = ProvisioningTask::create([
            'order_id' => $instance->order_id,
            'vps_instance_id' => $instance->id,
            'kind' => 'reset_password',
            'status' => 'pending',
            'provider' => 'tencent',
            'input' => [],
        ]);

        $this->fakeTencent([
            'ResetInstancesPassword' => ['Error' => ['Code' => 'OperationDenied', 'Message' => 'Busy']],
        ]);

        try {
            (new ResetPasswordJob($task->id))->handle(app(ProviderManager::class));
        } catch (\Throwable) {
            // expected non-retryable fail
        }

        $this->assertSame('failed', $task->fresh()->status);
        $this->assertSame('running', $instance->fresh()->status);
    }

    // ============ 4. Customer Day-2: Firewall CRUD, Traffic, Upgrade ============

    public function test_firewall_crud_and_ssh_protection(): void
    {
        [, $instance, $customer] = $this->provisionedTencent();

        $this->fakeTencent([
            'DescribeFirewallRules' => ['FirewallRuleSet' => [
                ['Protocol' => 'TCP', 'Port' => '22', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'ssh'],
                ['Protocol' => 'TCP', 'Port' => '8080', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'app'],
            ]],
            'CreateFirewallRules' => [],
            'DeleteFirewallRules' => [],
        ]);

        $this->actingAs($customer)
            ->getJson(route('dashboard.vps.day2.firewall.index', $instance->id))
            ->assertOk()
            ->assertJsonCount(2, 'rules');

        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.day2.firewall.store', $instance->id), [
                'protocol' => 'TCP',
                'port' => '3000',
                'cidr' => '0.0.0.0/0',
                'description' => 'web',
            ])
            ->assertOk()
            ->assertJsonPath('created', 1);

        // Deleting SSH (port 22) without confirm_ssh=true must be blocked.
        $this->actingAs($customer)
            ->deleteJson(route('dashboard.vps.day2.firewall.destroy', $instance->id), [
                'protocol' => 'TCP',
                'port' => '22',
                'cidr' => '0.0.0.0/0',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ssh_protection');

        // With confirm_ssh=true it succeeds.
        $this->actingAs($customer)
            ->deleteJson(route('dashboard.vps.day2.firewall.destroy', $instance->id), [
                'protocol' => 'TCP',
                'port' => '22',
                'cidr' => '0.0.0.0/0',
                'confirm_ssh' => true,
            ])
            ->assertOk()
            ->assertJsonPath('deleted', 1);
    }

    public function test_traffic_endpoint_returns_usage_and_caches_for_60s(): void
    {
        [, $instance, $customer] = $this->provisionedTencent();
        Cache::forget('tencent:traffic:'.$instance->id);

        $this->fakeTencent([
            'DescribeInstancesTrafficPackages' => ['InstanceTrafficPackageSet' => [[
                'TrafficPackageSet' => [[
                    'TrafficUsed' => 250_000_000_000,
                    'TrafficPackageTotal' => 1_000_000_000_000,
                    'Deadline' => '2026-12-19T00:00:00Z',
                ]],
            ]]],
        ]);

        $this->actingAs($customer)
            ->getJson(route('dashboard.vps.day2.traffic', $instance->id))
            ->assertOk()
            ->assertJsonPath('percent', 25);

        $this->actingAs($customer)
            ->getJson(route('dashboard.vps.day2.traffic', $instance->id))
            ->assertOk();

        $this->assertSame(1, $this->actionCounts['DescribeInstancesTrafficPackages'] ?? 0);
    }

    public function test_upgrade_endpoints_create_invoice_and_reject_downgrade(): void
    {
        [, $instance, $customer] = $this->provisionedTencent();
        $starterS = $this->spec('Starter S');
        $starterXs = $this->spec('Starter XS');

        // Preview valid upgrade.
        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.day2.upgrade.preview', $instance->id), [
                'target_spec_id' => $starterS->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        // Create upgrade order + invoice.
        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.day2.upgrade', $instance->id), [
                'target_spec_id' => $starterS->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'awaiting_payment');

        // Smaller spec rejected 422 with replacement_spec_id if set.
        $tiny = VpsSpec::create([
            'name' => 'Tiny Reject', 'category' => 'vps',
            'cpu' => 1, 'ram' => 1, 'disk' => 20, 'bandwidth' => 10,
            'cost_price' => 40000, 'sell_price' => 60000,
            'is_active' => true, 'is_renewable' => true,
            'default_stack' => 'none',
            'allowed_providers' => ['cravacloud'], 'default_provider' => 'cravacloud',
            'upstream_bundle_id' => 'bundle_starter_nmc_lin_med2_01',
            'upstream_bundle_type' => 'STARTER_BUNDLE',
        ]);
        $starterXs->update(['replacement_spec_id' => $starterS->id]);

        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.day2.upgrade', $instance->id), [
                'target_spec_id' => $tiny->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'downgrade')
            ->assertJsonPath('replacement_spec_id', $starterS->id);
    }

    // ============ 5. Multi-Tenant Isolation & Admin Mirror ============

    public function test_cross_organization_day2_access_returns_404(): void
    {
        [, $instanceOrgA] = $this->provisionedTencent();
        $userOrgB = $this->customer('orgb');

        $this->actingAs($userOrgB)
            ->getJson(route('dashboard.vps.day2.capabilities', $instanceOrgA->id))
            ->assertNotFound();
        $this->actingAs($userOrgB)
            ->postJson(route('dashboard.vps.day2.power', $instanceOrgA->id), ['action' => 'reboot'])
            ->assertNotFound();
        $this->actingAs($userOrgB)
            ->getJson(route('dashboard.vps.day2.console', $instanceOrgA->id))
            ->assertNotFound();
        $this->actingAs($userOrgB)
            ->postJson(route('dashboard.vps.day2.firewall.store', $instanceOrgA->id), [
                'protocol' => 'TCP', 'port' => '80',
            ])
            ->assertNotFound();
    }

    public function test_admin_day2_mirror_audits_without_leaking_vnc_url_and_monitoring_renders(): void
    {
        [, $instance] = $this->provisionedTencent();
        $admin = $this->admin();

        $vncSecretUrl = 'https://vnc.secret.test/one-time-token-999';
        $this->fakeTencent([
            'DescribeInstanceVncUrl' => ['InstanceVncUrl' => $vncSecretUrl],
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.instances.day2.console', $instance->id))
            ->assertOk()
            ->assertJsonPath('url', $vncSecretUrl);

        $audit = AdminAuditLog::where('action', 'vps.console')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString($vncSecretUrl, json_encode($audit->toArray()));

        $this->actingAs($admin)
            ->postJson(route('admin.instances.day2.power', $instance->id), ['action' => 'stop'])
            ->assertOk();
        Queue::assertPushed(StopVpsJob::class, 1);

        // Monitoring & instances pages render cleanly with quota & cached balance widgets.
        Cache::put('tencent:balance', 2550, 60);
        UpstreamQuotaPool::firstOrCreate(
            ['region_code' => 'ap-jakarta', 'bundle_type' => 'STARTER_BUNDLE'],
            ['capacity' => 20, 'reserved' => 1, 'used' => 2]
        );

        $this->actingAs($admin)
            ->get(route('admin.monitoring'))
            ->assertOk()
            ->assertSee('Kuota Upstream per Region')
            ->assertSee('$25.50');

        $this->actingAs($admin)
            ->get(route('admin.instances'))
            ->assertOk()
            ->assertSee('Kontrol Day-2 (admin)');
    }

    // ============ 6. Automated Reinstall via TAT ============

    public function test_reinstall_request_dispatches_tat_and_resolves_ticket_without_extra_purchase(): void
    {
        [$order, $instance, $customer] = $this->provisionedTencent('none', 'ubuntu2404');
        $initialPurchaseCount = SupplierPurchase::where('vps_instance_id', $instance->id)->count();

        $this->actingAs($customer)
            ->post(route('dashboard.vps.request-reinstall', $instance->id), [
                'os' => 'ubuntu2404',
                'control_panel' => 'coolify',
                'confirm_hostname' => $instance->hostname,
            ])
            ->assertRedirect(route('dashboard.vps.show', $instance->id));

        Queue::assertPushed(ReinstallVpsJob::class, 1);
        $ticket = SupportTicket::where('vps_instance_id', $instance->id)
            ->where('type', SupportTicket::TYPE_REINSTALL)
            ->firstOrFail();
        $this->assertSame('in_progress', $ticket->status);

        $reinstallTask = ProvisioningTask::where('vps_instance_id', $instance->id)
            ->where('kind', 'reinstall')
            ->firstOrFail();

        $this->fakeTencent([
            'ResetInstance' => [],
            'ResetInstancesPassword' => [],
            'DescribeInstances' => ['TotalCount' => 1, 'InstanceSet' => [$this->remote([
                'LatestOperation' => 'ResetInstancesPassword',
                'LatestOperationState' => 'SUCCESS',
                'InstanceState' => 'RUNNING',
            ])]],
            'DescribeAutomationAgentStatus' => ['AutomationAgentStatusSet' => [['AgentStatus' => 'Online']]],
            'DescribeFirewallRules' => ['FirewallRuleSet' => [
                ['Protocol' => 'TCP', 'Port' => '22', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
            ]],
            'CreateFirewallRules' => [],
            'RunCommand' => ['InvocationId' => 'invt-reinstall-01'],
            'DescribeInvocationTasks' => ['InvocationTaskBasicInfoSet' => [[
                'TaskStatus' => 'SUCCESS',
                'TaskResult' => base64_encode('CRAVA_INSTALL_OK coolify'),
            ]]],
        ]);

        (new ReinstallVpsJob($reinstallTask->id))->handle(app(ProviderManager::class));
        Queue::assertPushed(InstallStackJob::class, 1);

        $installTask = ProvisioningTask::where('vps_instance_id', $instance->id)
            ->where('kind', 'install_stack')
            ->latest('id')
            ->firstOrFail();

        (new InstallStackJob($installTask->id))->handle(app(ProviderManager::class));

        $instance->refresh();
        $this->assertSame('running', $instance->status);
        $this->assertSame('coolify', $instance->control_panel);
        $this->assertSame('http://'.self::PUBLIC_IP.':8000', $instance->app_url);
        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertSame($initialPurchaseCount, SupplierPurchase::where('vps_instance_id', $instance->id)->count());
        $this->assertSame('active', $order->fresh()->status);
    }

    // ============ 7. UI Gating & White-Label Regression ============

    public function test_detail_ui_gates_day2_for_tencent_vs_manual_and_stays_white_label(): void
    {
        [$order, $tencentVps, $tencentCustomer] = $this->provisionedTencent();
        [, $manualVps, $manualCustomer] = $this->manualVps();

        // Tencent instance (stack=none): Day-2 tab is default, manual legacy copy hidden, Control Panel tab hidden when stack=none, ZERO vendor leaks.
        $resTencent = $this->actingAs($tencentCustomer)
            ->get(route('dashboard.vps.show', $tencentVps->id))
            ->assertOk()
            ->assertSee("activeTab: 'day2'", false)
            ->assertSee('Kontrol Server')
            ->assertSee('Kontrol Daya')
            ->assertSee('Konsol Server (VNC)')
            ->assertSee('Reinstall OS Otomatis')
            ->assertSee('Mulai Reinstall Otomatis')
            ->assertSee('Bantuan &amp; Tiket', false)
            ->assertDontSee('Cara Reboot')
            ->assertDontSee('Ajukan Reinstall OS')
            ->assertDontSee('Catatan untuk tim')
            ->assertDontSee('dikerjakan tim kami, maksimal')
            ->assertDontSee('Minta Alamat Panel');

        $html = strtolower((string) $resTencent->getContent());
        $this->assertStringNotContainsString('tencent', $html);
        $this->assertStringNotContainsString('lighthouse', $html);
        $this->assertStringNotContainsString('bundle_starter', $html);

        // Tencent instance while reinstalling: shows automated banner, NOT manual 12h SLA ticket banner.
        $tencentVps->update(['status' => 'reinstalling']);
        \App\Models\ProvisioningTask::create([
            'order_id' => $order->id,
            'vps_instance_id' => $tencentVps->id,
            'kind' => 'reinstall',
            'status' => 'running',
            'provider' => 'tencent',
            'progress' => 45,
            'current_step' => 'Memasang ulang sistem operasi',
        ]);
        \App\Models\SupportTicket::create([
            'customer_id' => $tencentCustomer->id,
            'organization_id' => $tencentCustomer->current_organization_id,
            'vps_instance_id' => $tencentVps->id,
            'subject' => 'Reinstall OS Otomatis - '.$tencentVps->hostname,
            'type' => \App\Models\SupportTicket::TYPE_REINSTALL,
            'status' => 'in_progress',
            'priority' => 'high',
        ]);
        $this->actingAs($tencentCustomer)
            ->get(route('dashboard.vps.show', $tencentVps->id))
            ->assertOk()
            ->assertSee('Proses Otomatis Sedang Berjalan')
            ->assertDontSee('Estimasi selesai maksimal');

        // Manual instance: keeps legacy "Cara Reboot" & "Ajukan Reinstall OS", does NOT see Day-2 self-service.
        $this->actingAs($manualCustomer)
            ->get(route('dashboard.vps.show', $manualVps->id))
            ->assertOk()
            ->assertSee("activeTab: 'overview'", false)
            ->assertSee('Cara Reboot')
            ->assertSee('Ajukan Reinstall OS')
            ->assertDontSee('Kontrol Daya')
            ->assertDontSee('Konsol Server (VNC)');

        // Manual instance hitting Day-2 endpoint returns 422 unsupported (white-label).
        $manualJson = $this->actingAs($manualCustomer)
            ->postJson(route('dashboard.vps.day2.power', $manualVps->id), ['action' => 'reboot'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'unsupported');
        $this->assertStringNotContainsString('tencent', strtolower((string) $manualJson->getContent()));
    }
}
