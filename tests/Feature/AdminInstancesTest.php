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

    public function test_admin_instances_page_renders_with_provider_stat_cards(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();

        $response = $this->actingAs($admin)->get('/admin/instances');
        $response->assertStatus(200);
        $response->assertSee('Daftar VPS Instance Aktif');
        $response->assertSee('Total Cloud Instances');
        $response->assertSee('Running (Online)');
        $response->assertSee('Tencent Auto-Cloud');
        $response->assertSee('Perlu Perhatian');
        $response->assertDontSee('AI Agent & Workstation', false);
        $response->assertDontSee('Managed Database');
    }

    public function test_admin_can_filter_instances_by_tencent_provider(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $customer = $this->createTestCustomer();

        VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'tencent-auto-test',
            'public_ip' => '103.150.190.10',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'provider_resource_id' => 'lhins-test001',
            'control_panel' => 'coolify',
            'cpu' => 4,
            'ram' => 8,
            'disk' => 50,
        ]);

        VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'manual-legacy-test',
            'public_ip' => '103.150.190.11',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'cravacloud',
            'control_panel' => 'none',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 40,
        ]);

        $response = $this->actingAs($admin)->get('/admin/instances?provider=tencent');
        $response->assertStatus(200);
        $response->assertSee('tencent-auto-test');
        $response->assertDontSee('manual-legacy-test');
    }

    public function test_admin_can_filter_instances_by_manual_provider(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $customer = $this->createTestCustomer();

        VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'tencent-cloud-node',
            'public_ip' => '103.150.190.20',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'provider_resource_id' => 'lhins-test002',
            'control_panel' => 'docker',
            'cpu' => 4,
            'ram' => 8,
            'disk' => 80,
        ]);

        VpsInstance::create([
            'customer_id' => $customer->id,
            'hostname' => 'vps-manual-node',
            'public_ip' => '103.150.190.21',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'cravacloud',
            'control_panel' => 'none',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 40,
        ]);

        $response = $this->actingAs($admin)->get('/admin/instances?provider=manual');
        $response->assertStatus(200);
        $response->assertSee('vps-manual-node');
        $response->assertDontSee('tencent-cloud-node');
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
            'provider' => 'cravacloud',
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
