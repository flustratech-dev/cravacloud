<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function assertNoEmojis(string $content): void
    {
        // Unicode ranges for common emojis
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        $this->assertSame(
            0,
            preg_match($emojiPattern, $content),
            'Page contains forbidden emoji characters'
        );
    }

    public function test_terms_of_service_page_loads_and_renders_clauses_without_emojis(): void
    {
        $response = $this->get('/terms');
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertNoEmojis($content);

        $response->assertSee('Ketentuan Layanan (Terms of Service)');
        $response->assertSee('Acceptable Use Policy');
        $response->assertSee('Created by cravacloud.');
        $response->assertSee('Kredensial Root');
        $response->assertSee('PASAL 1');
        $response->assertSee('Zero Tolerance Enforcement');
    }

    public function test_privacy_policy_page_loads_and_renders_uu_pdp_clauses_without_emojis(): void
    {
        $response = $this->get('/privacy');
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertNoEmojis($content);

        $response->assertSee('Kebijakan Privasi (Privacy Policy)');
        $response->assertSee('Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi');
        $response->assertSee('Zero-Knowledge Server Access');
        $response->assertSee('Data Protection Officer');
        $response->assertSee('PASAL 1');
    }

    public function test_removed_sla_page_redirects_permanently_to_terms(): void
    {
        // CravaCloud tidak menjanjikan persentase uptime; tautan lama tetap berfungsi.
        $this->get('/sla')->assertRedirect('/terms')->assertStatus(301);
    }

    public function test_refund_policy_page_loads_and_renders_refund_clauses_without_emojis(): void
    {
        $response = $this->get('/refund');
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertNoEmojis($content);

        $response->assertSee('Kebijakan Pengembalian Dana (Refund Policy)');
        $response->assertSee('48 (empat puluh delapan) jam');
        $response->assertSee('Garansi Shopee');
        $response->assertSee('1 hingga 3 hari kerja');
        $response->assertSee('PASAL 1');
    }

    public function test_legal_pages_have_working_cross_document_navigation_links(): void
    {
        $pages = ['/terms', '/privacy', '/refund'];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);

            $response->assertSee(route('terms'));
            $response->assertSee(route('privacy'));
            $response->assertSee(route('refund'));
            $response->assertDontSee('SLA 99.9%');
        }
    }
}
