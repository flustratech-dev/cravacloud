<?php

namespace Tests\Feature;

use App\Jobs\Provisioning\ProvisionVpsJob;
use App\Jobs\Provisioning\UpgradeBundleJob;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\SupplierPurchase;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\VpsUpgradedNotification;
use App\Services\OrderStateMachine;
use App\Services\Provisioning\ProviderManager;
use App\Services\SettingsService;
use App\Services\UpgradeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 5C — self-service bundle upgrade (prorata difference billing),
 * STRICT Rp0 (Http::fake ONLY, no real mutation call anywhere).
 *
 * Covered:
 *  - Order type routing: upgrade orders (which carry vps_instance_id) are
 *    isUpgrade(), NOT isRenewal()/isNew() — so PaymentCompletionService never
 *    misroutes them into the renewal / new-provision flows.
 *  - UpgradeService::preview prorata math + downgrade / same-spec / not-running
 *    rejection (white-label messages, replacement_spec_id surfaced).
 *  - createUpgradeOrder builds order_type=upgrade + prorata difference invoice.
 *  - handleUpgradePayment dispatches UpgradeBundleJob once (idempotent).
 *  - UpgradeBundleJob applies ModifyInstancesBundle, re-syncs cpu/ram/disk,
 *    repoints orders.vps_spec_id, records ONE upgrade SupplierPurchase
 *    (idempotent), activates the order and notifies the customer.
 *  - Downgrade target → non-retryable failure: order failed, running server
 *    left intact, no upstream purchase recorded.
 */
class TencentUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'ops@cravacloud.test';
    private const UPSTREAM_ID = 'lhins-upg01';
    private const PUBLIC_IP = '43.157.10.20';

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
            'provisioning_auto_spec_whitelist' => [$this->spec('Starter XS')->id],
            'tencent_usd_idr' => 16500,
        ]);
        \App\Support\MailIdentity::flushCache();

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

    private function spec(string $name): VpsSpec
    {
        return VpsSpec::where('category', 'vps')->where('name', $name)->firstOrFail();
    }

    private function customer(string $username = 'upg'): User
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
    }

    private function remote(array $overrides = []): array
    {
        return array_merge([
            'InstanceId' => self::UPSTREAM_ID,
            'InstanceName' => 'crava-upg',
            'InstanceState' => 'RUNNING',
            'LatestOperation' => 'ResetInstancesPassword',
            'LatestOperationState' => 'SUCCESS',
            'PublicAddresses' => [self::PUBLIC_IP],
            'PrivateAddresses' => ['10.0.0.2'],
            'BundleId' => 'bundle_starter_nmc_lin_med2_01',
            'ExpiredTime' => now()->addMonth()->format('Y-m-d\TH:i:s\Z'),
        ], $overrides);
    }

    private function happyHandlers(): array
    {
        return [
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
        ];
    }

    /** Fully provisioned running Starter XS tencent instance. */
    private function provisioned(): array
    {
        $customer = $this->customer('upg'.(++$this->seq));
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $this->spec('Starter XS')->id,
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
        ]);

        $this->fakeTencent($this->happyHandlers());
        $this->markPaid($order);

        $task = ProvisioningTask::where('order_id', $order->id)->where('kind', 'provision')->firstOrFail();
        (new ProvisionVpsJob($task->id))->handle(app(ProviderManager::class));

        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();
        // Pin a clean 30-day remaining period so prorata factor == 1.
        $instance->update(['expires_at' => now()->addDays(30)->endOfDay()]);

        return [$order->fresh(), $instance->fresh(), $customer];
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

    /** A mapped spec smaller than Starter XS, used to exercise downgrade rejection. */
    private function tinySpec(): VpsSpec
    {
        return VpsSpec::create([
            'name' => 'Tiny Test',
            'category' => 'vps',
            'cpu' => 1, 'ram' => 1, 'disk' => 20, 'bandwidth' => 10,
            'cost_price' => 40000, 'sell_price' => 60000,
            'is_active' => true, 'is_renewable' => true,
            'default_stack' => 'none',
            'allowed_providers' => ['cravacloud'],
            'default_provider' => 'cravacloud',
            'upstream_bundle_id' => 'bundle_starter_nmc_lin_med2_01',
            'upstream_bundle_type' => 'STARTER_BUNDLE',
        ]);
    }

    // ============ 1. order type routing ============

    public function test_upgrade_order_is_not_renewal_or_new(): void
    {
        [$order, $instance] = $this->provisioned();

        $upgrade = Order::create([
            'customer_id' => $order->customer_id,
            'vps_spec_id' => $this->spec('Starter S')->id,
            'vps_instance_id' => $instance->id,
            'order_type' => 'upgrade',
            'status' => 'pending',
            'channel' => 'website',
            'control_panel' => 'none',
            'os' => 'ubuntu2404',
            'datacenter_location' => 'indonesia',
            'hostname' => 'srv-upg',
            'billing_cycle' => 'monthly',
            'amount' => 50000,
            'currency' => 'IDR',
        ]);

        $this->assertTrue($upgrade->isUpgrade());
        $this->assertFalse($upgrade->isRenewal(), 'Upgrade must not be misrouted as renewal.');
        $this->assertFalse($upgrade->isNew());

        // Sanity: a genuine renewal (no upgrade type) still routes as renewal.
        $renewal = Order::create([
            'customer_id' => $order->customer_id,
            'vps_spec_id' => $this->spec('Starter XS')->id,
            'vps_instance_id' => $instance->id,
            'order_type' => 'renewal',
            'status' => 'pending',
            'channel' => 'website',
            'control_panel' => 'none',
            'os' => 'ubuntu2404',
            'datacenter_location' => 'indonesia',
            'hostname' => 'srv-upg',
            'billing_cycle' => 'monthly',
            'amount' => 115000,
            'currency' => 'IDR',
        ]);
        $this->assertTrue($renewal->isRenewal());
        $this->assertFalse($renewal->isUpgrade());
        $this->assertTrue($order->fresh()->isNew());
    }

    // ============ 2. preview prorata + rejections ============

    public function test_preview_prorata_and_rejections(): void
    {
        [, $instance] = $this->provisioned();
        $service = app(UpgradeService::class);
        $target = $this->spec('Starter S');

        $preview = $service->preview($instance, $target);
        $this->assertTrue($preview['ok']);
        $this->assertSame(30, $preview['remaining_days']);
        $this->assertSame(30, $preview['cycle_days']);
        $this->assertEqualsWithDelta(50000.0, $preview['monthly_difference'], 0.01);
        $this->assertEqualsWithDelta(50000.0, $preview['prorata_amount'], 0.01);
        // HPP difference: (6.00 - 4.20) USD × 16500 × factor 1 = 29700.
        $this->assertEqualsWithDelta(29700.0, $preview['hpp_prorata'], 0.01);
        $this->assertEqualsWithDelta(20300.0, $preview['margin'], 0.01);
        $this->assertGreaterThanOrEqual($preview['prorata_amount'], $preview['total']);

        // Same spec.
        $same = $service->preview($instance, $this->spec('Starter XS'));
        $this->assertFalse($same['ok']);
        $this->assertSame('same_spec', $same['code']);

        // Downgrade (smaller resources).
        $down = $service->preview($instance, $this->tinySpec());
        $this->assertFalse($down['ok']);
        $this->assertSame('downgrade', $down['code']);

        // Not running.
        $instance->update(['status' => 'stopped']);
        $stopped = $service->preview($instance->fresh(), $target);
        $this->assertFalse($stopped['ok']);
        $this->assertSame('not_running', $stopped['code']);
    }

    // ============ 3. create upgrade order + invoice ============

    public function test_create_upgrade_order_and_invoice(): void
    {
        [, $instance] = $this->provisioned();
        $target = $this->spec('Starter S');

        $result = app(UpgradeService::class)->createUpgradeOrder($instance, $target);
        $order = $result['order'];
        $invoice = $result['invoice'];

        $this->assertSame('upgrade', $order->order_type);
        $this->assertSame($instance->id, $order->vps_instance_id);
        $this->assertSame($target->id, $order->vps_spec_id);
        $this->assertSame('pending', $order->status);
        $this->assertEqualsWithDelta($result['preview']['total'], (float) $order->amount, 0.01);
        $this->assertNotNull($invoice);
        $this->assertEqualsWithDelta($result['preview']['total'], (float) $invoice->amount, 0.01);
        $this->assertTrue($invoice->items->contains(fn ($i) => ($i->metadata['kind'] ?? null) === 'upgrade_prorata'));
        $this->assertSame($target->id, $order->metadata['upgrade']['to_spec_id']);
    }

    // ============ 4. payment dispatches job once (idempotent) ============

    public function test_handle_upgrade_payment_dispatches_job_once(): void
    {
        [, $instance] = $this->provisioned();
        $result = app(UpgradeService::class)->createUpgradeOrder($instance, $this->spec('Starter S'));
        $order = $result['order'];

        $this->markPaid($order); // triggers PaymentCompletionService → handleUpgradePayment

        Queue::assertPushed(UpgradeBundleJob::class, 1);
        $this->assertTrue($order->fresh()->metadata['upgrade_dispatched']);
        $this->assertDatabaseHas('provisioning_tasks', [
            'order_id' => $order->id,
            'kind' => 'upgrade',
        ]);

        // Second invocation must not double-dispatch.
        app(UpgradeService::class)->handleUpgradePayment($order->fresh());
        Queue::assertPushed(UpgradeBundleJob::class, 1);
    }

    // ============ 5. job applies upgrade end-to-end ============

    public function test_upgrade_job_applies_bundle_and_records_purchase(): void
    {
        [$originalOrder, $instance, $customer] = $this->provisioned();
        $target = $this->spec('Starter S');
        $result = app(UpgradeService::class)->createUpgradeOrder($instance, $target);
        $upgradeOrder = $result['order'];
        $this->markPaid($upgradeOrder);

        $task = ProvisioningTask::where('order_id', $upgradeOrder->id)->where('kind', 'upgrade')->firstOrFail();

        $this->fakeTencent([
            'DescribeAccountBalance' => ['Balance' => 100000],
            'ModifyInstancesBundle' => [],
            'DescribeInstances' => ['TotalCount' => 1, 'InstanceSet' => [$this->remote([
                'BundleId' => 'bundle_starter_nmc_lin_med4_01',
            ])]],
        ]);

        (new UpgradeBundleJob($task->id))->handle(app(ProviderManager::class));

        $instance->refresh();
        $this->assertSame(2, $instance->cpu);
        $this->assertSame(4, $instance->ram);
        $this->assertSame(60, $instance->disk);
        $this->assertSame('bundle_starter_nmc_lin_med4_01', $instance->provider_meta['bundle_id']);
        $this->assertSame('running', $instance->status);

        // Original order repointed to the upgraded plan (renewal bills new plan).
        $this->assertSame($target->id, $originalOrder->fresh()->vps_spec_id);
        $this->assertSame('active', $upgradeOrder->fresh()->status);

        // Exactly one upgrade SupplierPurchase, idempotent on re-run.
        $reference = self::UPSTREAM_ID.'-UPG-'.$upgradeOrder->id;
        $purchase = SupplierPurchase::where('upstream_reference', $reference)->first();
        $this->assertNotNull($purchase);
        $this->assertEqualsWithDelta(29700.0, (float) $purchase->actual_cost, 0.01);
        $this->assertSame('upgrade', $purchase->metadata['kind']);

        Notification::assertSentTo($customer, VpsUpgradedNotification::class);

        // Re-running the succeeded task is a no-op (no second purchase).
        (new UpgradeBundleJob($task->id))->handle(app(ProviderManager::class));
        $this->assertSame(1, SupplierPurchase::where('upstream_reference', $reference)->count());
    }

    // ============ 6. downgrade target fails safely ============

    public function test_downgrade_job_fails_and_keeps_server_running(): void
    {
        [, $instance, $customer] = $this->provisioned();
        $tiny = $this->tinySpec();

        $upgradeOrder = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $tiny->id,
            'vps_instance_id' => $instance->id,
            'order_type' => 'upgrade',
            'status' => 'pending',
            'channel' => 'website',
            'control_panel' => 'none',
            'os' => 'ubuntu2404',
            'datacenter_location' => 'indonesia',
            'hostname' => 'srv-down',
            'billing_cycle' => 'monthly',
            'amount' => 0,
            'currency' => 'IDR',
        ]);
        $this->markPaid($upgradeOrder);

        $task = ProvisioningTask::where('order_id', $upgradeOrder->id)->where('kind', 'upgrade')->firstOrFail();
        $this->fakeTencent([
            'DescribeInstances' => ['TotalCount' => 1, 'InstanceSet' => [$this->remote()]],
        ]);

        (new UpgradeBundleJob($task->id))->handle(app(ProviderManager::class));

        $this->assertSame('failed', $task->fresh()->status);
        $this->assertSame('failed', $upgradeOrder->fresh()->status);
        // Running server is NOT torn down on a rejected downgrade.
        $this->assertSame('running', $instance->fresh()->status);
        $this->assertSame(0, SupplierPurchase::where('order_id', $upgradeOrder->id)->count());
        Notification::assertNotSentTo($customer, VpsUpgradedNotification::class);
    }
}