<?php

namespace Tests\Feature;

use App\Jobs\Provisioning\AbstractProvisioningJob;
use App\Jobs\Provisioning\ProvisionVpsJob;
use App\Jobs\Provisioning\UpgradeBundleJob;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\ProvisioningTask;
use App\Models\Subscription;
use App\Models\UpstreamQuotaPool;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\BillingService;
use App\Services\OrderStateMachine;
use App\Services\Payments\MidtransService;
use App\Services\Provisioning\ProviderManager;
use App\Services\RenewalService;
use App\Services\SubscriptionSyncService;
use App\Services\UpgradeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Wave1HardeningAndBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
    }

    private function customer(string $suffix = 'w1'): User
    {
        $user = User::create([
            'username' => 'cust_' . $suffix . '_' . uniqid(),
            'email' => 'cust_' . $suffix . '_' . uniqid() . '@example.com',
            'full_name' => 'Pelanggan Wave 1',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return $user->fresh();
    }

    private function vpsSpec(string $name = 'Starter XS'): VpsSpec
    {
        return VpsSpec::where('category', 'vps')->where('name', $name)->firstOrFail();
    }

    public function test_self_service_renewal_rejects_non_monthly_billing_cycle(): void
    {
        $customer = $this->customer('cyc');
        $spec = $this->vpsSpec('Starter XS');

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'midtrans_snap',
            'amount' => $spec->sell_price,
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => 'vps-cycle-' . uniqid(),
            'status' => 'running',
            'billing_cycle' => 'monthly',
            'expires_at' => now()->addDays(10),
        ]);

        $this->actingAs($customer)
            ->post(route('dashboard.vps.renew', $instance->id), [
                'billing_cycle' => 'annual',
                'payment_method' => 'midtrans_snap',
            ])
            ->assertSessionHasErrors(['billing_cycle']);

        $this->assertSame(0, Order::where('vps_instance_id', $instance->id)->where('order_type', 'renewal')->count());
    }

    public function test_pending_renewal_reuse_preserves_matching_order_and_replaces_when_price_changes(): void
    {
        $customer = $this->customer('reuse');
        $spec = $this->vpsSpec('Starter XS');

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'midtrans_snap',
            'amount' => $spec->sell_price,
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => 'vps-reuse-' . uniqid(),
            'status' => 'running',
            'billing_cycle' => 'monthly',
            'expires_at' => now()->addDays(10),
        ]);

        // First request creates renewal order 1
        $res1 = $this->actingAs($customer)->post(route('dashboard.vps.renew', $instance->id), [
            'payment_method' => 'midtrans_snap',
        ]);
        $firstRenewal = Order::where('vps_instance_id', $instance->id)->where('order_type', 'renewal')->firstOrFail();
        $res1->assertRedirect(route('order.payment', $firstRenewal->id));

        // Identical request reuses existing order
        $res2 = $this->actingAs($customer)->post(route('dashboard.vps.renew', $instance->id), [
            'payment_method' => 'midtrans_snap',
        ]);
        $res2->assertRedirect(route('order.payment', $firstRenewal->id));
        $this->assertSame(1, Order::where('vps_instance_id', $instance->id)->where('order_type', 'renewal')->count());

        // Price override changes amount -> cancels old order & creates fresh one cleanly
        $instance->update(['custom_renewal_price' => 95000]);
        $res3 = $this->actingAs($customer)->post(route('dashboard.vps.renew', $instance->id), [
            'payment_method' => 'midtrans_snap',
        ]);

        $this->assertSame('cancelled', $firstRenewal->fresh()->status);
        $this->assertSame('cancelled', $firstRenewal->invoice->fresh()->status);

        $secondRenewal = Order::where('vps_instance_id', $instance->id)
            ->where('order_type', 'renewal')
            ->where('status', 'pending')
            ->firstOrFail();
        $this->assertNotSame($firstRenewal->id, $secondRenewal->id);
        $this->assertEquals(95000.0, (float) $secondRenewal->amount);
        $res3->assertRedirect(route('order.payment', $secondRenewal->id));
    }

    public function test_deduct_credit_guards_against_double_spend(): void
    {
        $customer = $this->customer('cred');
        $billing = app(BillingService::class);
        $billing->addCredit($customer, 50000, 'Deposit awal');

        $tx1 = $billing->deductCredit($customer, 40000, 'Pemakaian pertama');
        $this->assertEquals(10000.0, (float) $tx1->balance_after);

        $this->expectException(\RuntimeException::class);
        $billing->deductCredit($customer, 40000, 'Pemakaian ganda');
    }

    public function test_guest_checkout_respects_2fa_enabled_on_existing_account(): void
    {
        $customer = $this->customer('2fa');
        $customer->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $spec = $this->vpsSpec('Starter XS');

        $response = $this->postJson('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'srv-2fa-check',
            'payment_method' => 'online_payment',
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'password' => 'password123',
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'requires_2fa' => true,
            ]);

        $this->assertGuest();
        $this->assertSame($customer->id, session('2fa.pending_user_id'));
        $this->assertFalse((bool) session('2fa.passed', false));
    }

    public function test_midtrans_sync_blocks_amount_mismatch_and_closed_order_bypass(): void
    {
        $customer = $this->customer('mid');
        $spec = $this->vpsSpec('Starter XS');

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'midtrans_snap',
            'amount' => 115000,
        ]);

        $okMismatch = MidtransService::settleOrderFromMidtrans($order, [
            'transaction_id' => 'tx-mismatch-1',
            'gross_amount' => '10000.00',
            'payment_type' => 'qris',
        ], 'CRV-' . $order->id);

        $this->assertFalse($okMismatch);
        $this->assertSame('pending', $order->fresh()->status);

        // Cancelled order must not be forced into 'paid' via state machine bypass.
        app(OrderStateMachine::class)->transition($order, 'cancelled', ['reason' => 'Kadaluarsa', 'actor_type' => 'system']);
        $okClosed = MidtransService::settleOrderFromMidtrans($order->fresh(), [
            'transaction_id' => 'tx-late-1',
            'gross_amount' => '115000.00',
            'payment_type' => 'qris',
        ], 'CRV-' . $order->id);

        $this->assertFalse($okClosed);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'provider_transaction_id' => 'tx-late-1',
            'status' => 'settled',
        ]);
    }

    public function test_webhook_rejects_arbitrary_numeric_match_outside_strict_prefixes(): void
    {
        $customer = $this->customer('wbh');
        $spec = $this->vpsSpec('Starter XS');

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'online_payment',
            'amount' => 115000,
        ]);

        $serverKey = 'Mid-server-strict-key';
        PaymentGateway::where('code', 'midtrans')->first()->update([
            'is_active' => true,
            'mode' => 'production',
            'credentials' => ['server_key' => $serverKey, 'client_key' => 'Mid-client'],
        ]);

        $rawOrderId = "EXTERNAL-PING-{$order->id}";
        $signature = hash('sha512', $rawOrderId . '200' . '115000.00' . $serverKey);

        $this->postJson('/api/webhooks/payment', [
            'order_id' => $rawOrderId,
            'status_code' => '200',
            'gross_amount' => '115000.00',
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'transaction_id' => 'tx-ext-ping-1',
        ])->assertStatus(200);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_subscription_sync_auto_renew_idempotency_and_expire_sent_invoice(): void
    {
        $customer = $this->customer('sub');
        $spec = $this->vpsSpec('Starter XS');

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'midtrans_snap',
            'amount' => $spec->sell_price,
            'created_at' => now()->subDays(2),
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => 'vps-sync-' . uniqid(),
            'status' => 'running',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subHour(),
            'auto_renew' => true,
        ]);

        Artisan::call('billing:backfill-subscriptions');
        $sub = Subscription::where('vps_instance_id', $instance->id)->first();
        $this->assertNotNull($sub);
        $this->assertSame('monthly', $sub->billing_cycle);

        // Renew subscription creates genuine renewal order + idempotent anti-spam check
        $billing = app(BillingService::class);
        $inv1 = $billing->renewSubscription($sub);
        $this->assertNotNull($inv1);
        $this->assertSame('renewal', $inv1->order->order_type);
        $this->assertSame($instance->id, $inv1->order->vps_instance_id);
        $this->assertSame('midtrans_snap', $inv1->order->payment_method);

        $inv2 = $billing->renewSubscription($sub->fresh());
        $this->assertNull($inv2);

        // Payment of renewal updates subscription period
        app(OrderStateMachine::class)->transition($inv1->order, 'paid', ['actor_type' => 'system', 'onLocked' => fn ($o) => $o->update(['paid_at' => now()])]);
        app(RenewalService::class)->handleRenewalPayment($inv1->order->fresh());
        $this->assertSame('active', $sub->fresh()->status);
        $this->assertTrue($sub->fresh()->next_billing_at->isFuture());

        // Pending order older than 24h expires invoice with status 'sent'
        $staleOrder = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'midtrans_snap',
            'amount' => $spec->sell_price,
        ]);
        Order::whereKey($staleOrder->id)->update(['created_at' => now()->subHours(26)]);
        $staleInvoice = Invoice::create([
            'order_id' => $staleOrder->id,
            'organization_id' => $customer->current_organization_id,
            'invoice_number' => 'INV-SENT-EXPIRE-1',
            'amount' => $spec->sell_price,
            'subtotal' => $spec->sell_price,
            'status' => 'sent',
            'issued_at' => now()->subHours(26),
            'due_at' => now()->subHours(2),
        ]);

        Artisan::call('orders:expire-pending');
        $this->assertSame('expired', $staleOrder->fresh()->status);
        $this->assertSame('cancelled', $staleInvoice->fresh()->status);
    }

    public function test_upgrade_guards_and_queue_overlap_configuration(): void
    {
        $this->assertSame(720, config('queue.connections.database.retry_after'));
        $this->assertSame(720, config('queue.connections.redis.retry_after'));

        $job = new ProvisionVpsJob(999);
        $middlewares = $job->middleware();
        $this->assertNotEmpty($middlewares);
        $this->assertInstanceOf(WithoutOverlapping::class, $middlewares[0]);

        $customer = $this->customer('upg');
        $xs = $this->vpsSpec('Starter XS');
        $pro = $this->vpsSpec('Enterprise Pro'); // GENERAL_BUNDLE vs STARTER_BUNDLE

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $xs->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'midtrans_snap',
            'amount' => $xs->sell_price,
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => 'srv-upg-g1',
            'provider' => 'tencent',
            'provider_resource_id' => 'lhins-upg-g1',
            'control_panel' => 'none',
            'os' => 'ubuntu2404',
            'datacenter_location' => 'indonesia',
            'cpu' => $xs->cpu,
            'ram' => $xs->ram,
            'disk' => $xs->disk,
            'status' => 'running',
            'billing_cycle' => 'monthly',
            'expires_at' => now()->addHours(6), // < 1 day left
        ]);

        $upgradeService = app(UpgradeService::class);
        $endingPreview = $upgradeService->preview($instance, $pro);
        $this->assertFalse($endingPreview['ok']);
        $this->assertSame('cycle_ending', $endingPreview['code']);

        // Extend expiry, test cross-pool exhaustion
        $instance->update(['expires_at' => now()->addDays(20)]);
        $genPool = UpstreamQuotaPool::where('region_code', 'ap-jakarta')->where('bundle_type', 'GENERAL_BUNDLE')->firstOrFail();
        $genPool->update(['used' => $genPool->capacity]);

        $exhausted = $upgradeService->preview($instance->fresh(), $pro);
        $this->assertFalse($exhausted['ok']);
        $this->assertSame('quota_exhausted', $exhausted['code']);

        // Free slot in pool & check preview OK, then test BalanceGuard failure on low balance
        $genPool->update(['used' => 0]);
        $starterPool = UpstreamQuotaPool::where('region_code', 'ap-jakarta')->where('bundle_type', 'STARTER_BUNDLE')->firstOrFail();
        $starterPool->update(['used' => 1]);

        Queue::fake();
        config(['services.tencent.secret_id' => 'id', 'services.tencent.secret_key' => 'key']);
        Http::fake(fn () => Http::response(['Response' => ['Balance' => 50, 'RequestId' => 'req-low']])); // $0.50

        $created = $upgradeService->createUpgradeOrder($instance->fresh(), $pro);
        $this->assertNotNull($created['order']);

        // Duplicate pending upgrade rejected
        $dup = $upgradeService->preview($instance->fresh(), $pro);
        $this->assertFalse($dup['ok']);
        $this->assertSame('upgrade_in_progress', $dup['code']);

        $task = ProvisioningTask::create([
            'order_id' => $created['order']->id,
            'vps_instance_id' => $instance->id,
            'kind' => 'upgrade',
            'status' => 'pending',
            'provider' => 'tencent',
            'input' => ['target_spec_id' => $pro->id, 'from_spec_id' => $xs->id],
        ]);

        (new UpgradeBundleJob($task->id))->handle(app(ProviderManager::class));
        $this->assertSame('failed', $task->fresh()->status);
        $this->assertStringContainsString('balance insufficient', strtolower($task->fresh()->error));
    }
}
