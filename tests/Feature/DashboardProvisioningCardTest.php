<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardProvisioningCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_dashboard_renders_card_for_paid_order_in_setup()
    {
        $user = User::create([
            'username' => 'buyer1',
            'email' => 'buyer1@example.com',
            'password' => bcrypt('password123'),
            'full_name' => 'Buyer Satu',
        ]);

        $spec = VpsSpec::first();

        $order = Order::create([
            'customer_id' => $user->id,
            'organization_id' => $user->current_organization_id,
            'vps_spec_id' => $spec->id,
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'control_panel' => 'coolify',
            'billing_cycle' => 'monthly',
            'hostname' => 'cv-srv-testing',
            'payment_method' => 'lynk',
            'channel' => 'website',
            'amount' => 1000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.index'));

        $response->assertStatus(200);
        $response->assertSee('Pesanan Diproses');
        $response->assertSee('Dalam Setup');
        $response->assertSee('Proses Setup Server');
        $response->assertSee('cv-srv-testing');
        $response->assertSee('Setup Server Sedang Berjalan');
        $response->assertDontSee('Belum Ada Layanan Aktif');
    }
}
