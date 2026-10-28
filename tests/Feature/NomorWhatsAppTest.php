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
        config(['cravacloud.whatsapp' => '085808749131']);

        $this->assertSame('6285808749131', NomorWhatsApp::internasional());
        $this->assertSame('085808749131', NomorWhatsApp::lokal());
        $this->assertSame('0858-0874-9131', NomorWhatsApp::tampil());
        $this->assertSame('https://wa.me/6285808749131', NomorWhatsApp::tautan());
    }

    public function test_halaman_publik_menampilkan_nomor_baru_di_tautan_dan_teks(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['cravacloud.whatsapp' => '6285808749131']);

        $this->get('/')
            ->assertOk()
            ->assertSee('wa.me/6285808749131', false)
            ->assertSee('0858-0874-9131')
            ->assertDontSee('5774410978', false);

        $this->get('/refund')
            ->assertOk()
            ->assertSee('WhatsApp Finance 085808749131')
            ->assertDontSee('5774410978', false);
    }
}
