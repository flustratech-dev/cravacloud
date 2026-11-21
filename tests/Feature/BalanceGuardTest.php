<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsSpec;
use App\Services\AdminAlertService;
use App\Services\Provisioning\Exceptions\ProviderException;
use App\Services\Provisioning\Tencent\BalanceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BalanceGuardTest extends TestCase
{
    use RefreshDatabase;

    private function fakeBalance(int|float $raw): void
    {
        Http::fake(['*' => Http::response(['Response' => [
            'Balance' => $raw,
            'RealBalance' => $raw,
            'Uin' => 200055228512,
            'RequestId' => 'r-bal',
        ]])]);
    }

    public function test_balance_usd_applies_divisor_and_caches(): void
    {
        config(['services.tencent.balance_divisor' => 100]);
        $this->fakeBalance(2500); // $25.00

        $guard = app(BalanceGuard::class);

        $this->assertSame(25.0, $guard->balanceUsd());
        $this->assertSame(25.0, $guard->balanceUsd());

        Http::assertSentCount(1); // second call served from cache
    }

    public function test_fresh_bypasses_cache(): void
    {
        config(['services.tencent.balance_divisor' => 100]);
        $this->fakeBalance(1000);

        $guard = app(BalanceGuard::class);
        $guard->balanceUsd();
        $guard->balanceUsd(fresh: true);

        Http::assertSentCount(2);
    }

    public function test_assert_sufficient_throws_non_retryable_when_short(): void
    {
        config(['services.tencent.balance_divisor' => 100]);
        $this->fakeBalance(300); // $3.00 < $4.20 HPP Starter XS

        try {
            app(BalanceGuard::class)->assertSufficient(4.20, fresh: true);
            $this->fail('ProviderException expected');
        } catch (ProviderException $e) {
            $this->assertFalse($e->retryable);
            $this->assertSame('InsufficientBalance', $e->providerCode);
            // Customer payment must never be silently refunded; message is
            // admin-facing and vendor-neutral wording is not required here,
            // but it must not contain credentials.
            $this->assertStringContainsString('insufficient', strtolower($e->getMessage()));
        }
    }

    public function test_assert_sufficient_passes_when_covered(): void
    {
        config(['services.tencent.balance_divisor' => 100]);
        $this->fakeBalance(5000); // $50

        app(BalanceGuard::class)->assertSufficient(4.20, fresh: true);
        $this->assertTrue(true);
    }

    // ===================== check-balance command =====================

    public function test_command_alerts_below_threshold_once_then_dedupes(): void
    {
        config(['services.tencent.balance_divisor' => 100, 'services.tencent.low_balance_usd' => 20]);
        $this->fakeBalance(500); // $5 < $20

        $alerts = $this->mock(AdminAlertService::class);
        $alerts->shouldReceive('alert')->once()->andReturn(['email' => true, 'whatsapp' => true, 'skipped_dedupe' => false]);

        $this->artisan('tencent:check-balance')->assertSuccessful();
        $this->artisan('tencent:check-balance')->assertSuccessful(); // deduped
    }

    public function test_command_escalates_when_balance_drops_further(): void
    {
        config(['services.tencent.balance_divisor' => 100, 'services.tencent.low_balance_usd' => 20]);

        $alerts = $this->mock(AdminAlertService::class);
        $alerts->shouldReceive('alert')->twice()->andReturn(['email' => true, 'whatsapp' => true, 'skipped_dedupe' => false]);

        // Sequential responses: $10 first, then $3 (worse than last alerted).
        Http::fake(['*' => Http::sequence()
            ->push(['Response' => ['Balance' => 1000, 'RequestId' => 'r1']])
            ->push(['Response' => ['Balance' => 300, 'RequestId' => 'r2']])]);

        $this->artisan('tencent:check-balance')->assertSuccessful();
        $this->artisan('tencent:check-balance')->assertSuccessful();
    }

    public function test_command_no_alert_when_healthy(): void
    {
        config(['services.tencent.balance_divisor' => 100, 'services.tencent.low_balance_usd' => 20]);
        $this->fakeBalance(10000); // $100

        $alerts = $this->mock(AdminAlertService::class);
        $alerts->shouldNotReceive('alert');

        $this->artisan('tencent:check-balance')->assertSuccessful();
    }

    public function test_command_alerts_when_below_pending_order_hpp(): void
    {
        config(['services.tencent.balance_divisor' => 100, 'services.tencent.low_balance_usd' => 20]);
        $this->fakeBalance(2500); // $25 — above $20 threshold...

        $spec = VpsSpec::create([
            'name' => 'Enterprise Extreme', 'category' => 'vps',
            'cpu' => 16, 'ram' => 32, 'disk' => 400, 'bandwidth' => 60,
            'cost_price' => 2000000, 'sell_price' => 2850000, 'is_active' => true,
            'upstream_bundle_id' => 'bundle_gen_nmc_lin_2xlg32_01', // HPP $105
            'upstream_bundle_type' => 'GENERAL_BUNDLE',
        ]);
        $user = User::create([
            'full_name' => 'Pelanggan HPP', 'username' => 'hpp' . uniqid(),
            'email' => 'hpp' . uniqid() . '@test.id', 'password' => bcrypt('password123'), 'is_admin' => false,
        ]);
        Order::create([
            'customer_id' => $user->id, 'vps_spec_id' => $spec->id,
            'control_panel' => 'none', 'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404', 'status' => 'pending', 'channel' => 'website',
            'payment_method' => 'qris', 'amount' => 2850000, 'hostname' => 'cv-hpp-' . uniqid(),
        ]);

        // ...but below the $105 HPP of the pending Extreme order.
        $alerts = $this->mock(AdminAlertService::class);
        $alerts->shouldReceive('alert')->once()->andReturn(['email' => true, 'whatsapp' => true, 'skipped_dedupe' => false]);

        $this->artisan('tencent:check-balance')->assertSuccessful();
    }
}