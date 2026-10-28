<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_payment_page_loads_for_lynk_method_without_500_error(): void
    {
        $user = User::create([
            'username' => 'customertest1',
            'email' => 'customer1@test.com',
            'password' => bcrypt('password123'),
            'full_name' => 'Customer Test 1',
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $spec = VpsSpec::find(1);
        $spec->update(['payment_url' => 'https://lynk.id/cravacloud/student-basic']);

        $org = $user->createPersonalOrganization();

        $order = Order::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cloudeka',
            'hostname' => 'cv-test-payment-page',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'lynk',
            'amount' => 1000,
        ]);

        $response = $this->actingAs($user)->get(route('order.payment', $order->id));

        $response->assertStatus(200);
        $response->assertSee('Bayar via Lynk.id Gateway');
        $response->assertSee('Buka Halaman Checkout Lynk.id');
        $response->assertSee('https://lynk.id/cravacloud/student-basic');
    }

    public function test_payment_page_loads_for_qris_method(): void
    {
        $user = User::create([
            'username' => 'customertest2',
            'email' => 'customer2@test.com',
            'password' => bcrypt('password123'),
            'full_name' => 'Customer Test 2',
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $spec = VpsSpec::find(1);

        $org = $user->createPersonalOrganization();

        $order = Order::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cloudeka',
            'hostname' => 'cv-test-payment-page-qris',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 1000,
        ]);

        $response = $this->actingAs($user)->get(route('order.payment', $order->id));

        $response->assertStatus(200);
        $response->assertSee('Pembayaran QRIS');
    }
}
