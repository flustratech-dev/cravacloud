<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusPageTest extends TestCase
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
            'Status page contains forbidden emoji characters'
        );
    }

    public function test_status_page_shows_only_real_data_without_uptime_claims(): void
    {
        $response = $this->get('/status');
        $response->assertStatus(200);

        $this->assertNoEmojis($response->getContent());

        // Isi berasal dari data nyata (system_components dari seeder).
        $response->assertSee('Status Layanan CravaCloud');
        $response->assertSee('Semua Sistem Beroperasi Normal');
        $response->assertSee('Status Komponen Layanan');
        $response->assertSee('Created by cravacloud.');
        $response->assertSee(route('terms'));
        $response->assertSee(route('docs'));

        // Tidak ada lagi klaim uptime/SLA atau metrik yang tidak diukur.
        $response->assertDontSee('SLA');
        $response->assertDontSee('Uptime');
        $response->assertDontSee('99.9');
        $response->assertDontSee('100.0%');
        $response->assertDontSee('Latensi');
        $response->assertDontSee('Gedung Cyber 1');
    }

    public function test_status_page_hides_component_uptime_percentage(): void
    {
        \App\Models\SystemComponent::query()->update(['uptime_percent' => 97.12]);

        $this->get('/status')
            ->assertStatus(200)
            ->assertDontSee('97.12');
    }
}
