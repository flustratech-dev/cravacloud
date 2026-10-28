<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nama badan hukum dan surel kontak di setiap dokumen harus identitas asli
 * perusahaan, bukan isian template yang tercetak di faktur pelanggan.
 */
class IdentitasPerusahaanTest extends TestCase
{
    use RefreshDatabase;

    private function jalankanMigrasiPerbaikan(): void
    {
        $migrasi = require database_path('migrations/2026_09_19_200000_betulkan_identitas_perusahaan_dan_surel_kontak.php');
        $migrasi->up();
    }

    public function test_seeder_mengisi_identitas_asli(): void
    {
        $this->seed(DatabaseSeeder::class);

        $settings = app(SettingsService::class);
        $this->assertSame('PT NAMA PERUSAHAAN', $settings->get('company_legal_name'));
        $this->assertSame('admin@gmail.com', $settings->get('support_email'));
        $this->assertSame('admin@gmail.com', $settings->get('admin_notification_email'));
    }

    public function test_migrasi_mengganti_nilai_template_di_database_lama(): void
    {
        $this->seed(DatabaseSeeder::class);
        Setting::where('key', 'company_legal_name')->update(['value' => 'CravaCloud']);
        Setting::where('key', 'support_email')->update(['value' => 'support@cravacloud.id']);
        Setting::where('key', 'admin_notification_email')->update(['value' => 'admin@cravacloud.id']);

        $this->jalankanMigrasiPerbaikan();

        $this->assertSame('PT NAMA PERUSAHAAN', Setting::where('key', 'company_legal_name')->value('value'));
        $this->assertSame('admin@gmail.com', Setting::where('key', 'support_email')->value('value'));
        $this->assertSame('admin@gmail.com', Setting::where('key', 'admin_notification_email')->value('value'));
    }

    public function test_migrasi_tidak_menimpa_nilai_yang_sudah_diubah_admin(): void
    {
        $this->seed(DatabaseSeeder::class);
        Setting::where('key', 'support_email')->update(['value' => 'cs@contoh.id']);

        $this->jalankanMigrasiPerbaikan();

        $this->assertSame('cs@contoh.id', Setting::where('key', 'support_email')->value('value'));
    }

    public function test_halaman_hukum_tidak_lagi_memuat_isian_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['/terms', '/privacy', '/refund'] as $alamat) {
            $this->get($alamat)
                ->assertOk()
                ->assertDontSee('Crava Media Host')
                ->assertDontSee('admin@gmail.com');
        }
    }
}
