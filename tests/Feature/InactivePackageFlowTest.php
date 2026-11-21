<?php

namespace Tests\Feature;

use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactivePackageFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_inactive_packages_remain_visible_on_landing_page(): void
    {
        // Deactivate official Cloud VPS package
        $vpsSpec = VpsSpec::where('category', 'vps')->first();
        $vpsSpec->update(['is_active' => false]);

        $response = $this->get('/');
        $response->assertStatus(200);

        // Active/inactive VPS packages remain visible on landing page (with maintenance notice when inactive)
        $response->assertSee($vpsSpec->name);
        $response->assertSee(route('checkout', $vpsSpec->id));

        // Retired AI & Database product routes return 404
        $this->get('/ai')->assertStatus(404);
        $this->get('/database')->assertStatus(404);
    }

    public function test_inactive_packages_remain_visible_on_product_pages_and_navbar(): void
    {
        $vpsSpec = VpsSpec::where('category', 'vps')->first();
        $vpsSpec->update(['is_active' => false]);

        // Check VPS product comparison page
        $this->get('/vps')->assertStatus(200)->assertSee($vpsSpec->name);
        $this->get('/ai')->assertStatus(404);
        $this->get('/database')->assertStatus(404);
    }

    public function test_checkout_page_renders_maintenance_warning_and_locked_state_for_inactive_package(): void
    {
        $vpsSpec = VpsSpec::where('category', 'vps')->first();
        $vpsSpec->update(['is_active' => false]);

        $response = $this->get('/checkout/' . $vpsSpec->id);
        $response->assertStatus(200);

        // Warning banner above slide
        $response->assertSee('Paket Sementara Dinonaktifkan');
        $response->assertSee('pemeliharaan');
        $response->assertSee('Pemesanan untuk paket ini ditangguhkan sementara');

        // Locked button state
        $response->assertSee('Pemesanan Dikunci');
    }

    public function test_retired_legacy_categories_are_absent_from_catalog(): void
    {
        $this->assertSame(0, VpsSpec::whereIn('category', ['ai_combo', 'managed_db'])->count());
        $this->assertSame(8, VpsSpec::where('category', 'vps')->count());
    }

    public function test_order_creation_is_rejected_when_package_is_inactive(): void
    {
        $vpsSpec = VpsSpec::where('category', 'vps')->first();
        $vpsSpec->update(['is_active' => false]);

        $payload = [
            'vps_spec_id' => $vpsSpec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'hostname' => 'cv-test-srv',
            'root_password' => 'secret123456',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
            'full_name' => 'Budi Tester',
            'username' => 'buditester',
            'email' => 'budi@example.com',
            'password' => 'secret123456',
        ];

        $response = $this->postJson('/checkout', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Paket layanan ini sedang dinonaktifkan sementara oleh tim CravaCloud untuk pemeliharaan sistem.',
        ]);
        $this->assertDatabaseMissing('orders', [
            'hostname' => 'cv-test-srv',
        ]);
    }

    public function test_active_package_can_still_be_ordered(): void
    {
        $vpsSpec = VpsSpec::where('category', 'vps')->where('is_active', true)->first();
        $provider = $vpsSpec->defaultProvider();
        $datacenter = ($provider === 'cloudeka') ? 'indonesia' : 'singapore';

        $payload = [
            'vps_spec_id' => $vpsSpec->id,
            'control_panel' => 'none',
            'provider' => $provider,
            'datacenter_location' => $datacenter,
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'hostname' => 'cv-active-srv',
            'root_password' => 'secret123456',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
            'full_name' => 'Active Tester',
            'username' => 'activetester',
            'email' => 'active@example.com',
            'password' => 'secret123456',
        ];

        $response = $this->postJson('/checkout', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'hostname' => 'cv-active-srv',
        ]);
    }

    public function test_seeder_does_not_reactivate_inactive_package_on_redeploy(): void
    {
        $spec = VpsSpec::first();
        $spec->update(['is_active' => false]);
        $this->assertFalse($spec->fresh()->is_active);

        // Simulate deployment command: php artisan db:seed --force
        $this->seed(\Database\Seeders\VpsSpecSeeder::class);

        // Package must still remain inactive, NOT reset to true!
        $this->assertFalse($spec->fresh()->is_active);
    }

    public function test_checkout_hides_package_selector_and_step_eyebrow_labels(): void
    {
        $spec = VpsSpec::where('category', 'vps')->where('is_active', true)->firstOrFail();

        $this->get(route('checkout', $spec->id))
            ->assertOk()
            ->assertSee($spec->name)
            ->assertSee('Konfigurasi VPS Anda')
            ->assertSee('Informasi Akun')
            ->assertSee('Metode Pembayaran')
            ->assertDontSee('checkout-package-spec', false)
            ->assertDontSee('LANGKAH 01 // SPESIFIKASI')
            ->assertDontSee('LANGKAH 02 // IDENTITAS AKUN')
            ->assertDontSee('LANGKAH 01 // IDENTITAS AKUN')
            ->assertDontSee('LANGKAH 03 // METODE PEMBAYARAN')
            ->assertDontSee('LANGKAH 02 // METODE PEMBAYARAN');
    }
}
