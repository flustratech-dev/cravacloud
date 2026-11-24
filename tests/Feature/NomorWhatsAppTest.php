<?php

namespace Tests\Feature;

use App\Support\NomorWhatsApp;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nomor WhatsApp bisnis dibaca dari satu tempat, termasuk teks yang tampil di
 * samping tombolnya — bukan hanya tautannya.
 */
class NomorWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_semua_bentuk_nomor_berasal_dari_config(): void
    {
        $this->assertSame('6285776361743', NomorWhatsApp::internasional());

        foreach (['085776361743', '+62 857-7636-1743'] as $format) {
            config(['cravacloud.whatsapp' => $format]);

            $this->assertSame('6285776361743', NomorWhatsApp::internasional());
            $this->assertSame('085776361743', NomorWhatsApp::lokal());
            $this->assertSame('0857-7636-1743', NomorWhatsApp::tampil());
            $this->assertSame('https://wa.me/6285776361743', NomorWhatsApp::tautan());
        }
    }

    public function test_halaman_publik_menampilkan_nomor_baru_di_tautan_dan_teks(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['cravacloud.whatsapp' => '6285776361743']);

        $this->get('/')
            ->assertOk()
            ->assertSee('wa.me/6285776361743', false)
            ->assertSee('0857-7636-1743')
            ->assertDontSee('85808749131', false)
            ->assertDontSee('0858-0874-9131', false)
            ->assertDontSee('5774410978', false);

        $this->get('/refund')
            ->assertOk()
            ->assertSee('WhatsApp Finance 085776361743')
            ->assertDontSee('85808749131', false)
            ->assertDontSee('0858-0874-9131', false)
            ->assertDontSee('5774410978', false);
    }
}
