<?php

namespace Tests\Feature;

use App\Jobs\Provisioning\InstallStackJob;
use App\Jobs\Provisioning\ProvisionVpsJob;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\SupplierPurchase;
use App\Models\UpstreamQuotaPool;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\AdminProvisioningFailedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\VpsProvisionedNotification;
use App\Services\AdminAlertService;
use App\Services\OrderStateMachine;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\ProviderManager;
use App\Services\Provisioning\ProvisioningFinalizer;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 3 — zero-touch provisioning end-to-end with Http::fake ONLY.
 * STRICT Rp0: no real CreateInstances / no live Tencent call anywhere here.
 *
 * Covered (per Phase 3 brief):
 *  - happy path 2-stage (infra → install_stack → finalize + SupplierPurchase)
 *  - retry idempotent: CreateInstances exactly 1×, checkpoint resume
 *  - orphan adoption on ambiguous timeout (instance-name filter)
 *  - InsufficientBalance: payment intact + admin alert + releaseReserved
 *  - reserveHard full pool: hold paid order + alert, NO auto-fallback (D4)
 *  - white-label: no 'tencent'/'lighthouse' in customer outputs
 *  - ManualProvider / gate-OFF regression: zero behavior change
 *  - firewall diff, Ubuntu vs Debian UserName, install failure settlement
 */
class TencentAutoProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'ops@cravacloud.test';
    private const UPSTREAM_ID = 'lhins-test01';
    private const PUBLIC_IP = '43.157.10.20';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        Queue::fake();

        // Deterministic test credentials + instant polling (no sleeps).
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

        // ONE fake for the whole test (re-calling Http::fake would wipe the
        // recorded history). It delegates to $tencentHandlers, swapped per
        // test/phase via fakeTencent(); non-Tencent traffic (WA gateway)
        // always gets a generic success.
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

    /** @var array<string, mixed>|null */
    private ?array $tencentHandlers = null;

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

    /** Mark paid through the REAL state machine (fires PaymentCompletionService). */
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

    /**
     * Swap the upstream handlers keyed on X-TC-Action. Unexpected actions
     * fail loudly so a stray live-shaped call can never slip through.
     */
    private function fakeTencent(array $handlers): void
    {
        $this->tencentHandlers = $handlers;
    }

    /** Standard happy-path upstream handlers (override per test as needed). */
    private function happyHandlers(array $overrides = []): array
    {
        $remote = [
            'InstanceId' => self::UPSTREAM_ID,
            'InstanceState' => 'RUNNING',
            'LatestOperation' => 'ResetInstancesPassword',
            'LatestOperationState' => 'SUCCESS',
            'PublicAddresses' => [self::PUBLIC_IP],
            'PrivateAddresses' => ['10.0.0.2'],
            'BundleId' => 'bundle_starter_nmc_lin_med2_01',
        ];

        return array_merge([
            'DescribeAccountBalance' => ['Balance' => 100000], // $1000 (divisor 100)
            'DescribeInstances' => function (array $body) use ($remote) {
                if (isset($body['Filters'])) {
                    return ['TotalCount' => 0, 'InstanceSet' => []]; // no orphan
                }

                return ['TotalCount' => 1, 'InstanceSet' => [$remote]];
            },
            'CreateInstances' => ['InstanceIdSet' => [self::UPSTREAM_ID]],
            'ResetInstancesPassword' => [],
            'DescribeAutomationAgentStatus' => ['AutomationAgentStatusSet' => [['AgentStatus' => 'Online']]],
            'DescribeFirewallRules' => ['FirewallRuleSet' => [
                ['Protocol' => 'TCP', 'Port' => '22', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
                ['Protocol' => 'TCP', 'Port' => '80,443', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT'],
            ]],
            'CreateFirewallRules' => [],
            'RunCommand' => ['InvocationId' => 'invt-test01'],
            'DescribeInvocationTasks' => ['InvocationTaskBasicInfoSet' => [[
                'TaskStatus' => 'SUCCESS',
                'TaskResult' => base64_encode('CRAVA_INSTALL_OK coolify'),
            ]]],
        ], $overrides);
    }

    private function runInfraJob(Order $order): ProvisioningTask
    {
        $task = ProvisioningTask::where('order_id', $order->id)->where('kind', 'provision')->firstOrFail();
        (new ProvisionVpsJob($task->id))->handle(app(ProviderManager::class));

        return $task->fresh();
    }

    private function pool(string $region = 'ap-jakarta', string $type = 'STARTER_BUNDLE'): UpstreamQuotaPool
    {
        return UpstreamQuotaPool::where('region_code', $region)->where('bundle_type', $type)->firstOrFail();
    }

    private function assertNoVendorWords(string $text): void
    {
        $this->assertStringNotContainsStringIgnoringCase('tencent', $text);
        $this->assertStringNotContainsStringIgnoringCase('lighthouse', $text);
    }

    // ===================== 1. happy path, two stages =====================

    public function test_happy_path_two_stage_coolify_from_paid_to_active(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrder($customer, ['control_panel' => 'coolify']);
        $this->fakeTencent($this->happyHandlers());

        $this->markPaid($order);

        // Orchestrator: paid → provisioning, instance + task created, slot reserved.
        $order->refresh();
        $this->assertSame('provisioning', $order->status);
        Queue::assertPushed(ProvisionVpsJob::class);
        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('tencent', $instance->provider);
        $this->assertSame('provisioning', $instance->status);
        $this->assertSame(1, $this->pool()->reserved);
        Notification::assertSentTo($customer, PaymentReceivedNotification::class);

        // Stage 1: infra.
        $task = $this->runInfraJob($order);
        $this->assertSame('succeeded', $task->status);
        $this->assertSame('tat_invoked', $task->output['checkpoint']);

        $instance->refresh();
        $this->assertSame(self::UPSTREAM_ID, $instance->provider_resource_id);
        $this->assertSame(self::PUBLIC_IP, $instance->public_ip);
        $this->assertSame('ubuntu', $instance->ssh_username);
        $this->assertNotEmpty($instance->initial_root_password);

        // Stage 2 task created + dispatched; customer NOT notified yet.
        $installTask = ProvisioningTask::where('order_id', $order->id)->where('kind', 'install_stack')->firstOrFail();
        $this->assertSame('invt-test01', $installTask->output['invocation_id']);
        // Review patch A3c: install stage starts at 65% — infra never jumped to 80%.
        $this->assertSame(65, $installTask->progress);
        Queue::assertPushed(InstallStackJob::class);
        $this->assertSame('provisioning', $order->fresh()->status);
        Notification::assertNotSentTo($customer, VpsProvisionedNotification::class);

        // Stage 2: first poll RUNNING (retryable), second SUCCESS → finalize.
        $polls = 0;
        $this->fakeTencent($this->happyHandlers([
            'DescribeInvocationTasks' => function () use (&$polls) {
                $polls++;

                return ['InvocationTaskBasicInfoSet' => [[
                    'TaskStatus' => $polls === 1 ? 'RUNNING' : 'SUCCESS',
                    'TaskResult' => base64_encode('CRAVA_INSTALL_OK coolify'),
                ]]];
            },
        ]));

        try {
            (new InstallStackJob($installTask->id))->handle(app(ProviderManager::class));
            $this->fail('First poll should be retryable (still running).');
        } catch (ProviderException $e) {
            $this->assertTrue($e->retryable);
            $this->assertSame('InstallPending', $e->providerCode);
        }

        (new InstallStackJob($installTask->id))->handle(app(ProviderManager::class));

        // Finalized: running/active, quota committed, SupplierPurchase (D9).
        $this->assertSame('running', $instance->fresh()->status);
        $this->assertSame('active', $order->fresh()->status);
        $this->assertSame('http://'.self::PUBLIC_IP.':8000', $instance->fresh()->app_url);
        $pool = $this->pool();
        $this->assertSame(0, $pool->reserved);
        $this->assertSame(1, $pool->used);

        $purchase = SupplierPurchase::where('vps_instance_id', $instance->id)->firstOrFail();
        $this->assertSame('tencent', $purchase->supplier);
        $this->assertSame('IDR', $purchase->currency);
        $this->assertSame(self::UPSTREAM_ID, $purchase->upstream_reference);
        $this->assertEquals(16500, $purchase->exchange_rate);
        $this->assertEquals(round(4.20 * 16500, 2), $purchase->actual_cost); // 69300
        $this->assertNotNull($purchase->supplier_expires_at);

        Notification::assertSentTo($customer, VpsProvisionedNotification::class);

        // CreateInstances exactly once; firewall diff created ONLY port 8000.
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('X-TC-Action', 'CreateInstances'));
        $this->assertSame(1, $this->sentCount('CreateInstances'));
        Http::assertSent(function (HttpRequest $r) {
            if (! $r->hasHeader('X-TC-Action', 'CreateFirewallRules')) {
                return false;
            }
            $body = json_decode($r->body(), true);
            $ports = array_column($body['FirewallRules'] ?? [], 'Port');

            return $ports === ['8000']; // 22/80/443 already covered by defaults
        });

        // Ubuntu default user sent explicitly (revision 3).
        Http::assertSent(function (HttpRequest $r) {
            if (! $r->hasHeader('X-TC-Action', 'ResetInstancesPassword')) {
                return false;
            }
            $body = json_decode($r->body(), true);

            return ($body['UserName'] ?? null) === 'ubuntu';
        });

        // TAT invoked only after agent Online gate; script is base64 SHELL.
        Http::assertSent(function (HttpRequest $r) {
            if (! $r->hasHeader('X-TC-Action', 'RunCommand')) {
                return false;
            }
            $body = json_decode($r->body(), true);
            $script = base64_decode($body['Content'] ?? '', true);

            return ($body['CommandType'] ?? null) === 'SHELL'
                && ($body['InstanceIds'] ?? null) === [self::UPSTREAM_ID]
                && is_string($script)
                && str_contains($script, 'CRAVA_INSTALL_OK');
        });

        // Re-running the finished job is a no-op (idempotent finalize).
        (new InstallStackJob($installTask->id))->handle(app(ProviderManager::class));
        $this->assertSame(1, SupplierPurchase::where('vps_instance_id', $instance->id)->count());
        $this->assertSame(1, $this->pool()->used);

        // Review patch A1: NO plaintext credential in ANY task.output —
        // the password lives only encrypted on the instance column.
        $plainPassword = $instance->fresh()->initial_root_password;
        $this->assertNotEmpty($plainPassword);
        foreach (ProvisioningTask::where('order_id', $order->id)->get() as $persistedTask) {
            $output = $persistedTask->output ?? [];
            $this->assertArrayNotHasKey('root_password', $output);
            $this->assertArrayNotHasKey('password', $output);
            $this->assertStringNotContainsString($plainPassword, json_encode($output));
            $this->assertStringNotContainsString($plainPassword, json_encode($persistedTask->input ?? []));
        }
    }

    // ============ 2. retry idempotent — CreateInstances 1× ============

    public function test_retry_resumes_from_checkpoint_and_never_recreates_instance(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrder($customer); // stack none → single stage
        $resets = 0;
        $this->fakeTencent($this->happyHandlers([
            'ResetInstancesPassword' => function () use (&$resets) {
                $resets++;
                if ($resets === 1) {
                    throw new ConnectionException('ambiguous timeout after create');
                }

                return [];
            },
        ]));

        $this->markPaid($order);

        // Attempt 1: create succeeds, password reset times out → retryable.
        try {
            $this->runInfraJob($order);
            $this->fail('Attempt 1 should throw retryable ProviderException.');
        } catch (ProviderException $e) {
            $this->assertTrue($e->retryable);
        }

        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $task = ProvisioningTask::where('order_id', $order->id)->where('kind', 'provision')->firstOrFail();
        $this->assertSame(self::UPSTREAM_ID, $instance->provider_resource_id); // persisted immediately
        $this->assertSame('running', $task->output['checkpoint']); // checkpoint survived
        $this->assertSame('running', $task->status);

        // Attempt 2: resumes AFTER 'running' — no create, no adoption lookup.
        $task = $this->runInfraJob($order);
        $this->assertSame('succeeded', $task->status);
        $this->assertSame('active', $order->fresh()->status);
        $this->assertSame('running', $instance->fresh()->status);

        $this->assertSame(1, $this->sentCount('CreateInstances'));
        // Adoption lookup happened ONLY on attempt 1 (before first create);
        // attempt 2 resumed from the persisted resource id — no lookup.
        $this->assertSame(1, $this->sentCount('DescribeInstances', fn (array $b) => isset($b['Filters'])));
        // Password reset attempted twice (attempt 1 timed out at transport
        // level — not recorded by Http::recorded; the counter is the truth).
        $this->assertSame(2, $resets);
        $this->assertSame(1, $this->sentCount('ResetInstancesPassword'));
        $this->assertSame(0, $this->pool()->reserved);
        $this->assertSame(1, $this->pool()->used);
    }

    // ============ 3. orphan adoption on ambiguous timeout ============

    public function test_orphan_adopted_by_name_after_ambiguous_create_timeout(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrder($customer);
        $creates = 0;
        $adopted = false;

        $this->fakeTencent($this->happyHandlers([
            'CreateInstances' => function () use (&$creates) {
                $creates++;
                throw new ConnectionException('timeout — instance may or may not exist');
            },
            'DescribeInstances' => function (array $body) use (&$adopted) {
                if (isset($body['Filters'])) {
                    // Adoption lookup only succeeds on attempt 2.
                    if (! $adopted) {
                        return ['TotalCount' => 0, 'InstanceSet' => []];
                    }

                    return ['TotalCount' => 1, 'InstanceSet' => [[
                        'InstanceId' => 'lhins-orphan',
                        'InstanceName' => $body['Filters'][0]['Values'][0],
                        'InstanceState' => 'RUNNING',
                        'LatestOperation' => 'ResetInstancesPassword',
                        'LatestOperationState' => 'SUCCESS',
                        'PublicAddresses' => [self::PUBLIC_IP],
                        'PrivateAddresses' => ['10.0.0.9'],
                    ]]];
                }

                return ['TotalCount' => 1, 'InstanceSet' => [[
                    'InstanceId' => 'lhins-orphan',
                    'InstanceState' => 'RUNNING',
                    'LatestOperation' => 'ResetInstancesPassword',
                    'LatestOperationState' => 'SUCCESS',
                    'PublicAddresses' => [self::PUBLIC_IP],
                    'PrivateAddresses' => ['10.0.0.9'],
                ]]];
            },
        ]));

        $this->markPaid($order);

        // Attempt 1: ambiguous create failure, nothing persisted.
        try {
            $this->runInfraJob($order);
            $this->fail('Attempt 1 should throw retryable ProviderException.');
        } catch (ProviderException $e) {
            $this->assertTrue($e->retryable);
        }
        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $this->assertNull($instance->provider_resource_id);

        // Attempt 2: the orphan now exists upstream → adopt, never re-create.
        $adopted = true;
        $task = $this->runInfraJob($order);

        $this->assertSame('succeeded', $task->status);
        $this->assertSame('lhins-orphan', $instance->fresh()->provider_resource_id);
        $this->assertSame('active', $order->fresh()->status);
        $this->assertSame(1, $creates); // only the ambiguous attempt, never a second buy
        // The failed create threw at transport level (not recorded); what
        // matters: ZERO successful CreateInstances responses were consumed.
        $this->assertSame(0, $this->sentCount('CreateInstances'));

        // Adoption used the official instance-name filter + deterministic name.
        Http::assertSent(function (HttpRequest $r) use ($order, $instance) {
            if (! $r->hasHeader('X-TC-Action', 'DescribeInstances')) {
                return false;
            }
            $body = json_decode($r->body(), true);
            $filter = $body['Filters'][0] ?? [];

            return ($filter['Name'] ?? null) === 'instance-name'
                && ($filter['Values'][0] ?? null) === 'crava-'.$order->id.'-'.$instance->id;
        });
    }

    // ============ 4. InsufficientBalance — payment intact ============

    public function test_insufficient_balance_fails_safely_payment_intact_and_reservation_released(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrder($customer);

        // Stale cached balance is HIGH — fresh:true must bypass it (D9).
        Cache::put('tencent:balance', 999999, 60);
        $this->fakeTencent($this->happyHandlers([
            'DescribeAccountBalance' => ['Balance' => 0],
        ]));

        $this->markPaid($order);
        $this->assertSame(1, $this->pool()->reserved);

        $this->runInfraJob($order); // non-retryable → handled internally, no throw

        $order->refresh();
        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $task = ProvisioningTask::where('order_id', $order->id)->where('kind', 'provision')->firstOrFail();

        $this->assertSame('failed', $task->status);
        $this->assertStringContainsStringIgnoringCase('insufficient', $task->error ?? '');
        $this->assertSame('failed', $order->status);
        $this->assertSame('error', $instance->status);

        // Pembayaran pelanggan UTUH: paid_at tetap, amount tidak berubah.
        $this->assertNotNull($order->paid_at);
        $this->assertEquals(115000, $order->amount);

        // Reservasi dilepas, tidak ada slot terpakai, tidak ada pembelian.
        $this->assertSame(0, $this->pool()->reserved);
        $this->assertSame(0, $this->pool()->used);
        $this->assertSame(0, $this->sentCount('CreateInstances'));
        $this->assertSame(0, SupplierPurchase::count());

        // Alert admin terkirim.
        Notification::assertSentOnDemand(AdminProvisioningFailedNotification::class);
    }

    // ============ 5. pool full → hold + alert, NO fallback (D4) ============

    public function test_full_pool_holds_paid_order_with_alert_and_no_auto_fallback(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrder($customer);

        // Fill the Jakarta starter pool completely.
        $this->pool()->update(['capacity' => 1, 'reserved' => 1, 'used' => 0]);

        $alerts = \Mockery::spy(AdminAlertService::class);
        $this->instance(AdminAlertService::class, $alerts);

        $this->fakeTencent($this->happyHandlers());
        $this->markPaid($order);

        // Order DITAHAN di paid — tidak provisioning, tidak failed.
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNull(VpsInstance::where('order_id', $order->id)->first());
        Queue::assertNotPushed(ProvisionVpsJob::class);
        $this->assertSame(0, $this->sentCount('CreateInstances'));

        // Tidak ada fallback otomatis ke region lain (D4).
        $singapore = $this->pool('ap-singapore');
        $this->assertSame(0, $singapore->reserved);
        $this->assertSame(0, $singapore->used);

        $alerts->shouldHaveReceived('digest')
            ->once()
            ->withArgs(fn (string $subject) => str_contains($subject, 'pool penuh'));
    }

    // ============ 6. white-label — no vendor words to customer ============

    public function test_customer_outputs_never_leak_vendor_name(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrder($customer);
        $this->fakeTencent($this->happyHandlers());

        $this->markPaid($order);
        $this->runInfraJob($order); // stack none → finalize immediately

        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();

        // Label publik provider = merek sendiri, sama untuk semua provider.
        $label = Order::customerProviderLabel('tencent');
        $this->assertSame(Order::customerProviderLabel('manual'), $label);
        $this->assertNoVendorWords($label);

        // Email kredensial (rendered) bebas nama vendor.
        $mail = (new VpsProvisionedNotification($instance->fresh()))->toMail($customer);
        $this->assertNoVendorWords($mail->render());
        $this->assertNoVendorWords($mail->subject ?? '');

        // Pesan WhatsApp bebas nama vendor.
        $wa = (new VpsProvisionedNotification($instance->fresh()))->toWhatsApp($customer);
        $this->assertNoVendorWords((string) $wa?->getContent());

        // Endpoint polling dashboard: error mentah provider disanitasi.
        $task = ProvisioningTask::where('order_id', $order->id)->firstOrFail();
        $task->update([
            'status' => 'failed',
            'error' => '[lighthouse/CreateInstances] TencentCloud API error: InvalidSecretId',
        ]);

        $response = $this->actingAs($customer)
            ->getJson(route('dashboard.vps.provisioning-status', $instance->id));

        $response->assertOk();
        $error = (string) $response->json('task.error');
        $this->assertNotSame('', $error);
        $this->assertNoVendorWords($error);
        $this->assertNoVendorWords((string) $response->getContent());
    }

    // ============ 7. regression — gate OFF / unmapped = manual ============

    public function test_gate_off_keeps_manual_flow_unchanged(): void
    {
        app(SettingsService::class)->set('provisioning_auto_enabled', false);

        $customer = $this->customer();
        $order = $this->pendingOrder($customer);
        $this->fakeTencent($this->happyHandlers());

        $this->markPaid($order);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNull(VpsInstance::where('order_id', $order->id)->first());
        Queue::assertNotPushed(ProvisionVpsJob::class);
        $this->assertSame(0, $this->sentCount('CreateInstances'));
        $this->assertSame(0, $this->pool()->reserved);
        Notification::assertSentTo($customer, PaymentReceivedNotification::class);
    }

    public function test_unmapped_spec_never_auto_provisions_even_when_enabled(): void
    {
        // D3 HOLD: ai_combo tanpa mapping upstream.
        $aiSpec = VpsSpec::create([
            'name' => 'AI Combo Test',
            'category' => 'ai_combo',
            'cpu' => 4, 'ram' => 8, 'disk' => 100, 'bandwidth' => 50,
            'cost_price' => 200000, 'sell_price' => 300000, 'is_active' => true,
        ]);
        app(SettingsService::class)->set('provisioning_auto_spec_whitelist', [$aiSpec->id]);

        $customer = $this->customer('aiuser');
        $order = $this->pendingOrder($customer, ['vps_spec_id' => $aiSpec->id]);
        $this->fakeTencent($this->happyHandlers());

        $this->markPaid($order);

        $this->assertSame('paid', $order->fresh()->status);
        Queue::assertNotPushed(ProvisionVpsJob::class);
        $this->assertSame(0, $this->sentCount('CreateInstances'));
    }

    public function test_manual_provider_still_finalizes_without_upstream_calls(): void
    {
        // Gate OFF → alur manual admin: instance dibuat manual, task manual.
        app(SettingsService::class)->set('provisioning_auto_enabled', false);

        $customer = $this->customer();
        $order = $this->pendingOrder($customer);
        $this->markPaid($order);
        $order->refresh();

        app(OrderStateMachine::class)->transition($order, 'provisioning', [
            'reason' => 'Admin memulai provisioning manual.',
            'actor_type' => 'admin',
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => 'srv-manual',
            'public_ip' => '139.180.200.20',
            'ssh_port' => 22,
            'os' => 'ubuntu2404',
            'status' => 'provisioning',
            'cpu' => 2, 'ram' => 2, 'disk' => 40,
            'control_panel' => 'none',
            'provider' => 'manual',
            'initial_root_password' => 'PasswordAwal#123',
            'billing_cycle' => 'monthly',
            'expires_at' => now()->addMonth(),
        ]);

        $task = ProvisioningTask::create([
            'order_id' => $order->id,
            'vps_instance_id' => $instance->id,
            'kind' => 'provision',
            'status' => 'pending',
            'provider' => 'manual',
            'input' => [],
        ]);

        (new ProvisionVpsJob($task->id))->handle(app(ProviderManager::class));

        $this->assertSame('succeeded', $task->fresh()->status);
        $this->assertSame('running', $instance->fresh()->status);
        $this->assertSame('active', $order->fresh()->status);
        $this->assertSame(0, SupplierPurchase::count()); // manual → no upstream purchase
        $this->assertSame(0, $this->sentCount('CreateInstances'));
        $this->assertSame(0, $this->pool()->reserved); // no reservation in manual flow
        Notification::assertSentTo($customer, VpsProvisionedNotification::class);
    }

    // ============ 8. install failure → failed + quota settlement ============

    public function test_stack_install_failure_marks_failed_and_commits_occupied_slot(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrder($customer, ['control_panel' => 'coolify']);
        $this->fakeTencent($this->happyHandlers());

        $this->markPaid($order);
        $this->runInfraJob($order);

        $installTask = ProvisioningTask::where('order_id', $order->id)->where('kind', 'install_stack')->firstOrFail();
        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();

        $this->fakeTencent($this->happyHandlers([
            'DescribeInvocationTasks' => ['InvocationTaskBasicInfoSet' => [[
                'TaskStatus' => 'FAILED',
                'TaskResult' => base64_encode('apt failed: network unreachable'),
            ]]],
        ]));

        (new InstallStackJob($installTask->id))->handle(app(ProviderManager::class));

        $this->assertSame('failed', $installTask->fresh()->status);
        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame('error', $instance->fresh()->status);

        // Instance upstream SUDAH ada → slot commit (used), bukan release.
        $this->assertSame(0, $this->pool()->reserved);
        $this->assertSame(1, $this->pool()->used);
        Notification::assertSentOnDemand(AdminProvisioningFailedNotification::class);
        Notification::assertNotSentTo($customer, VpsProvisionedNotification::class);
    }

    // ============ 9. Debian/Rocky use root (revision 3) ============

    public function test_debian_order_resets_root_password_not_ubuntu(): void
    {
        $customer = $this->customer('debianuser');
        $order = $this->pendingOrder($customer, ['os' => 'debian12']);
        $this->fakeTencent($this->happyHandlers());

        $this->markPaid($order);
        $task = $this->runInfraJob($order);

        $this->assertSame('succeeded', $task->status);
        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('root', $instance->ssh_username);

        Http::assertSent(function (HttpRequest $r) {
            if (! $r->hasHeader('X-TC-Action', 'ResetInstancesPassword')) {
                return false;
            }
            $body = json_decode($r->body(), true);

            return ($body['UserName'] ?? null) === 'root';
        });

        // Debian tanpa stack docker → blueprint OS biasa, bukan image docker.
        Http::assertSent(function (HttpRequest $r) {
            if (! $r->hasHeader('X-TC-Action', 'CreateInstances')) {
                return false;
            }
            $body = json_decode($r->body(), true);

            return ($body['BlueprintId'] ?? null) === config('services.tencent.blueprints.debian12');
        });
    }

    // ============ 10. review patch A2 — double failed() guard ============

    public function test_worker_failed_callback_never_runs_on_failure_twice(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrder($customer);
        $this->fakeTencent($this->happyHandlers([
            'DescribeAccountBalance' => ['Balance' => 0],
        ]));

        $this->markPaid($order);
        $task = ProvisioningTask::where('order_id', $order->id)->where('kind', 'provision')->firstOrFail();
        $job = new ProvisionVpsJob($task->id);
        $job->handle(app(ProviderManager::class)); // non-retryable → markFailed + onFailure + fail()

        $task->refresh();
        $this->assertSame('failed', $task->status);
        $firstError = $task->error;
        $this->assertStringContainsStringIgnoringCase('insufficient', $firstError);
        Notification::assertSentOnDemandTimes(AdminProvisioningFailedNotification::class, 1);

        // Queue worker invokes failed() AGAIN after fail() — must be a no-op.
        $job->failed(new \RuntimeException('worker exhausted retries'));

        $task->refresh();
        $this->assertSame($firstError, $task->error); // not overwritten
        $this->assertSame('failed', $order->fresh()->status);
        Notification::assertSentOnDemandTimes(AdminProvisioningFailedNotification::class, 1);
        $this->assertSame(0, $this->pool()->reserved);
        $this->assertSame(0, $this->pool()->used);
    }

    // ====== 11. review patch A3a — quota flag on order.metadata ======

    public function test_quota_settled_flag_on_order_metadata_prevents_double_commit(): void
    {
        $customer = $this->customer();
        $order = $this->pendingOrder($customer); // stack none → single stage
        $this->fakeTencent($this->happyHandlers());

        $this->markPaid($order);
        $task = $this->runInfraJob($order);
        $instance = VpsInstance::where('order_id', $order->id)->firstOrFail();

        // Flag lives on the ORDER, not on the (replaceable) task output.
        $order->refresh();
        $this->assertTrue((bool) ($order->metadata['quota_settled'] ?? false));
        $this->assertArrayNotHasKey('quota_settled', $task->output ?? []);
        $this->assertSame(1, $this->pool()->used);

        // A NEW retry task for the same order must NOT commit a second slot.
        $retryTask = ProvisioningTask::create([
            'order_id' => $order->id,
            'vps_instance_id' => $instance->id,
            'kind' => 'provision',
            'status' => 'pending',
            'provider' => 'tencent',
            'input' => [],
        ]);
        app(ProvisioningFinalizer::class)->finalize($retryTask, $instance->fresh(), [
            'resource_id' => self::UPSTREAM_ID,
        ]);
        $this->assertSame(1, $this->pool()->used);
        $this->assertSame(0, $this->pool()->reserved);
        $this->assertSame(1, SupplierPurchase::where('vps_instance_id', $instance->id)->count());

        // Backward-compat: legacy Phase 3 task-level flag is still honored.
        $order->update(['metadata' => array_diff_key($order->metadata, ['quota_settled' => true])]);
        $legacyTask = ProvisioningTask::create([
            'order_id' => $order->id,
            'vps_instance_id' => $instance->id,
            'kind' => 'provision',
            'status' => 'pending',
            'provider' => 'tencent',
            'input' => [],
            'output' => ['quota_settled' => true],
        ]);
        app(ProvisioningFinalizer::class)->finalize($legacyTask, $instance->fresh(), [
            'resource_id' => self::UPSTREAM_ID,
        ]);
        $this->assertSame(1, $this->pool()->used);
    }

    // ====== 12. review patch A3b — unsupported OS/stack stays manual ======

    public function test_unsupported_os_stack_combo_stays_manual_without_false_failure(): void
    {
        // CloudPanel CE v2 tidak mendukung Debian 13 → jangan auto-provision.
        $customer = $this->customer('cpuser');
        $order = $this->pendingOrder($customer, ['control_panel' => 'cloudpanel', 'os' => 'debian13']);
        $this->fakeTencent($this->happyHandlers());

        $this->markPaid($order);

        $this->assertSame('paid', $order->fresh()->status); // bukan failed
        $this->assertNull(VpsInstance::where('order_id', $order->id)->first());
        Queue::assertNotPushed(ProvisionVpsJob::class);
        $this->assertSame(0, $this->pool()->reserved); // tidak menyentuh kuota
        $this->assertSame(0, $this->sentCount('CreateInstances'));

        // Docker (apt repo Ubuntu) di Debian 12 juga harus tetap manual.
        $customer2 = $this->customer('dockdeb');
        $order2 = $this->pendingOrder($customer2, ['control_panel' => 'docker', 'os' => 'debian12']);
        $this->markPaid($order2);
        $this->assertSame('paid', $order2->fresh()->status);
        Queue::assertNotPushed(ProvisionVpsJob::class);
        $this->assertSame(0, $this->pool()->reserved);
    }

    // ============================== utilities ==============================

    /** Count recorded requests for a TC action (optionally filtered by body). */
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

            return false; // never mark as "asserted", just count
        });

        return $count;
    }
}