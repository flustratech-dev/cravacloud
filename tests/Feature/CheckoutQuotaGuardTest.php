<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\UpstreamQuotaPool;
use App\Models\User;
use App\Models\VpsSpec;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP-level checkout guard (Phase 2): observe mode must not change existing
 * behavior; enforce mode rejects with a neutral white-label message.
 */
class CheckoutQuotaGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function customer(): User
    {
        return User::create([
            'full_name' => 'Pelanggan Guard',
            'username' => 'guard' . uniqid(),
            'email' => 'guard' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        $spec = VpsSpec::where('is_active', true)->where('category', 'vps')->where('name', 'Starter XS')->first();

        return array_merge([
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'hostname' => 'cv-guard-' . uniqid(),
            'root_password' => 'RahasiaKuat123',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
        ], $overrides);
    }

    private function fillJakartaStarterPool(): void
    {
        UpstreamQuotaPool::where('region_code', 'ap-jakarta')
            ->where('bundle_type', 'STARTER_BUNDLE')
            ->update(['used' => 20]);
    }

    public function test_seeded_catalog_maps_eight_vps_specs_and_holds_others(): void
    {
        $this->assertSame(8, VpsSpec::whereNotNull('upstream_bundle_id')->count());
        $this->assertSame(0, VpsSpec::whereIn('category', ['ai_combo', 'managed_db'])->whereNotNull('upstream_bundle_id')->count());
    }

    public function test_observe_mode_full_pool_still_creates_order(): void
    {
        $this->fillJakartaStarterPool();
        $customer = $this->customer();

        $this->actingAs($customer)->postJson('/checkout', $this->payload());

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);
    }

    public function test_enforce_mode_full_pool_rejects_checkout_with_neutral_message(): void
    {
        app(SettingsService::class)->set('quota_guard_mode', 'enforce');
        $this->fillJakartaStarterPool();
        $customer = $this->customer();

        $response = $this->actingAs($customer)->postJson('/checkout', $this->payload());

        $response->assertStatus(422);
        $message = $response->json('message');
        $this->assertStringContainsString('penuh', strtolower($message));
        $this->assertStringNotContainsStringIgnoringCase('tencent', $message);
        $this->assertStringNotContainsStringIgnoringCase('lighthouse', $message);
        $this->assertSame(0, Order::where('customer_id', $customer->id)->count());
    }

    public function test_enforce_mode_offers_alternative_region_when_available(): void
    {
        app(SettingsService::class)->set('quota_guard_mode', 'enforce');
        $this->fillJakartaStarterPool();
        // Activate the fallback region (D4: explicit choice, not auto-fallback).
        \App\Models\DatacenterRegion::where('code', 'ap-singapore')->update(['is_active' => true]);

        $response = $this->actingAs($this->customer())->postJson('/checkout', $this->payload());

        $response->assertStatus(422);
        $this->assertStringContainsString('Singapore', $response->json('message'));
    }

    public function test_enforce_mode_blocks_third_pending_order(): void
    {
        app(SettingsService::class)->set('quota_guard_mode', 'enforce');
        $customer = $this->customer();

        $this->actingAs($customer)->postJson('/checkout', $this->payload());
        $this->actingAs($customer)->postJson('/checkout', $this->payload());
        $response = $this->actingAs($customer)->postJson('/checkout', $this->payload());

        $response->assertStatus(422);
        $this->assertSame(2, Order::where('customer_id', $customer->id)->where('status', 'pending')->count());
    }

    public function test_checkout_links_order_to_datacenter_region(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->postJson('/checkout', $this->payload());

        $order = Order::where('customer_id', $customer->id)->first();
        $this->assertNotNull($order->datacenter_region_id);
        $this->assertSame('ap-jakarta', $order->datacenterRegion->code);
    }
}