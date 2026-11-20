<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\UpstreamQuotaPool;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 5F — LOCAL-ONLY demo data for the Rp0 browser preview of the Day-2
 * self-service UI. Creates one running auto-provisioned (tencent) VPS with
 * Coolify, a sample firewall rule, demo traffic/console data and quota-pool
 * usage so the customer "Kontrol Server" tab and the admin monitoring widgets
 * render fully offline (no upstream API call).
 *
 * SAFETY:
 *  - Runs ONLY in the local environment; refuses to run elsewhere.
 *  - NOT registered in DatabaseSeeder — invoke explicitly:
 *      php artisan db:seed --class=DemoTencentUiSeeder
 *  - Idempotent: re-running reuses the demo customer/instance and refreshes
 *    the demo cache keys.
 *  - The demo cache keys (tencent:demo:*) are read ONLY by the provider when
 *    APP_ENV=local, so production and the test suite are never affected.
 *  - No real credentials are used or stored; the demo password is a local
 *    placeholder and the VNC "url" is a neutral local placeholder.
 */
class DemoTencentUiSeeder extends Seeder
{
    private const DEMO_USERNAME = 'demo-day2';
    private const UPSTREAM_ID = 'lhins-demo01';
    private const PUBLIC_IP = '43.157.99.10';

    public function run(): void
    {
        if (!app()->environment('local')) {
            $this->command?->warn('DemoTencentUiSeeder hanya berjalan di environment local. Dilewati.');

            return;
        }

        $spec = VpsSpec::where('category', 'vps')->where('name', 'Starter XS')->firstOrFail();

        $customer = User::firstOrNew(['username' => self::DEMO_USERNAME]);
        if (!$customer->exists) {
            $customer->fill([
                'email' => self::DEMO_USERNAME.'@example.com',
                'full_name' => 'Demo Day-2 CravaCloud',
                'password' => Hash::make('password123'),
                'channel' => 'website',
                'is_admin' => false,
            ])->save();
            $org = $customer->createPersonalOrganization();
            $customer->switchToOrganization($org);
            $customer = $customer->fresh();
        }
        $orgId = (int) $customer->current_organization_id;

        $order = Order::firstOrNew(['hostname' => 'srv-demo-day2', 'customer_id' => $customer->id]);
        if (!$order->exists) {
            $order->fill([
                'organization_id' => $orgId,
                'vps_spec_id' => $spec->id,
                'control_panel' => 'coolify',
                'datacenter_location' => 'indonesia',
                'os' => 'ubuntu2404',
                'billing_cycle' => 'monthly',
                'order_type' => 'new',
                'status' => 'active',
                'channel' => 'website',
                'payment_method' => 'qris',
                'amount' => (float) $spec->sell_price,
                'currency' => 'IDR',
                'paid_at' => now()->subDays(2),
            ])->save();
        }

        $instance = VpsInstance::firstOrNew(['provider_resource_id' => self::UPSTREAM_ID]);
        $instance->fill([
            'customer_id' => $customer->id,
            'organization_id' => $orgId,
            'order_id' => $order->id,
            'hostname' => 'srv-demo-day2',
            'public_ip' => self::PUBLIC_IP,
            'private_ip' => '10.0.0.2',
            'ssh_port' => 22,
            'ssh_username' => 'ubuntu',
            'os' => 'ubuntu2404',
            'datacenter_location' => 'indonesia',
            'status' => 'running',
            'cpu' => (int) $spec->cpu,
            'ram' => (int) $spec->ram,
            'disk' => (int) $spec->disk,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'provider_resource_id' => self::UPSTREAM_ID,
            'provider_meta' => ['bundle_id' => $spec->upstream_bundle_id, 'demo' => true],
            'app_url' => 'http://'.self::PUBLIC_IP.':8000',
            'app_name' => 'Coolify',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->addDays(28)->endOfDay(),
            'auto_renew' => true,
        ])->save();

        Subscription::firstOrCreate(
            ['vps_instance_id' => $instance->id],
            [
                'customer_id' => $customer->id,
                'organization_id' => $orgId,
                'vps_spec_id' => $spec->id,
                'order_id' => $order->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'unit_amount' => (float) $spec->sell_price,
                'currency' => 'IDR',
                'auto_renew' => true,
                'current_period_start' => now()->subDays(2),
                'current_period_end' => $instance->expires_at,
                'next_billing_at' => $instance->expires_at,
            ]
        );

        // Sample firewall rules (SSH + Coolify UI + one custom app port).
        Cache::forever('tencent:demo:firewall:'.$instance->id, [
            ['Protocol' => 'TCP', 'Port' => '22', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'crava-ssh'],
            ['Protocol' => 'TCP', 'Port' => '8000', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'crava-coolify'],
            ['Protocol' => 'TCP', 'Port' => '8080', 'CidrBlock' => '0.0.0.0/0', 'Action' => 'ACCEPT', 'FirewallRuleDescription' => 'crava-custom'],
        ]);

        // Demo traffic quota (≈37% of a 1 TB monthly package).
        $total = 1099511627776;
        $used = (int) round($total * 0.37);
        Cache::forever('tencent:demo:traffic:'.$instance->id, [
            'used_bytes' => $used,
            'total_bytes' => $total,
            'reset_at' => now()->addDays(28)->endOfDay()->toIso8601String(),
            'percent' => round($used / $total * 100, 2),
        ]);

        // Neutral local placeholder console URL (single-use flow demo only).
        Cache::forever('tencent:demo:vnc:'.$instance->id, [
            'url' => url('/demo-console-placeholder'),
            'expires_in' => 15,
        ]);

        // Quota-pool usage for the admin monitoring widget (demo display values).
        foreach ([['ap-jakarta', 'STARTER_BUNDLE', 6, 4], ['ap-jakarta', 'GENERAL_BUNDLE', 2, 1], ['ap-singapore', 'STARTER_BUNDLE', 3, 2], ['ap-singapore', 'GENERAL_BUNDLE', 0, 0]] as [$region, $type, $reserved, $used]) {
            $pool = UpstreamQuotaPool::firstOrCreate(
                ['region_code' => $region, 'bundle_type' => $type],
                ['capacity' => 20, 'reserved' => 0, 'used' => 0]
            );
            $pool->update(['reserved' => $reserved, 'used' => $used]);
        }

        // Cached upstream balance for the deposit widget (demo: $12.50).
        Cache::forever('tencent:balance', 1250);

        $this->command?->info('Demo Day-2 siap. Login: '.self::DEMO_USERNAME.'@example.com / password123');
        $this->command?->info('Buka: /dashboard/vps/'.$instance->id.' (tab "Kontrol Server") dan /admin/monitoring.');
    }
}