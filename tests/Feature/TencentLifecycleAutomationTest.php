<?php

namespace Tests\Feature;

use App\Jobs\Provisioning\DestroyVpsJob;
use App\Jobs\Provisioning\ProvisionVpsJob;
use App\Jobs\Provisioning\RenewVpsJob;
use App\Jobs\Provisioning\StartVpsJob;
use App\Jobs\Provisioning\StopVpsJob;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\Subscription;
use App\Models\SupplierPurchase;
use App\Models\UpstreamQuotaPool;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\AdminAlertService;
use App\Services\OrderStateMachine;
use App\Services\Provisioning\ProviderManager;
use App\Services\SettingsService;
use App\Services\VpsStateMachine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 4 — lifecycle automation, STRICT Rp0 (Http::fake ONLY, no real
 * mutation call anywhere).
 *
 * Covered (per Phase 4 brief):
 *  - renew normal: customer period extended first, upstream RenewInstances
 *    1× (Period + NOTIFY_AND_MANUAL_RENEW), renewal SupplierPurchase
 *    idempotent, supplier_expires_at from real upstream ExpiredTime
 *  - SHUTDOWN (recycle bin) recovery via RenewInstances (NOT StartInstances)
 *    + public/private IP re-sync (Revisi 4)
 *  - STOPPED instance renewed then started
 *  - renew InsufficientBalance → renew_pending + admin alert, customer
 *    validity intact, no upstream purchase
 *  - billing:suspend-overdue → StopVpsJob; suspended AFTER job success
 *  - billing:terminate-expired → safe deadline min(customer, upstream+safety)
 *    → DestroyVpsJob (Isolate→Terminate) + idempotent releaseUsed
 *  - admin suspend/unsuspend/terminate tencent paths (STOPPED→Start,
 *    SHUTDOWN→Renew) + manual regression
 *  - provider:reconcile: no-op without non-manual instances, status/IP sync,
 *    quota sync, orphan crava-* alert WITHOUT auto-terminate, expiry drift
 *
 * Patch A regressions live in TencentAutoProvisioningTest (password-free
 * task.output, double-failed() guard, order.metadata quota flag, OS/stack
 * compatibility gate).
 */
class TencentLifecycleAutomationTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'ops@cravacloud.test';
    private const UPSTREAM_ID = 'lhins-test01';
    private const PUBLIC_IP = '43.157.10.20';
    private const NEW_IP = '43.157.99.77';

    private int $seq = 0;

    /** @var array<string, mixed>|null */
    private ?array $tencentHandlers = null;

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
            'mail_admin_addresses' => self::ADMIN_EMAIL,
            'admin_notification_email' => self::ADMIN_EMAIL,
            'admin_notification_whatsapp' => '0811-1111-1111',
            'provisioning_auto_enabled' => true,
            'provisioning_auto_spec_whitelist' => [$this->starterSpec()->id],
            'tencent_usd_idr' => 16500,
        ]);
        \App\Support\MailIdentity::flushCache();

        // ONE delegating fake (re-calling Http::fake wipes recorded history).
        Http::fake(function (HttpRequest $request) {
            $action = $request->header('X-TC-Action')[0] ?? null;

            if ($action === null || $this->tencentHandlers === null) {
                return Http::response(['success' => true], 200);
            }

            if (! array_key_exists($action, $this->tencentHandlers)) {
                throw new \RuntimeException("Unexpected Tencent action in test: {$action}");
            }

            $body = json_decode($request->body(), true) ?: [];
            $handler = $this->tencentHandlers[$action];
            $response = is_callable($handler) ? $handler($body, $request) : $handler;

            return Http::response(['Response' => $response + ['RequestId' => 'req-test']]);
        });
    }

    // ============================== helpers ==============================

    private function starterSpec(): VpsSpec
    {
        return VpsSpec::where('category', 'vps')->where('name', 'Starter XS')->firstOrFail();
    }

    private function customer(string $username = 'budi'): User
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

    private function adminUser(): User
    {
        $user = User::create([
            'username' => 'adminlife',
            'email' => 'adminlife@cravacloud.test',
            'full_name' => 'Admin Lifecycle',
            'password' => Hash::make('password123'),
            'channel' => 'website',
        ]);
        // is_admin is NOT fillable (security) — must forceFill.
        $user->forceFill(['is_admin' => true])->save();
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return $user->fresh();
    }

    private function pendingOrder(User $customer, array $attrs = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $this->starterSpec()->id,
            'control_panel' => 'none',
            'hostname' => 'srv-'.$customer->username,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'order_type' => 'new',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 115000,
            'currency' => 'IDR',
        ], $attrs));
    }

    private function markPaid(Order $order): void
    {
        app(OrderStateMachine::class)->transition($order, 'paid', [
            'reason' => 'Pembayaran test lunas.',
            'actor_type' => 'system',
            'onLocked' => function (Order $locked) {
                $locked->paid_at = now();
                $locked->save();
            },
        ]);
    }

    private function fakeTencent(array $handlers): void
    {
        $this->tencentHandlers = $handlers;
    }

    private function remote(array $overrides = []): array
    {
        return array_merge([
            'InstanceId' => self::UPSTREAM_ID,
            'InstanceName' => 'crava-test',
            'InstanceState' => 'RUNNING',
            'LatestOperation' => 'ResetInstancesPassword',
            'LatestOperationState' => 'SUCCESS',
            'PublicAddresses' => [self::PUBLIC_IP],
            'PrivateAddresses' => ['10.0.0.2'],
            'BundleId' => 'bundle_starter_nmc_lin_med2_01',
            'ExpiredTime' => now()->addMonth()->format('Y-m-d\TH:i:s\Z'),
        ], $overrides);
    }

    private function happyHandlers(array $overrides = []): array
    {
        return array_merge([
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
            'RunCommand' => ['InvocationId' => 'invt-test01'],
            'DescribeInvocationTasks' => ['InvocationTaskBasicInfoSet' => [[
                'TaskStatus' => 'SUCCESS',
                'TaskResult' => base64_encode('CRAVA_INSTALL_OK none'),
            ]]],
        ], $overrides);
    }

    /** Fully provisioned tencent instance (running/active, used=1, purchase). */
    private function provisioned(): array
    {
        $customer = $this->customer('life'.(++$this->seq));
        $order = $this->pendingOrder($customer);
        $this->fakeTencent($this->happyHandlers());
        $this->markPaid($order);

        $task = ProvisioningTask::where('order_id', $order->id)->where('kind', 'provision')->firstOrFail();
        (new ProvisionVpsJob($task->id))->handle(app(ProviderManager::class));

        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();

        return [$order->fresh(), $instance->fresh(), $customer];
    }

    private function subscriptionFor(VpsInstance $instance, string $status, array $attrs = []): Subscription
    {
        return Subscription::create(array_merge([
            'customer_id' => $instance->customer_id,
            'vps_spec_id' => $instance->order?->vps_spec_id ?? $this->starterSpec()->id,
            'vps_instance_id' => $instance->id,
            'order_id' => $instance->order_id,
            'status' => $status,
            'billing_cycle' => 'monthly',
            'unit_amount' => 115000,
            'currency' => 'IDR',
            'auto_renew' => false,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => $instance->expires_at ?? now()->addMonth(),
            'next_billing_at' => $instance->expires_at ?? now()->addMonth(),
        ], $attrs));
    }

    private function manualInstance(User $customer): VpsInstance
    {
        return VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'hostname' => 'srv-manual-'.$customer->username,
            'public_ip' => '139.180.200.20',
            'ssh_port' => 22,
            'os' => 'ubuntu2404',
            'status' => 'running',
            'cpu' => 2, 'ram' => 2, 'disk' => 40,
            'control_panel' => 'none',
            'provider' => 'manual',
            'billing_cycle' => 'monthly',
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function renewTask(VpsInstance $instance, array $input = []): ProvisioningTask
    {
        return ProvisioningTask::create([
            'order_id' => $instance->order_id,
            'vps_instance_id' => $instance->id,
            'kind' => 'renew',
            'status' => 'pending',
            'provider' => 'tencent',
            'input' => array_merge(['months' => 1], $input),
        ]);
    }

    private function pool(string $region = 'ap-jakarta', string $type = 'STARTER_BUNDLE'): UpstreamQuotaPool
    {
        return UpstreamQuotaPool::where('region_code', $region)->where('bundle_type', $type)->firstOrFail();
    }

    private function sentCount(string $action, ?callable $bodyFilter = null): int
    {
        $count = 0;
        Http::recorded(function (HttpRequest $request) use ($action, $bodyFilter, &$count) {
            if (! $request->hasHeader('X-TC-Action', $action)) {
                return false;
            }
            if ($bodyFilter !== null) {
                $body = json_decode($request->body(), true) ?: [];
                if (! $bodyFilter($body)) {
                    return false;
                }
            }
            $count++;

            return false;
        });

        return $count;
    }

    // ============ 1. renewal normal — customer first, upstream once ============

    public function test_renewal_paid_extends_customer_period_and_renews_upstream_once(): void
    {
        [$order, $instance, $customer] = $this->provisioned();
        $originalExpires = $instance->expires_at->copy();

        $oldExp = now()->addMonth()->format('Y-m-d\TH:i:s\Z');
        $newExp = now()->addMonths(2)->format('Y-m-d\TH:i:s\Z');
        $calls = 0;
        $this->fakeTencent([
            'DescribeAccountBalance' => ['Balance' => 100000],
            'DescribeInstances' => function () use (&$calls, $oldExp, $newExp) {
                $calls++;

                return ['TotalCount' => 1, 'InstanceSet' => [$this->remote([
                    'ExpiredTime' => $calls === 1 ? $oldExp : $newExp,
                ])]];
            },
            'RenewInstances' => [],
        ]);

        // Renewal order paid through the REAL payment completion path.
        $renewal = $this->pendingOrder($customer, [
            'order_type' => 'renewal',
            'vps_instance_id' => $instance->id,
        ]);
        $this->markPaid($renewal);

        // Customer period extended FIRST (DB commit), job dispatched after.
        $instance->refresh();
        $this->assertTrue($instance->expires_at->greaterThan($originalExpires));
        $this->assertSame('active', $renewal->fresh()->status);
        $renewTask = ProvisioningTask::where('order_id', $renewal->id)->where('kind', 'renew')->firstOrFail();
        Queue::assertPushed(RenewVpsJob::class);

        // Run the upstream renewal.
        (new RenewVpsJob($renewTask->id))->handle(app(ProviderManager::class));

        $this->assertSame('succeeded', $renewTask->fresh()->status);
        $this->assertSame('running', $instance->fresh()->status);
        $this->assertSame(1, $this->sentCount('RenewInstances'));
        Http::assertSent(function (HttpRequest $r) {
            if (! $r->hasHeader('X-TC-Action', 'RenewInstances')) {
                return false;
            }
            $body = json_decode($r->body(), true);

            return ($body['InstanceIds'] ?? null) === [self::UPSTREAM_ID]
                && ($body['InstanceChargePrepaid']['Period'] ?? null) === 1
                && ($body['InstanceChargePrepaid']['RenewFlag'] ?? null) === 'NOTIFY_AND_MANUAL_RENEW';
        });

        // Renewal SupplierPurchase: distinct reference, real upstream expiry.
        $reference = self::UPSTREAM_ID.'-RENEW-'.$renewal->id;
        $purchase = SupplierPurchase::where('vps_instance_id', $instance->id)
            ->where('upstream_reference', $reference)->firstOrFail();
        $this->assertSame($renewal->id, $purchase->order_id);
        $this->assertSame('IDR', $purchase->currency);
        $this->assertEquals(round(4.20 * 16500, 2), $purchase->actual_cost);
        $this->assertSame(
            Carbon::parse($newExp)->format('Y-m-d H:i'),
            $purchase->supplier_expires_at->format('Y-m-d H:i')
        );
        $this->assertSame(2, SupplierPurchase::where('vps_instance_id', $instance->id)->count());
        $this->assertSame(1, $this->pool()->used); // renewal never touches quota

        // Idempotent: re-running the finished job changes nothing.
        (new RenewVpsJob($renewTask->id))->handle(app(ProviderManager::class));
        $this->assertSame(1, $this->sentCount('RenewInstances'));
        $this->assertSame(1, SupplierPurchase::where('upstream_reference', $reference)->count());
    }

    // ============ 2. SHUTDOWN recovery via renew + IP re-sync (Revisi 4) ============

    public function test_shutdown_instance_recovered_via_renew_with_new_ip_synced(): void
    {
        [$order, $instance] = $this->provisioned();
        app(VpsStateMachine::class)->transition($instance, 'suspended', [
            'reason' => 'Test: instance masuk recycle bin upstream.',
            'actor_type' => 'system',
        ]);

        $calls = 0;
        $this->fakeTencent([
            'DescribeAccountBalance' => ['Balance' => 100000],
            'DescribeInstances' => function () use (&$calls) {
                $calls++;
                if ($calls === 1) {
                    return ['TotalCount' => 1, 'InstanceSet' => [$this->remote([
                        'InstanceState' => 'SHUTDOWN',
                        'ExpiredTime' => now()->subDay()->format('Y-m-d\TH:i:s\Z'),
                    ])]];
                }
                if ($calls === 2) {
                    return ['TotalCount' => 1, 'InstanceSet' => [$this->remote(['InstanceState' => 'RECOVERING'])]];
                }

                return ['TotalCount' => 1, 'InstanceSet' => [$this->remote([
                    'PublicAddresses' => [self::NEW_IP],
                    'PrivateAddresses' => ['10.0.0.9'],
                    'ExpiredTime' => now()->addMonth()->format('Y-m-d\TH:i:s\Z'),
                ])]];
            },
            'RenewInstances' => [],
            'StartInstances' => [],
        ]);

        $task = $this->renewTask($instance->fresh());
        (new RenewVpsJob($task->id))->handle(app(ProviderManager::class));

        $this->assertSame('succeeded', $task->fresh()->status);
        // Recovery = RenewInstances, BUKAN StartInstances.
        $this->assertSame(1, $this->sentCount('RenewInstances'));
        $this->assertSame(0, $this->sentCount('StartInstances'));

        // IP berubah pasca recovery → wajib re-sync.
        $instance->refresh();
        $this->assertSame(self::NEW_IP, $instance->public_ip);
        $this->assertSame('10.0.0.9', $instance->private_ip);
        $this->assertSame('running', $instance->status);
    }

    // ============ 3. STOPPED instance: renew then start ============

    public function test_stopped_instance_renewed_then_started(): void
    {
        [$order, $instance] = $this->provisioned();

        $calls = 0;
        $this->fakeTencent([
            'DescribeAccountBalance' => ['Balance' => 100000],
            'DescribeInstances' => function () use (&$calls) {
                $calls++;

                return ['TotalCount' => 1, 'InstanceSet' => [$this->remote([
                    'InstanceState' => $calls <= 2 ? 'STOPPED' : 'RUNNING',
                ])]];
            },
            'RenewInstances' => [],
            'StartInstances' => [],
        ]);

        $task = $this->renewTask($instance);
        (new RenewVpsJob($task->id))->handle(app(ProviderManager::class));

        $this->assertSame('succeeded', $task->fresh()->status);
        $this->assertSame(1, $this->sentCount('RenewInstances'));
        $this->assertSame(1, $this->sentCount('StartInstances'));
        $this->assertSame('running', $instance->fresh()->status);
    }

    // ============ 4. renew InsufficientBalance → renew_pending + alert ============

    public function test_renew_insufficient_balance_sets_renew_pending_and_keeps_customer_period(): void
    {
        [$order, $instance, $customer] = $this->provisioned();
        $originalExpires = $instance->expires_at->copy();

        $alerts = \Mockery::spy(AdminAlertService::class);
        $this->instance(AdminAlertService::class, $alerts);

        $this->fakeTencent([
            'DescribeAccountBalance' => ['Balance' => 0],
            'DescribeInstances' => ['TotalCount' => 1, 'InstanceSet' => [$this->remote()]],
            'RenewInstances' => [],
        ]);

        $renewal = $this->pendingOrder($customer, [
            'order_type' => 'renewal',
            'vps_instance_id' => $instance->id,
        ]);
        $this->markPaid($renewal);

        $renewTask = ProvisioningTask::where('order_id', $renewal->id)->where('kind', 'renew')->firstOrFail();
        (new RenewVpsJob($renewTask->id))->handle(app(ProviderManager::class));

        // Task gagal, TAPI masa aktif pelanggan tetap diperpanjang.
        $this->assertSame('failed', $renewTask->fresh()->status);
        $instance->refresh();
        $this->assertTrue($instance->expires_at->greaterThan($originalExpires));
        $this->assertSame('running', $instance->status); // tidak dibalik ke error
        $this->assertTrue((bool) ($instance->provider_meta['renew_pending'] ?? false));

        // Tidak ada pembelian upstream, tidak ada RenewInstances.
        $this->assertSame(0, $this->sentCount('RenewInstances'));
        $this->assertSame(1, SupplierPurchase::where('vps_instance_id', $instance->id)->count());

        $alerts->shouldHaveReceived('digest')
            ->once()
            ->withArgs(fn (string $subject) => str_contains($subject, 'saldo tidak cukup'));
    }

    // ============ 5. suspend overdue: stop upstream dulu, suspended setelah sukses ============

    public function test_suspend_overdue_stops_upstream_before_marking_suspended(): void
    {
        [$order, $instance, $customer] = $this->provisioned();
        $sub = $this->subscriptionFor($instance, 'grace_period', [
            'grace_period_ends_at' => now()->subDay(),
        ]);

        // Regression manual: instance manual di-suspend LANGSUNG (tanpa job).
        $manualCustomer = $this->customer('manualsuspend');
        $manual = $this->manualInstance($manualCustomer);
        $manualSub = $this->subscriptionFor($manual, 'grace_period', [
            'grace_period_ends_at' => now()->subDay(),
        ]);

        $this->fakeTencent([
            'StopInstances' => [],
            // A2: stop() now polls DescribeInstances until SUCCESS && STOPPED.
            'DescribeInstances' => ['TotalCount' => 1, 'InstanceSet' => [$this->remote(['InstanceState' => 'STOPPED'])]],
        ]);

        $this->artisan('billing:suspend-overdue')->assertSuccessful();

        $this->assertSame('suspended', $sub->fresh()->status);
        $this->assertSame('suspended', $manual->fresh()->status); // manual langsung
        $this->assertSame('running', $instance->fresh()->status); // tencent menunggu job
        Queue::assertPushed(StopVpsJob::class, 1);
        $this->assertSame(0, $this->sentCount('StopInstances'));

        $stopTask = ProvisioningTask::where('vps_instance_id', $instance->id)->where('kind', 'stop')->firstOrFail();
        $this->assertSame('suspended', $stopTask->input['target_status']);
        (new StopVpsJob($stopTask->id))->handle(app(ProviderManager::class));

        $this->assertSame('suspended', $instance->fresh()->status); // setelah job sukses
        $this->assertSame(1, $this->sentCount('StopInstances'));
    }

    // ============ 6. terminate expired: 2 tahap + releaseUsed idempoten ============

    public function test_terminate_expired_uses_safe_deadline_and_releases_quota_idempotently(): void
    {
        [$order, $instance, $customer] = $this->provisioned();
        app(VpsStateMachine::class)->transition($instance, 'suspended', [
            'reason' => 'Test suspend.', 'actor_type' => 'system',
        ]);

        // Upstream expiry 20 hari lalu → deadline upstream -8 hari (lewat).
        SupplierPurchase::where('vps_instance_id', $instance->id)
            ->update(['supplier_expires_at' => now()->subDays(20)]);

        $sub = $this->subscriptionFor($instance->fresh(), 'suspended');
        DB::table('subscriptions')->where('id', $sub->id)
            ->update(['updated_at' => now()->subDays(40)]); // customer deadline -10 hari

        $calls = 0;
        $this->fakeTencent([
            'DescribeInstances' => function () use (&$calls) {
                $calls++;

                return ['TotalCount' => 1, 'InstanceSet' => [$this->remote([
                    'InstanceState' => $calls === 1 ? 'RUNNING' : 'SHUTDOWN',
                ])]];
            },
            'IsolateInstances' => [],
            'TerminateInstances' => [],
        ]);

        $this->artisan('billing:terminate-expired')->assertSuccessful();

        // A1: cron only DISPATCHES destroy for tencent — subscription stays
        // SUSPENDED and no termination notification is sent yet.
        $this->assertSame('suspended', $sub->fresh()->status);
        Queue::assertPushed(DestroyVpsJob::class);
        $this->assertSame('suspended', $instance->fresh()->status); // belum terminated
        $this->assertSame(0, $this->sentCount('TerminateInstances'));
        Notification::assertNotSentTo($customer, \App\Notifications\ServiceTerminatedNotification::class);

        $destroyTask = ProvisioningTask::where('vps_instance_id', $instance->id)->where('kind', 'destroy')->firstOrFail();
        (new DestroyVpsJob($destroyTask->id))->handle(app(ProviderManager::class));

        // Dua tahap wajib: Isolate → SHUTDOWN → Terminate.
        $this->assertSame(1, $this->sentCount('IsolateInstances'));
        $this->assertSame(1, $this->sentCount('TerminateInstances'));
        $this->assertSame('terminated', $instance->fresh()->status);
        $this->assertSame('terminated', $order->fresh()->status);

        // A1: subscription severed ONLY after upstream destroy succeeded.
        $this->assertSame('terminated', $sub->fresh()->status);
        $this->assertNotNull($sub->fresh()->ended_at);
        Notification::assertSentToTimes($customer, \App\Notifications\ServiceTerminatedNotification::class, 1);

        // Kuota used dilepas + flag idempoten.
        $this->assertSame(0, $this->pool()->used);
        $this->assertTrue((bool) ($instance->fresh()->provider_meta['quota_released'] ?? false));

        // Task destroy BARU (upstream sudah TERMINATED) → tidak double-release.
        $this->fakeTencent([
            'DescribeInstances' => ['TotalCount' => 1, 'InstanceSet' => [$this->remote(['InstanceState' => 'TERMINATED'])]],
            'IsolateInstances' => [],
            'TerminateInstances' => [],
        ]);
        $retryTask = ProvisioningTask::create([
            'order_id' => $order->id,
            'vps_instance_id' => $instance->id,
            'kind' => 'destroy',
            'status' => 'pending',
            'provider' => 'tencent',
            'input' => [],
        ]);
        (new DestroyVpsJob($retryTask->id))->handle(app(ProviderManager::class));

        $this->assertSame(0, $this->pool()->used);
        $this->assertSame(1, $this->sentCount('IsolateInstances'));
        $this->assertSame(1, $this->sentCount('TerminateInstances'));
        // Subscription already terminated → retry never re-notifies the customer.
        Notification::assertSentToTimes($customer, \App\Notifications\ServiceTerminatedNotification::class, 1);
    }

    // ============ 7. terminate ditunda bila jendela aman belum lewat ============

    public function test_terminate_deferred_until_safe_deadline_and_upstream_window_can_trigger_first(): void
    {
        [$order, $instance] = $this->provisioned();
        app(VpsStateMachine::class)->transition($instance, 'suspended', [
            'reason' => 'Test suspend.', 'actor_type' => 'system',
        ]);

        // Customer deadline +20 hari, upstream deadline +7 hari → keduanya
        // belum lewat → JANGAN terminate.
        SupplierPurchase::where('vps_instance_id', $instance->id)
            ->update(['supplier_expires_at' => now()->subDays(5)]);
        $sub = $this->subscriptionFor($instance->fresh(), 'suspended');
        DB::table('subscriptions')->where('id', $sub->id)
            ->update(['updated_at' => now()->subDays(10)]);

        $this->fakeTencent([]);
        $this->artisan('billing:terminate-expired')->assertSuccessful();

        $this->assertSame('suspended', $sub->fresh()->status);
        Queue::assertNotPushed(DestroyVpsJob::class);
        $this->assertSame('suspended', $instance->fresh()->status);

        // Upstream expiry dimundurkan → jendela aman upstream lewat DULUAN
        // (min(customer, upstream+safety)) meskipun tenggang pelanggan belum.
        SupplierPurchase::where('vps_instance_id', $instance->id)
            ->update(['supplier_expires_at' => now()->subDays(15)]);

        $this->artisan('billing:terminate-expired')->assertSuccessful();
        // A1: upstream window triggered destroy, but the subscription stays
        // SUSPENDED until DestroyVpsJob succeeds (severance moved to onSuccess).
        $this->assertSame('suspended', $sub->fresh()->status);
        Queue::assertPushed(DestroyVpsJob::class);
    }

    // ============ 8. admin suspend / unsuspend / terminate (tencent + manual) ============

    public function test_admin_lifecycle_routes_use_upstream_jobs_for_tencent_only(): void
    {
        [$order, $instance] = $this->provisioned();
        $admin = $this->adminUser();

        $calls = 0;
        $this->fakeTencent([
            'DescribeAccountBalance' => ['Balance' => 100000],
            'DescribeInstances' => function () use (&$calls) {
                $calls++;
                // A2: start()/stop() now poll DescribeInstances (SUCCESS &&
                // target state). Sequence:
                // 1: stop() poll → STOPPED; 2: unsuspend status check → STOPPED
                // (routes to Start); 3: start() poll → RUNNING; 4: unsuspend
                // status check → SHUTDOWN (routes to Renew); 5: destroy describe
                // → RUNNING (triggers Isolate); 6+: destroy poll → SHUTDOWN.
                $state = match (true) {
                    $calls === 1 => 'STOPPED',
                    $calls === 2 => 'STOPPED',
                    $calls === 3 => 'RUNNING',
                    $calls === 4 => 'SHUTDOWN',
                    $calls === 5 => 'RUNNING',
                    default => 'SHUTDOWN',
                };

                return ['TotalCount' => 1, 'InstanceSet' => [$this->remote(['InstanceState' => $state])]];
            },
            'StopInstances' => [],
            'StartInstances' => [],
            'RenewInstances' => [],
            'IsolateInstances' => [],
            'TerminateInstances' => [],
        ]);

        // --- suspend: stop dulu, suspended setelah job sukses ---
        $this->actingAs($admin)->post(route('admin.instances.suspend', $instance->id))->assertRedirect();
        Queue::assertPushed(StopVpsJob::class);
        $this->assertSame('running', $instance->fresh()->status);

        $stopTask = ProvisioningTask::where('vps_instance_id', $instance->id)->where('kind', 'stop')->firstOrFail();
        (new StopVpsJob($stopTask->id))->handle(app(ProviderManager::class));
        $this->assertSame('suspended', $instance->fresh()->status);

        // --- unsuspend STOPPED → StartVpsJob ---
        $this->actingAs($admin)->post(route('admin.instances.unsuspend', $instance->id))->assertRedirect();
        Queue::assertPushed(StartVpsJob::class);
        $startTask = ProvisioningTask::where('vps_instance_id', $instance->id)->where('kind', 'start')->firstOrFail();
        (new StartVpsJob($startTask->id))->handle(app(ProviderManager::class));
        $this->assertSame('running', $instance->fresh()->status);

        // --- unsuspend SHUTDOWN (recycle bin) → RenewVpsJob ---
        app(VpsStateMachine::class)->transition($instance->fresh(), 'suspended', [
            'reason' => 'Test recycle bin.', 'actor_type' => 'system',
        ]);
        $this->actingAs($admin)->post(route('admin.instances.unsuspend', $instance->id))->assertRedirect();
        Queue::assertPushed(RenewVpsJob::class);
        $renewTask = ProvisioningTask::where('vps_instance_id', $instance->id)->where('kind', 'renew')->firstOrFail();
        $this->assertTrue((bool) ($renewTask->input['unsuspend'] ?? false));

        // --- terminate: destroy upstream dulu, terminated setelah sukses ---
        $this->actingAs($admin)->post(route('admin.instances.terminate', $instance->id))->assertRedirect();
        Queue::assertPushed(DestroyVpsJob::class);
        $this->assertNotSame('terminated', $instance->fresh()->status);

        $destroyTask = ProvisioningTask::where('vps_instance_id', $instance->id)->where('kind', 'destroy')->firstOrFail();
        (new DestroyVpsJob($destroyTask->id))->handle(app(ProviderManager::class));
        $this->assertSame('terminated', $instance->fresh()->status);
        $this->assertSame(0, $this->pool()->used);

        // --- regression manual: suspend LANGSUNG, tanpa job upstream ---
        $manualCustomer = $this->customer('manualadmin');
        $manual = $this->manualInstance($manualCustomer);
        $this->actingAs($admin)->post(route('admin.instances.suspend', $manual->id))->assertRedirect();
        $this->assertSame('suspended', $manual->fresh()->status);
        Queue::assertPushed(StopVpsJob::class, 1); // tetap hanya yang tencent
    }

    // ============ 9. reconcile no-op tanpa instance non-manual ============

    public function test_reconcile_is_noop_without_non_manual_instances(): void
    {
        $customer = $this->customer('manualonly');
        $this->manualInstance($customer);
        $this->fakeTencent([]);

        $this->artisan('provider:reconcile --auto-correct')->assertSuccessful();

        Http::assertNothingSent(); // nol panggilan API di deployment manual
    }

    // ============ 10. reconcile: orphan alert, sync, drift — TANPA auto-terminate ============

    public function test_reconcile_detects_orphan_and_drift_syncs_status_without_terminating(): void
    {
        [$order, $instance] = $this->provisioned();

        // NOTE: artisan command instances are resolved during RefreshDatabase
        // migrations (before any spy could be installed), so the alert is
        // asserted END-TO-END through the real AdminAlertService → WA gateway.
        $known = $this->remote([
            'InstanceName' => 'crava-'.$order->id.'-'.$instance->id,
            'InstanceState' => 'STOPPED',
            'PublicAddresses' => [self::NEW_IP],
            'ExpiredTime' => now()->addDays(40)->format('Y-m-d\TH:i:s\Z'),
        ]);
        $orphan = [
            'InstanceId' => 'lhins-orphanx',
            'InstanceName' => 'crava-999-999',
            'InstanceState' => 'RUNNING',
            'BundleId' => 'bundle_starter_nmc_lin_med2_01',
            'PublicAddresses' => ['43.157.50.50'],
        ];

        $this->fakeTencent([
            'DescribeInstances' => function (array $body) use ($known, $orphan) {
                if (! empty($body['InstanceIds'])) {
                    return ['TotalCount' => 1, 'InstanceSet' => [$known]];
                }

                return ['TotalCount' => 2, 'InstanceSet' => [$known, $orphan]];
            },
            'IsolateInstances' => [],
            'TerminateInstances' => [],
        ]);

        $this->artisan('provider:reconcile --auto-correct')->assertSuccessful();

        // Status + IP tersinkron dari upstream.
        $instance->refresh();
        $this->assertSame('stopped', $instance->status);
        $this->assertSame(self::NEW_IP, $instance->public_ip);

        // Kuota used dikoreksi dari jumlah riil (2 instance hidup).
        $this->assertSame(2, $this->pool()->used);

        // Orphan → alert admin sampai ke WA gateway, TIDAK PERNAH auto-terminate.
        // (digest mengirim $text ke WA; subjek hanya untuk email.)
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'wa.test')
            && str_contains($r->body(), 'lhins-orphanx'));
        $this->assertSame(0, $this->sentCount('TerminateInstances'));
        $this->assertSame(0, $this->sentCount('IsolateInstances'));
        $this->assertNull(VpsInstance::where('provider_resource_id', 'lhins-orphanx')->first());

        // Drift supplier_expires_at (DB +1 bulan vs upstream +40 hari) → alert.
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'wa.test')
            && str_contains($r->body(), 'supplier_expires_at tercatat'));
    }
}