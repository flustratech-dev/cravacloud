<?php

namespace Tests\Feature;

use App\Models\AbuseCase;
use App\Models\BroadcastLog;
use App\Models\NotificationTemplate;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\VpsSpec;
use App\Notifications\TemplatedNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PHASE 2 - Legal, AUP, dan Notifikasi Insiden.
 */
class LegalAndAbuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    protected function customer(): User
    {
        return User::create([
            'full_name' => 'Pelanggan Uji',
            'username' => 'pelangganaup' . uniqid(),
            'email' => 'aup' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
    }

    /**
     * Payload checkout minimal yang valid untuk paket Tencent.
     */
    protected function checkoutPayload(array $overrides = []): array
    {
        $spec = VpsSpec::where('is_active', true)
            ->where('category', 'vps')
            ->get()
            ->first(fn (VpsSpec $s) => $s->isProviderAllowed('tencent'));

        return array_merge([
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'tencent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'hostname' => 'cv-uji-' . uniqid(),
            'root_password' => 'RahasiaKuat123',
            'payment_method' => 'qris',
            'terms_accepted' => 1,
        ], $overrides);
    }

    // ======================= Naskah ketentuan =======================

    public function test_terms_page_lists_all_supplier_prohibitions(): void
    {
        $response = $this->get('/terms');

        $response->assertStatus(200);
        $response->assertSee('Layanan VPN &amp; Proxy', false);
        $response->assertSee('Scraping &amp; Crawling', false);
        $response->assertSee('Agregator Torrent');
        $response->assertSee('Bot &amp; Skrip Otomatis', false);
    }

    public function test_terms_page_contains_liability_and_backup_clauses(): void
    {
        $response = $this->get('/terms');

        $response->assertSee('Pembatasan Tanggung Jawab');
        $response->assertSee('Tanggung Jawab Pencadangan Data');
        $response->assertSee('Dampak Pelanggaran terhadap Infrastruktur Hulu');
        $response->assertSee('dua belas (12) bulan terakhir');
    }

    public function test_terms_page_shows_configured_version(): void
    {
        $this->get('/terms')->assertSee(config('legal.terms_version'));
    }

    // ======================= Persetujuan wajib di checkout =======================

    public function test_checkout_is_rejected_without_terms_acceptance(): void
    {
        $customer = $this->customer();

        $response = $this->actingAs($customer)
            ->postJson('/checkout', $this->checkoutPayload(['terms_accepted' => 0]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('terms_accepted');
        $this->assertSame(0, TermsAcceptance::count());
    }

    public function test_checkout_is_rejected_when_terms_field_missing(): void
    {
        $customer = $this->customer();
        $payload = $this->checkoutPayload();
        unset($payload['terms_accepted']);

        $this->actingAs($customer)
            ->postJson('/checkout', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('terms_accepted');
    }

    public function test_checkout_records_acceptance_with_version_and_ip(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->postJson('/checkout', $this->checkoutPayload(), ['REMOTE_ADDR' => '203.0.113.9']);

        $acceptance = TermsAcceptance::where('user_id', $customer->id)->first();

        $this->assertNotNull($acceptance);
        $this->assertSame(config('legal.terms_version'), $acceptance->terms_version);
        $this->assertNotNull($acceptance->accepted_at);
        $this->assertNotNull($acceptance->ip_address);
        $this->assertNotNull($acceptance->order_id);
    }

    public function test_order_stores_terms_version(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->postJson('/checkout', $this->checkoutPayload());

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'terms_version' => config('legal.terms_version'),
        ]);
    }

    public function test_checkout_page_renders_mandatory_consent_checkbox(): void
    {
        $response = $this->get('/checkout');

        $response->assertStatus(200);
        $response->assertSee('Ketentuan Penggunaan Layanan');
        $response->assertSee('terms_accepted', false);
        $response->assertSee('agregator torrent', false);
        $response->assertSee(config('legal.terms_version'));
    }

    // ======================= Template notifikasi =======================

    public function test_seeder_creates_separate_incident_and_abuse_templates(): void
    {
        $this->assertDatabaseHas('notification_templates', ['code' => NotificationTemplate::ABUSE_SUSPENSION]);
        $this->assertDatabaseHas('notification_templates', ['code' => NotificationTemplate::INFRA_INCIDENT]);

        $incident = NotificationTemplate::where('code', NotificationTemplate::INFRA_INCIDENT)->first();

        // Template massal tidak boleh menuduh penerima melanggar.
        $this->assertStringNotContainsStringIgnoringCase('melanggar', $incident->body);
        $this->assertStringContainsString('upstream', $incident->body);
    }

    public function test_template_renders_placeholders(): void
    {
        $template = NotificationTemplate::where('code', NotificationTemplate::INFRA_INCIDENT)->first();

        $body = $template->renderBody([
            'nama' => 'Budi',
            'waktu_mulai' => '18 Sep 2026 03:00 WIB',
            'status' => 'Sedang ditangani',
            'keterangan' => 'Perbaikan berjalan.',
        ]);

        $this->assertStringContainsString('Budi', $body);
        $this->assertStringNotContainsString('{{nama}}', $body);
    }

    public function test_admin_can_view_and_update_templates(): void
    {
        $template = NotificationTemplate::first();

        $this->actingAs($this->admin())->get('/admin/templates')->assertStatus(200);

        $this->actingAs($this->admin())
            ->put("/admin/templates/{$template->id}", [
                'name' => 'Nama Baru Template',
                'subject' => 'Subjek Baru',
                'body' => 'Isi baru untuk {{nama}}.',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('Subjek Baru', $template->fresh()->subject);
    }

    // ======================= Kasus pelanggaran =======================

    public function test_admin_can_view_abuse_page(): void
    {
        $this->actingAs($this->admin())->get('/admin/abuse')
            ->assertStatus(200)
            ->assertSee('Pelanggaran Satu Pelanggan Berdampak ke Semua');
    }

    public function test_admin_can_record_abuse_case(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())->post('/admin/abuse', [
            'user_id' => $customer->id,
            'type' => 'vpn_proxy',
            'severity' => 'critical',
            'evidence' => 'Trafik SOCKS5 pada port 1080.',
        ])->assertRedirect();

        $this->assertDatabaseHas('abuse_cases', [
            'user_id' => $customer->id,
            'type' => 'vpn_proxy',
            'status' => 'open',
        ]);
    }

    public function test_abuse_types_cover_all_supplier_prohibitions(): void
    {
        $types = array_keys(AbuseCase::types());

        foreach (['vpn_proxy', 'scraping', 'torrent', 'automated_bots'] as $required) {
            $this->assertContains($required, $types);
        }
    }

    public function test_admin_notify_sends_only_to_offending_customer(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $other = $this->customer();

        $case = AbuseCase::create([
            'user_id' => $customer->id,
            'type' => 'crypto_mining',
            'severity' => 'high',
            'evidence' => 'CPU 100% berkelanjutan.',
            'status' => 'open',
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/abuse/{$case->id}/notify", ['deadline_days' => 3])
            ->assertRedirect();

        Notification::assertSentTo($customer, TemplatedNotification::class);
        Notification::assertNotSentTo($other, TemplatedNotification::class);

        $this->assertSame('notified', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->notified_at);
    }

    public function test_admin_can_resolve_abuse_case(): void
    {
        $case = AbuseCase::create([
            'user_id' => $this->customer()->id,
            'type' => 'spam_phishing',
            'severity' => 'medium',
            'status' => 'notified',
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/abuse/{$case->id}/resolve", [
                'status' => 'terminated',
                'resolution' => 'Pelanggan tidak menanggapi, layanan diterminasi.',
            ])
            ->assertRedirect();

        $this->assertSame('terminated', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->resolved_at);
    }

    // ======================= Broadcast darurat =======================

    public function test_admin_can_view_broadcast_page(): void
    {
        $this->actingAs($this->admin())->get('/admin/broadcast')
            ->assertStatus(200)
            ->assertSee('Jangan Menuduh Penerima Melanggar');
    }

    public function test_broadcast_requires_confirmation_checkbox(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/broadcast', [
                'audience' => 'all',
                'subject' => 'Uji',
                'body' => 'Isi uji.',
            ])
            ->assertSessionHasErrors('confirm');
    }

    public function test_broadcast_sends_to_customers_and_logs_result(): void
    {
        Notification::fake();

        $a = $this->customer();
        $b = $this->customer();

        $this->actingAs($this->admin())->post('/admin/broadcast', [
            'audience' => 'all',
            'subject' => 'Pemberitahuan Gangguan Layanan',
            'body' => "Halo {{nama}},\n\nTerjadi gangguan di tingkat infrastruktur upstream.",
            'confirm' => 1,
        ])->assertRedirect();

        Notification::assertSentTo($a, TemplatedNotification::class);
        Notification::assertSentTo($b, TemplatedNotification::class);

        $log = BroadcastLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('all', $log->audience);
        $this->assertGreaterThanOrEqual(2, $log->sent_count);
    }

    public function test_broadcast_does_not_reach_admin_accounts(): void
    {
        Notification::fake();

        $this->customer();

        $this->actingAs($this->admin())->post('/admin/broadcast', [
            'audience' => 'all',
            'subject' => 'Uji Segmen',
            'body' => 'Isi.',
            'confirm' => 1,
        ]);

        Notification::assertNotSentTo($this->admin(), TemplatedNotification::class);
    }

    public function test_admin_can_export_contacts_as_csv(): void
    {
        $customer = $this->customer();

        $response = $this->actingAs($this->admin())->get('/admin/broadcast/export-contacts');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Nama', $content);
        $this->assertStringContainsString($customer->email, $content);
    }

    public function test_customer_cannot_access_abuse_or_broadcast(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->get('/admin/abuse')->assertStatus(403);
        $this->actingAs($customer)->get('/admin/broadcast')->assertStatus(403);
        $this->actingAs($customer)->get('/admin/templates')->assertStatus(403);
    }
}
