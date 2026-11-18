<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AiComboPackagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_retired_ai_product_routes_return_not_found(): void
    {
        $this->get('/ai')->assertStatus(404);
        $this->get('/ai-agent')->assertStatus(404);
        $this->get('/tambah-ai-agent')->assertStatus(404);
    }

    public function test_checkout_ai_package_stores_order_with_defaults(): void
    {
        $aiSpec = VpsSpec::where('category', 'ai_combo')->first();
        $this->assertNotNull($aiSpec);

        $payload = [
            'vps_spec_id' => $aiSpec->id,
            'payment_method' => 'qris',
            'full_name' => 'Budi AI Agent',
            'email' => 'budi.ai@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'root_password' => 'SecureRootPass123!',
            'terms_accepted' => 1,
        ];

        $response = $this->post('/checkout', $payload);

        // Should create order and redirect to payment
        $response->assertStatus(302);

        $order = Order::where('vps_spec_id', $aiSpec->id)->first();
        $this->assertNotNull($order);
        $this->assertStringStartsWith('cv-ai-', $order->hostname);
        $this->assertEquals($aiSpec->default_stack, $order->control_panel);
        $this->assertEquals($aiSpec->sell_price, $order->amount);
    }

    public function test_dashboard_navigation_links_point_to_landing_sections(): void
    {
        $user = User::create([
            'username' => 'clientnav',
            'email' => 'clientnav@example.com',
            'full_name' => 'Client Nav',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Button "+ Tambah VPS" points to /#pricing
        $response->assertSee('#pricing');
        // Removed ecosystem & AI nav links must not appear in Client Console
        $response->assertDontSee('Tambah AI Agent');
        $response->assertDontSee('Ekosistem');
        $response->assertDontSee('wa.cravacloud.id');
        $response->assertDontSee('build.cravacloud.id');
        $response->assertDontSee('/ai');
    }

    public function test_dashboard_renders_ai_instance_with_app_link(): void
    {
        $user = User::create([
            'username' => 'aiuser',
            'email' => 'aiuser@example.com',
            'full_name' => 'AI Client',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        $aiSpec = VpsSpec::where('category', 'ai_combo')->where('control_panel', 'vscode_server')->first()
            ?? VpsSpec::where('category', 'ai_combo')->first();

        $order = Order::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $aiSpec->id,
            'control_panel' => $aiSpec->default_stack,
            'provider' => 'cravacloud',
            'hostname' => 'cv-ai-workstation-test',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $aiSpec->sell_price,
            'paid_at' => now(),
        ]);

        $vps = VpsInstance::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'hostname' => 'cv-ai-workstation-test',
            'public_ip' => '103.150.100.20',
            'control_panel' => $aiSpec->default_stack,
            'os' => 'Ubuntu 24.04 LTS',
            'provider' => 'cravacloud',
            'status' => 'running',
            'cpu' => $aiSpec->cpu,
            'ram' => $aiSpec->ram,
            'disk' => $aiSpec->disk,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'grace_period_ends_at' => now()->addMonth()->addDays(7),
        ]);

        // Access dashboard index
        $indexResponse = $this->actingAs($user)->get('/dashboard');
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('AI Agent');
        $indexResponse->assertSee('Link Web App:');
        $indexResponse->assertSee('Buka Web App');

        // Access dashboard show
        $showResponse = $this->actingAs($user)->get("/dashboard/vps/{$vps->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee('AI Combo');
        $showResponse->assertSee('Akses Web App');
        $showResponse->assertSee('Buka Web App');
        $showResponse->assertSee($vps->app_url);
    }

    public function test_admin_protects_ai_packages_from_deletion(): void
    {
        $admin = User::where('is_admin', true)->first();
        $this->assertNotNull($admin);

        $aiSpec = VpsSpec::where('category', 'ai_combo')->first();
        $this->assertNotNull($aiSpec);

        $response = $this->actingAs($admin)->delete("/admin/packages/{$aiSpec->id}");
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        // Spec still exists
        $this->assertDatabaseHas('vps_specs', ['id' => $aiSpec->id]);
    }
}
