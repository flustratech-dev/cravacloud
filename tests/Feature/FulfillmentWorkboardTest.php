<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentWorkboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->customer = User::factory()->create([
            'is_admin' => false,
        ]);
        $org = $this->customer->createPersonalOrganization();
        $this->customer->switchToOrganization($org);
    }

    public function test_fulfillment_route_redirects_to_orders_needs_provision(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/fulfillment');
        $response->assertRedirect(route('admin.orders', ['status' => 'needs_provision']));
    }

    public function test_fulfillment_route_forbidden_for_regular_customer(): void
    {
        $response = $this->actingAs($this->customer)->get('/admin/fulfillment');
        $response->assertStatus(403);
    }
}
