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

class ManagedDatabasePackagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_retired_database_product_routes_return_not_found(): void
    {
        $this->get('/database')->assertStatus(404);
        $this->get('/db')->assertStatus(404);
        $this->get('/tambah-database')->assertStatus(404);
    }

    public function test_checkout_database_package_stores_order_with_defaults(): void
    {
        $dbSpec = VpsSpec::where('category', 'managed_db')->first();
        $this->assertNotNull($dbSpec);

        $payload = [
            'vps_spec_id' => $dbSpec->id,
            'payment_method' => 'qris',
            'full_name' => 'Database Client',
            'email' => 'client.db@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'root_password' => 'SecureRootPass123!',
            'terms_accepted' => 1,
        ];

        $response = $this->post('/checkout', $payload);

        // Should create order and redirect to payment
        $response->assertStatus(302);

        $order = Order::where('vps_spec_id', $dbSpec->id)->first();
        $this->assertNotNull($order);
        $this->assertStringStartsWith('cv-db-', $order->hostname);
        $this->assertEquals('managed_database', $order->control_panel);
        $this->assertEquals($dbSpec->sell_price, $order->amount);
    }

    public function test_dashboard_navigation_links_point_to_database_packages_section(): void
    {
        $user = User::create([
            'username' => 'dbclientnav',
            'email' => 'dbclientnav@example.com',
            'full_name' => 'DB Client Nav',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        // Database & ecosystem links removed from Client Console navigation
        $response->assertDontSee('Tambah Database');
        $response->assertDontSee('/database');
        $response->assertDontSee('WA Gateway');
        $response->assertDontSee('Jasa Web');
    }

    public function test_dashboard_renders_managed_db_badge_and_remote_connection_guidance(): void
    {
        $user = User::create([
            'username' => 'dbuser',
            'email' => 'dbuser@example.com',
            'full_name' => 'Database User',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        $dbSpec = VpsSpec::where('category', 'managed_db')->first();

        $order = Order::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $dbSpec->id,
            'control_panel' => 'managed_database',
            'provider' => 'cravacloud',
            'hostname' => 'cv-db-micro-test',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $dbSpec->sell_price,
            'paid_at' => now(),
        ]);

        $vps = VpsInstance::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'hostname' => 'cv-db-micro-test',
            'public_ip' => '103.150.100.30',
            'control_panel' => 'managed_database',
            'os' => 'Ubuntu 24.04 LTS',
            'provider' => 'cravacloud',
            'status' => 'running',
            'cpu' => $dbSpec->cpu,
            'ram' => $dbSpec->ram,
            'disk' => $dbSpec->disk,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'grace_period_ends_at' => now()->addMonth()->addDays(7),
        ]);

        // Access dashboard index
        $indexResponse = $this->actingAs($user)->get('/dashboard');
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Managed DB');

        // Access dashboard show
        $showResponse = $this->actingAs($user)->get("/dashboard/vps/{$vps->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Managed DB');
        $showResponse->assertSee('Panduan Remote Database Client');
        $showResponse->assertSee('Port 5432');
        $showResponse->assertSee('Port 3306');
        $showResponse->assertSee('Port 6379');
        $showResponse->assertSee('Port 6333');
    }

    public function test_checkout_database_package_accepts_custom_engine_and_manager(): void
    {
        $dbSpec = VpsSpec::where('category', 'managed_db')->first();
        $this->assertNotNull($dbSpec);

        $payload = [
            'vps_spec_id' => $dbSpec->id,
            'payment_method' => 'qris',
            'full_name' => 'Database Client 2',
            'email' => 'client2.db@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'root_password' => 'SecureRootPass123!',
            'terms_accepted' => 1,
            'db_engine' => 'postgres',
            'db_manager' => 'cloudbeaver',
        ];

        $response = $this->post('/checkout', $payload);
        $response->assertStatus(302);

        $order = Order::where('customer_id', User::where('email', 'client2.db@example.com')->value('id'))->first();
        $this->assertNotNull($order);
        $this->assertEquals('postgres', $order->db_engine);
        $this->assertEquals('cloudbeaver', $order->db_manager);
        $this->assertEquals('5432', $order->db_port);
        $this->assertEquals('admin_crava', $order->db_user);
    }

    public function test_dashboard_displays_connection_string_format_and_env_variables(): void
    {
        $user = User::create([
            'username' => 'dbuser_env',
            'email' => 'dbuser_env@example.com',
            'full_name' => 'Database User Env',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        $dbSpec = VpsSpec::where('category', 'managed_db')->first();

        $vps = VpsInstance::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'hostname' => 'cv-db-micro-env',
            'public_ip' => '103.150.100.55',
            'control_panel' => 'managed_database',
            'db_engine' => 'postgres',
            'db_manager' => 'cloudbeaver',
            'db_name' => 'cravadb_production',
            'db_user' => 'admin_crava',
            'db_password' => 'cravaSecretPass99',
            'db_port' => 5432,
            'os' => 'Ubuntu 24.04 LTS',
            'provider' => 'cravacloud',
            'status' => 'running',
            'cpu' => $dbSpec->cpu,
            'ram' => $dbSpec->ram,
            'disk' => $dbSpec->disk,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->get("/dashboard/vps/{$vps->id}");
        $response->assertStatus(200);

        // Connection string format .env assertions
        $response->assertSee('DB_CONNECTION=pgsql');
        $response->assertSee('DB_HOST=103.150.100.55');
        $response->assertSee('DB_PORT=5432');
        $response->assertSee('DB_DATABASE=cravadb_production');
        $response->assertSee('DB_USERNAME=admin_crava');
        $response->assertSee('DATABASE_URL=postgresql://admin_crava:cravaSecretPass99@103.150.100.55:5432/cravadb_production');
        $response->assertSee('WEB_MANAGER_URL=https://103.150.100.55:8080 (CloudBeaver)');
        $response->assertSee('Format Connection String Database');
        $response->assertSee('PostgreSQL 16');
    }

    public function test_admin_protects_database_packages_from_deletion(): void
    {
        $admin = User::where('is_admin', true)->first();
        $this->assertNotNull($admin);

        $dbSpec = VpsSpec::where('category', 'managed_db')->first();
        $this->assertNotNull($dbSpec);

        $response = $this->actingAs($admin)->delete("/admin/packages/{$dbSpec->id}");
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        // Spec still exists
        $this->assertDatabaseHas('vps_specs', ['id' => $dbSpec->id]);
    }
}