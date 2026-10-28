<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpsInstance;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInstancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function createTestCustomer(): User
    {
        return User::create([
            'full_name' => 'Customer Test',
            'username' => 'customertest',
            'email' => 'customer' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
    }

    public function test_admin_instances_page_renders_with_category_stat_cards(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();

        $response = $this->actingAs($admin)->get('/admin/instances');
        $response->assertStatus(200);
        $response->assertSee('Daftar VPS Instance Aktif');
        $response->assertSee('Total Cloud Instances');
        $response->assertSee('Cloud VPS Standar');
        $response->assertSee('AI Agent & Workstation', false);
        $response->assertSee('Managed Database');
    }

    public function test_admin_can_filter_instances_by_ai_category(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $customer = $this->createTestCustomer();

        $aiInstance = VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'ai-hermes-test',
            'public_ip' => '103.150.190.10',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'control_panel' => 'hermes_agent',
            'app_url' => 'http://103.150.190.10:8080',
            'app_name' => 'Hermes Agent Instance',
            'cpu' => 4,
            'ram' => 8,
            'disk' => 50,
        ]);

        $vpsInstance = VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'vps-standard-test',
            'public_ip' => '103.150.190.11',
            'status' => 'running',
            'os' => 'debian12',
            'provider' => 'tencent',
            'control_panel' => 'none',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 40,
        ]);

        $response = $this->actingAs($admin)->get('/admin/instances?category=ai');
        $response->assertStatus(200);
        $response->assertSee('ai-hermes-test');
        $response->assertDontSee('vps-standard-test');
    }

    public function test_admin_can_filter_instances_by_database_category(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $customer = $this->createTestCustomer();

        $dbInstance = VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'cv-db-postgres-prod',
            'public_ip' => '103.150.190.20',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'control_panel' => 'managed_database',
            'db_engine' => 'postgres',
            'db_name' => 'cravadb_production',
            'db_port' => 5432,
            'cpu' => 4,
            'ram' => 8,
            'disk' => 80,
        ]);

        $vpsInstance = VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'vps-web-app-test',
            'public_ip' => '103.150.190.21',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'control_panel' => 'none',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 40,
        ]);

        $response = $this->actingAs($admin)->get('/admin/instances?category=database');
        $response->assertStatus(200);
        $response->assertSee('cv-db-postgres-prod');
        $response->assertDontSee('vps-web-app-test');
    }

    public function test_admin_instances_page_has_no_emojis_or_non_monochrome_colors(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $customer = $this->createTestCustomer();

        VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'ai-runner',
            'public_ip' => '103.150.190.30',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'control_panel' => 'vscode_server',
            'app_url' => 'http://103.150.190.30:8443',
            'cpu' => 4,
            'ram' => 8,
            'disk' => 50,
        ]);

        $response = $this->actingAs($admin)->get('/admin/instances');
        $content = $response->getContent();

        // Check for emojis
        $hasEmoji = preg_match('/[\x{1F300}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $content);
        $this->assertEquals(0, $hasEmoji, 'Admin instances page should not contain emojis.');

        // Check for non-monochrome color classes
        $hasColor = preg_match('/(text|bg|border)-(blue|green|red|yellow|orange|purple|emerald|teal|cyan|sky|indigo|violet|rose|amber)/', $content);
        $this->assertEquals(0, $hasColor, 'Admin instances page should not contain non-monochrome color classes.');
    }
}
