<?php

namespace Tests\Feature;

use App\Models\DatacenterRegion;
use App\Models\Order;
use App\Models\UpstreamQuotaPool;
use App\Models\User;
use App\Models\VpsSpec;
use App\Services\AdminAlertService;
use App\Services\Provisioning\Exceptions\PendingOrderLimitException;
use App\Services\Provisioning\Exceptions\QuotaExhaustedException;
use App\Services\SettingsService;
use App\Services\UpstreamQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpstreamQuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function spec(array $overrides = []): VpsSpec
    {
        return VpsSpec::create(array_merge([
            'name' => 'Starter XS',
            'category' => 'vps',
            'cpu' => 2,
            'ram' => 2,
            'disk' => 40,
            'bandwidth' => 20,
            'cost_price' => 85000,
            'sell_price' => 115000,
            'is_active' => true,
            'upstream_bundle_id' => 'bundle_starter_nmc_lin_med2_01',
            'upstream_bundle_type' => 'STARTER_BUNDLE',
        ], $overrides));
    }

    private function pool(string $region = 'ap-jakarta', string $type = 'STARTER_BUNDLE', int $capacity = 20, int $reserved = 0, int $used = 0): UpstreamQuotaPool
    {
        // Migration 100003 already seeds ap-jakarta pools — upsert, don't duplicate.
        return UpstreamQuotaPool::updateOrCreate(
            ['region_code' => $region, 'bundle_type' => $type],
            ['capacity' => $capacity, 'reserved' => $reserved, 'used' => $used],
        );
    }

    
    private function readPool(string $region = 'ap-jakarta', string $type = 'STARTER_BUNDLE'): UpstreamQuotaPool
    {
        return UpstreamQuotaPool::where('region_code', $region)->where('bundle_type', $type)->firstOrFail();
    }

    private function user(): User
    {
        return User::create([
            'full_name' => 'Pelanggan Kuota',
            'username' => 'kuota' . uniqid(),
            'email' => 'kuota' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
    }

    private function pendingOrder(User $user, VpsSpec $spec, string $orderType = 'new'): Order
    {
        return Order::create([
            'customer_id' => $user->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'order_type' => $orderType,
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 115000,
            'hostname' => 'cv-kuota-' . uniqid(),
        ]);
    }

    private function service(): UpstreamQuotaService
    {
        return app(UpstreamQuotaService::class);
    }

    // ===================== reserveHard atomicity =====================

    public function test_reserve_hard_succeeds_once_when_one_slot_left(): void
    {
        $this->pool(capacity: 1);

        $this->assertTrue($this->service()->reserveHard('ap-jakarta', 'STARTER_BUNDLE'));
        $this->assertFalse($this->service()->reserveHard('ap-jakarta', 'STARTER_BUNDLE'));

        $this->assertSame(1, $this->readPool()->reserved);
    }

    public function test_reserve_hard_counts_used_slots(): void
    {
        $this->pool(capacity: 2, used: 2);

        $this->assertFalse($this->service()->reserveHard('ap-jakarta', 'STARTER_BUNDLE'));
    }

    public function test_reserve_hard_returns_false_for_missing_pool(): void
    {
        $this->assertFalse($this->service()->reserveHard('ap-tokyo', 'STARTER_BUNDLE'));
    }

    public function test_commit_moves_reserved_to_used(): void
    {
        $this->pool(capacity: 5, reserved: 2, used: 1);

        $this->service()->commit('ap-jakarta', 'STARTER_BUNDLE');

        $pool = $this->readPool();
        $this->assertSame(1, $pool->reserved);
        $this->assertSame(2, $pool->used);
    }

    public function test_release_reserved_floors_at_zero(): void
    {
        $this->pool(reserved: 0);

        $this->service()->releaseReserved('ap-jakarta', 'STARTER_BUNDLE');

        $this->assertSame(0, $this->readPool()->reserved);
    }

    public function test_release_used_frees_slot_and_floors_at_zero(): void
    {
        $this->pool(used: 1);

        $this->service()->releaseUsed('ap-jakarta', 'STARTER_BUNDLE');
        $this->service()->releaseUsed('ap-jakarta', 'STARTER_BUNDLE');

        $this->assertSame(0, $this->readPool()->used);
    }

    // ===================== assertAvailable & alternatives (D4) =====================

    public function test_assert_available_throws_when_full_with_alternatives(): void
    {
        $this->pool(capacity: 1, used: 1);
        DatacenterRegion::create([
            'code' => 'ap-singapore', 'name' => 'Singapore', 'country' => 'SG',
            'provider' => 'tencent', 'provider_region_id' => 'ap-singapore', 'is_active' => true,
        ]);
        $this->pool('ap-singapore', 'STARTER_BUNDLE', 20);

        try {
            $this->service()->assertAvailable('ap-jakarta', 'STARTER_BUNDLE');
            $this->fail('QuotaExhaustedException expected');
        } catch (QuotaExhaustedException $e) {
            $this->assertSame('ap-jakarta', $e->regionCode);
            $this->assertCount(1, $e->alternatives);
            $this->assertSame('ap-singapore', $e->alternatives[0]['region_code']);
            // White-label: customer message must not leak vendor names.
            $this->assertStringNotContainsStringIgnoringCase('tencent', $e->customerMessage());
            $this->assertStringNotContainsStringIgnoringCase('lighthouse', $e->customerMessage());
            $this->assertStringContainsString('Singapore', $e->customerMessage());
        }
    }

    public function test_assert_available_passes_with_free_slot(): void
    {
        $this->pool(capacity: 20, reserved: 10, used: 9);

        $this->service()->assertAvailable('ap-jakarta', 'STARTER_BUNDLE');
        $this->assertTrue(true); // no exception
    }

    // ===================== pending limit (revision 1 anti-DoS) =====================

    public function test_pending_limit_blocks_third_pending_order(): void
    {
        $user = $this->user();
        $spec = $this->spec();
        $this->pendingOrder($user, $spec);
        $this->pendingOrder($user, $spec);

        $this->expectException(PendingOrderLimitException::class);
        $this->service()->assertPendingLimit($user->id);
    }

    public function test_renewal_orders_do_not_count_toward_pending_limit(): void
    {
        $user = $this->user();
        $spec = $this->spec();
        $this->pendingOrder($user, $spec, 'renewal');
        $this->pendingOrder($user, $spec, 'renewal');

        $this->service()->assertPendingLimit($user->id);
        $this->assertTrue(true);
    }

    // ===================== guardCheckout modes =====================

    public function test_observe_mode_never_rejects_even_when_full(): void
    {
        $this->pool(capacity: 1, used: 1);
        $user = $this->user();
        $spec = $this->spec();
        $this->pendingOrder($user, $spec);
        $this->pendingOrder($user, $spec);

        // Default mode is observe — both problems present, no exception.
        $this->service()->guardCheckout($spec, 'indonesia', $user->id);
        $this->assertTrue(true);
    }

    public function test_enforce_mode_rejects_full_pool(): void
    {
        app(SettingsService::class)->set('quota_guard_mode', 'enforce');
        $this->pool(capacity: 1, used: 1);

        $this->expectException(QuotaExhaustedException::class);
        $this->service()->guardCheckout($this->spec(), 'indonesia', $this->user()->id);
    }

    public function test_enforce_mode_rejects_pending_limit(): void
    {
        app(SettingsService::class)->set('quota_guard_mode', 'enforce');
        $this->pool();
        $user = $this->user();
        $spec = $this->spec();
        $this->pendingOrder($user, $spec);
        $this->pendingOrder($user, $spec);

        $this->expectException(PendingOrderLimitException::class);
        $this->service()->guardCheckout($spec, 'indonesia', $user->id);
    }

    public function test_unmapped_spec_bypasses_guard_even_in_enforce(): void
    {
        app(SettingsService::class)->set('quota_guard_mode', 'enforce');
        $this->pool(capacity: 1, used: 1);
        $spec = $this->spec([
            'name' => 'DB Micro',
            'category' => 'managed_db',
            'upstream_bundle_id' => null,
            'upstream_bundle_type' => null,
        ]);

        $this->service()->guardCheckout($spec, 'indonesia', $this->user()->id);
        $this->assertTrue(true); // D3 HOLD: manual flow, no guard
    }

    public function test_marketplace_channel_never_rejects_even_in_enforce(): void
    {
        app(SettingsService::class)->set('quota_guard_mode', 'enforce');
        $this->pool(capacity: 1, used: 1);

        $this->service()->guardCheckout($this->spec(), 'indonesia', $this->user()->id, canReject: false);
        $this->assertTrue(true);
    }

    // ===================== syncFromProvider =====================

    public function test_sync_corrects_used_from_real_instances_and_alerts_drift_once(): void
    {
        Http::fake(['*' => Http::response(['Response' => [
            'TotalCount' => 3,
            'InstanceSet' => [
                ['InstanceId' => 'lhins-1', 'InstanceState' => 'RUNNING', 'BundleId' => 'bundle_starter_nmc_lin_med2_01'],
                ['InstanceId' => 'lhins-2', 'InstanceState' => 'STOPPED', 'BundleId' => 'bundle_starter_nmc_lin_med4_01'],
                ['InstanceId' => 'lhins-3', 'InstanceState' => 'TERMINATED', 'BundleId' => 'bundle_starter_nmc_lin_med2_01'],
            ],
            'RequestId' => 'r-sync',
        ]])]);

        $alerts = $this->mock(AdminAlertService::class);
        $alerts->shouldReceive('digest')->once()->andReturnTrue();

        $this->pool(used: 5); // drift: DB says 5, reality 2 (TERMINATED excluded)

        $summary = $this->service()->syncFromProvider('ap-jakarta');

        $this->assertSame(2, $this->readPool()->used);
        $this->assertTrue($summary[0]['drift']);

        // Second sync same day: no drift, no duplicate alert.
        $summary2 = $this->service()->syncFromProvider('ap-jakarta');
        $this->assertFalse($summary2[0]['drift']);
    }

    public function test_sync_counts_shutdown_instances_as_used(): void
    {
        Http::fake(['*' => Http::response(['Response' => [
            'TotalCount' => 1,
            'InstanceSet' => [
                ['InstanceId' => 'lhins-9', 'InstanceState' => 'SHUTDOWN', 'BundleId' => 'bundle_gen_nmc_lin_lg8_01'],
            ],
        ]])]);

        $this->mock(AdminAlertService::class)->shouldReceive('digest')->andReturnTrue();
        $this->pool('ap-jakarta', 'GENERAL_BUNDLE', 20);

        $this->service()->syncFromProvider('ap-jakarta');

        $this->assertSame(1, $this->readPool('ap-jakarta', 'GENERAL_BUNDLE')->used);
    }

    // ===================== early warning (80%) =====================

    public function test_high_usage_alert_fires_once_per_day(): void
    {
        $alerts = $this->mock(AdminAlertService::class);
        $alerts->shouldReceive('digest')->once()->andReturnTrue();

        $this->pool(capacity: 20, used: 15);

        // 16/20 = 80% → alert.
        $this->service()->reserveHard('ap-jakarta', 'STARTER_BUNDLE');
        // Same day again → deduped, no second alert.
        $this->service()->releaseReserved('ap-jakarta', 'STARTER_BUNDLE');
        $this->service()->reserveHard('ap-jakarta', 'STARTER_BUNDLE');
    }

    public function test_low_usage_does_not_alert(): void
    {
        $alerts = $this->mock(AdminAlertService::class);
        $alerts->shouldNotReceive('digest');

        $this->pool(capacity: 20, used: 1);
        $this->service()->reserveHard('ap-jakarta', 'STARTER_BUNDLE');
    }
}