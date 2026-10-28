<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Kontrak endpoint POST /api/admin/vps/provision.
 *
 * Field IP dan kata sandi server memakai nama netral (server_ip,
 * server_root_password). Otomasi luar yang memanggil endpoint ini wajib
 * memakai nama field tersebut.
 */
class AdminApiProvisionTest extends TestCase
{
    use RefreshDatabase;

    protected string $key = 'kunci-admin-api-uji-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        Config::set('cravacloud.admin_api_key', $this->key);
    }

    protected function paidOrder(): Order
    {
        $customer = User::create([
            'username' => 'api' . uniqid(),
            'email' => 'api' . uniqid() . '@test.id',
            'full_name' => 'Pelanggan API',
            'password' => bcrypt('password123'),
        ]);
        $org = $customer->createPersonalOrganization();
        $customer->switchToOrganization($org);

        return Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => VpsSpec::where('category', 'vps')->first()->id,
            'control_panel' => 'none',
            'provider' => 'tencent',
            'hostname' => 'api-' . uniqid(),
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 80000,
            'paid_at' => now(),
        ]);
    }

    protected function payload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'order_id' => $order->id,
            'server_ip' => '103.150.20.30',
            'server_root_password' => 'RootServer#2026',
            'os' => 'ubuntu2404',
            'datacenter_location' => 'singapore',
            'control_panel' => 'none',
            'hostname' => $order->hostname,
        ], $overrides);
    }

    public function test_provision_accepts_neutral_field_names(): void
    {
        $order = $this->paidOrder();

        $this->postJson('/api/admin/vps/provision', $this->payload($order), ['X-Admin-Key' => $this->key])
            ->assertSuccessful();

        $instance = VpsInstance::where('order_id', $order->id)->first();
        $this->assertNotNull($instance);
        $this->assertSame('103.150.20.30', $instance->public_ip);
        $this->assertSame('RootServer#2026', $instance->initial_root_password);
    }

    public function test_server_ip_is_required(): void
    {
        $order = $this->paidOrder();
        $payload = $this->payload($order);
        unset($payload['server_ip']);

        $this->postJson('/api/admin/vps/provision', $payload, ['X-Admin-Key' => $this->key])
            ->assertStatus(422)
            ->assertJsonValidationErrors('server_ip');
    }

    public function test_request_without_valid_key_is_rejected(): void
    {
        $order = $this->paidOrder();

        $this->postJson('/api/admin/vps/provision', $this->payload($order), ['X-Admin-Key' => 'salah'])
            ->assertStatus(401);

        $this->assertSame(0, VpsInstance::where('order_id', $order->id)->count());
    }
}
