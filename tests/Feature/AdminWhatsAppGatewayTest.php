<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\VpsProvisionedNotification;
use App\Services\AdminAlertService;
use App\Services\SettingsService;
use App\Services\WhatsAppGateway;
use App\Services\WhatsAppSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminWhatsAppGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        WhatsAppSettings::flush();
        WhatsAppGateway::flushHealthCache();
        Cache::forget(WhatsAppGateway::LAST_FAILED_CACHE_KEY);
        Cache::forget(WhatsAppGateway::LAST_SESSION_EVENT_CACHE_KEY);
    }

    protected function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    protected function customer(string $phone = '081234567890'): User
    {
        return User::create([
            'full_name' => 'Pelanggan Uji WA',
            'username'  => 'ujipelanggan' . uniqid(),
            'email'     => 'ujipelanggan' . uniqid() . '@example.com',
            'password'  => bcrypt('password123'),
            'phone'     => $phone,
            'is_admin'  => false,
        ]);
    }

    protected function createOrder(User $user, VpsSpec $spec, string $status = 'pending'): Order
    {
        return Order::create([
            'customer_id'         => $user->id,
            'vps_spec_id'         => $spec->id,
            'hostname'            => 'vps-test.local',
            'datacenter_location' => 'indonesia',
            'os'                  => 'ubuntu2404',
            'control_panel'       => 'none',
            'channel'             => 'website',
            'billing_cycle'       => 'monthly',
            'status'              => $status,
            'amount'              => 150000,
            'payment_method'      => 'bank_transfer',
        ]);
    }

    public function test_pengunjung_non_admin_tidak_bisa_mengakses_halaman_whatsapp(): void
    {
        $response = $this->get(route('admin.whatsapp.index'));
        $response->assertRedirect('/login');

        $user = $this->customer();
        $response = $this->actingAs($user)->get(route('admin.whatsapp.index'));
        $response->assertForbidden();
    }

    public function test_admin_dapat_melihat_halaman_whatsapp_gateway_env_only(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.vexahostcloud.my.id',
            'whatsapp.key'     => 'vwa_test1234.abcdefghijklmnop',
            'whatsapp.session' => null,
            'whatsapp.timeout' => 5,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.whatsapp.index'));

        $response->assertOk();
        $response->assertSee('WhatsApp Gateway');
        $response->assertSee('Status Koneksi WhatsApp Gateway');
        $response->assertSee('Konfigurasi Gateway (.env)');
        $response->assertSee('ENV-ONLY');
        $response->assertSee('https://wa.vexahostcloud.my.id');
        $response->assertSee('vwa_••••••••mnop');
        $response->assertSee('Uji Kirim Pesan WhatsApp');
        $response->assertSee(json_encode(route('admin.whatsapp.status')), false);
        $response->assertDontSee('Simpan Pengaturan Kredensial');

        $this->assertFalse(Route::has('admin.whatsapp.settings'));
        $this->assertFalse(Route::has('admin.whatsapp.settings.forget'));
    }

    public function test_pengaturan_database_lama_tidak_pernah_menimpa_config_env(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.vexahostcloud.my.id',
            'whatsapp.key'     => 'vwa_envkey.secretvalue1234',
            'whatsapp.session' => '',
            'whatsapp.timeout' => 5,
        ]);

        Setting::create([
            'key'   => 'wa_url',
            'value' => 'https://wa.cravacloud.my.id',
            'type'  => 'string',
            'group' => 'whatsapp',
            'label' => 'wa_url',
        ]);
        Setting::create([
            'key'   => 'wa_enabled',
            'value' => '0',
            'type'  => 'boolean',
            'group' => 'whatsapp',
            'label' => 'wa_enabled',
        ]);

        WhatsAppSettings::apply();

        $this->assertTrue(config('whatsapp.enabled'));
        $this->assertSame('https://wa.vexahostcloud.my.id', config('whatsapp.url'));
        $this->assertSame('vwa_envkey.secretvalue1234', config('whatsapp.key'));

        $summary = WhatsAppSettings::summary();
        $this->assertFalse($summary['wa_url']['from_database']);
        $this->assertSame('env', $summary['wa_url']['source']);
        $this->assertSame('https://wa.vexahostcloud.my.id', $summary['wa_url']['value']);
    }

    public function test_health_check_menangani_semua_status_sesi_cache_dan_lonjakan_gagal(): void
    {
        // 1. not_configured
        config(['whatsapp.key' => null]);
        $notConfigured = WhatsAppGateway::healthCheck(true);
        $this->assertSame('not_configured', $notConfigured['status']);

        // 2. ready + session_list + caching 30s + fresh=1
        config([
            'whatsapp.enabled'              => true,
            'whatsapp.url'                  => 'https://wa.vexahostcloud.my.id',
            'whatsapp.key'                  => 'dummy-api-key',
            'whatsapp.health_cache_seconds' => 30,
        ]);

        Http::fake([
            'https://wa.vexahostcloud.my.id/api/v1/health' => Http::sequence()
                ->push([
                    'success' => true,
                    'data'    => [
                        'workspace' => 'Cs Crava',
                        'status'    => 'active',
                        'sessions'  => ['total' => 2, 'connected' => 2, 'limit' => 3],
                        'usage'     => ['messages_sent' => 150, 'messages_failed' => 2, 'quota' => 1000],
                    ],
                ], 200)
                ->push([
                    'success' => true,
                    'data'    => [
                        'workspace' => 'Cs Crava',
                        'status'    => 'active',
                        'sessions'  => ['total' => 2, 'connected' => 1, 'limit' => 3],
                        'usage'     => ['messages_sent' => 160, 'messages_failed' => 20, 'quota' => 1000],
                    ],
                ], 200)
                ->push([
                    'success' => true,
                    'data'    => [
                        'workspace' => 'Cs Crava',
                        'status'    => 'active',
                        'sessions'  => ['total' => 2, 'connected' => 0, 'limit' => 3],
                        'usage'     => ['messages_sent' => 160, 'messages_failed' => 20, 'quota' => 1000],
                    ],
                ], 200)
                ->push([
                    'success' => true,
                    'data'    => [
                        'workspace' => 'Cs Crava',
                        'status'    => 'suspended',
                        'sessions'  => ['total' => 2, 'connected' => 2, 'limit' => 3],
                        'usage'     => ['messages_sent' => 160, 'messages_failed' => 20, 'quota' => 1000],
                    ],
                ], 200)
                ->push(['success' => false, 'error' => ['message' => 'Unauthorized']], 401),
            'https://wa.vexahostcloud.my.id/api/v1/sessions' => Http::response([
                'success' => true,
                'data'    => [
                    [
                        'id'           => '01m8429fd7bwq2y22h9c06mm4y',
                        'name'         => 'Cs Utama Cravacloud',
                        'status'       => 'connected',
                        'phone_number' => '6285776361743',
                        'push_name'    => 'cravacloud',
                        'connected_at' => '2026-11-24T08:10:56+07:00',
                        'last_error'   => null,
                    ],
                ],
            ], 200),
        ]);

        // Hit pertama -> ready (mengisi cache)
        $resReady = $this->actingAs($this->admin())->getJson(route('admin.whatsapp.status'));
        $resReady->assertOk()->assertJson([
            'status'           => 'ready',
            'workspace'        => 'Cs Crava',
            'workspace_status' => 'active',
            'failure_spike'    => false,
            'sessions'         => ['total' => 2, 'connected' => 2, 'limit' => 3],
            'usage'            => ['messages_sent' => 150, 'messages_failed' => 2, 'quota' => 1000],
        ]);
        $this->assertCount(1, $resReady->json('session_list'));
        $this->assertSame('Cs Utama Cravacloud', $resReady->json('session_list.0.name'));

        // Hit kedua tanpa fresh=1 harus membaca cache (tidak memanggil HTTP baru)
        $cached = $this->actingAs($this->admin())->getJson(route('admin.whatsapp.status'));
        $cached->assertOk()->assertJson(['status' => 'ready']);
        Http::assertSentCount(2); // 1 health + 1 sessions

        // Hit dengan ?fresh=1 -> partial + failure_spike (20 - 2 > 10)
        $resPartial = $this->actingAs($this->admin())->getJson(route('admin.whatsapp.status', ['fresh' => 1]));
        $resPartial->assertOk()->assertJson([
            'status'        => 'partial',
            'failure_spike' => true,
        ]);

        // Hit fresh -> disconnected
        $resDisconnected = $this->actingAs($this->admin())->getJson(route('admin.whatsapp.status', ['fresh' => 1]));
        $resDisconnected->assertOk()->assertJson(['status' => 'disconnected']);

        // Hit fresh -> inactive
        $resInactive = $this->actingAs($this->admin())->getJson(route('admin.whatsapp.status', ['fresh' => 1]));
        $resInactive->assertOk()->assertJson(['status' => 'inactive']);

        // Hit fresh -> error 401
        $resError = $this->actingAs($this->admin())->getJson(route('admin.whatsapp.status', ['fresh' => 1]));
        $resError->assertOk()->assertJson([
            'status'      => 'error',
            'http_status' => 401,
            'message'     => 'Unauthorized',
        ]);
    }

    public function test_health_check_mengembalikan_offline_saat_gateway_tidak_terjangkau(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.vexahostcloud.my.id',
            'whatsapp.key'     => 'dummy-api-key',
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 6: Could not resolve host');
        });

        $health = WhatsAppGateway::healthCheck(true);

        $this->assertSame('offline', $health['status']);
        $this->assertStringContainsString('Could not resolve host', $health['message']);
    }

    public function test_klien_wa_gateway_send_bulk_template_media_dan_status_pesan(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.vexahostcloud.my.id',
            'whatsapp.key'     => 'vwa_live.secret1234',
            'whatsapp.session' => '',
        ]);

        Http::fake([
            'https://wa.vexahostcloud.my.id/api/v1/messages/text' => Http::response([
                'success' => true,
                'data'    => [
                    'id'           => '01K2CDEFGH7JKMNPQRSTVWXYZ',
                    'to'           => '6281234567890',
                    'status'       => 'queued',
                    'scheduled_at' => null,
                ],
            ], 202),
            'https://wa.vexahostcloud.my.id/api/v1/messages/bulk' => Http::response([
                'success' => true,
                'data'    => ['batch_id' => '01K2BATCH', 'queued' => 2, 'scheduled' => 0],
            ], 202),
            'https://wa.vexahostcloud.my.id/api/v1/messages/template' => Http::response([
                'success' => true,
                'data'    => ['id' => '01K2TPL', 'status' => 'queued'],
            ], 202),
            'https://wa.vexahostcloud.my.id/api/v1/messages/media' => Http::response([
                'success' => true,
                'data'    => ['id' => '01K2MEDIA', 'status' => 'queued'],
            ], 202),
            'https://wa.vexahostcloud.my.id/api/v1/messages/01K2CDEFGH7JKMNPQRSTVWXYZ' => Http::response([
                'success' => true,
                'data'    => ['id' => '01K2CDEFGH7JKMNPQRSTVWXYZ', 'status' => 'delivered'],
            ], 200),
        ]);

        $this->assertTrue(WhatsAppGateway::send('081234567890', 'Halo dari tes'));
        $this->assertTrue(WhatsAppGateway::broadcast(['081234567890', '081234567890', '120363012345678901@g.us', 'invalid'], 'Pengumuman'));
        $this->assertTrue(WhatsAppGateway::template('081234567890', 'pengingat-invoice', ['nomor' => 'INV-001']));

        $tmpFile = tempnam(sys_get_temp_dir(), 'wa_media_');
        file_put_contents($tmpFile, 'PDF_DUMMY_CONTENT');
        try {
            $this->assertTrue(WhatsAppGateway::sendMedia('081234567890', $tmpFile, 'document', 'Faktur Anda'));
        } finally {
            @unlink($tmpFile);
        }

        $status = WhatsAppGateway::messageStatus('01K2CDEFGH7JKMNPQRSTVWXYZ');
        $this->assertSame('delivered', $status['status']);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://wa.vexahostcloud.my.id/api/v1/messages/bulk') {
                return true;
            }

            return $request['to'] === ['6281234567890', '120363012345678901@g.us']
                && $request['message'] === 'Pengumuman'
                && $request->header('X-Api-Key')[0] === 'vwa_live.secret1234';
        });
    }

    public function test_uji_kirim_pesan_sukses_dengan_respon_202_dan_audit_log(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.vexahostcloud.my.id',
            'whatsapp.key'     => 'test-key-valid',
            'whatsapp.session' => 'sess_1',
        ]);

        Http::fake([
            'https://wa.vexahostcloud.my.id/api/v1/messages/text' => Http::response([
                'success' => true,
                'data'    => [
                    'id'           => 'msg_test_999',
                    'to'           => '6281234567890',
                    'status'       => 'queued',
                    'scheduled_at' => '2026-11-25T08:00:00+07:00',
                ],
            ], 202),
        ]);

        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('admin.whatsapp.test'), [
            'phone'   => '081234567890',
            'message' => 'Tes pesan dari CravaCloud',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status'       => 'ok',
            'to'           => '6281234567890',
            'message_id'   => 'msg_test_999',
            'queue_status' => 'queued',
            'scheduled_at' => '2026-11-25T08:00:00+07:00',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://wa.vexahostcloud.my.id/api/v1/messages/text'
                && $request['to'] === '6281234567890'
                && $request['session_id'] === 'sess_1'
                && $request['message'] === 'Tes pesan dari CravaCloud'
                && $request->header('X-Api-Key')[0] === 'test-key-valid';
        });

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action'        => 'whatsapp.test_sent',
        ]);
    }

    public function test_normalisasi_nomor_telepon_dan_group_id(): void
    {
        $this->assertSame('6281234567890', WhatsAppGateway::normalize('081234567890'));
        $this->assertSame('6281234567890', WhatsAppGateway::normalize('+6281234567890'));
        $this->assertSame('6281234567890', WhatsAppGateway::normalize('6281234567890'));
        $this->assertSame('6281234567890', WhatsAppGateway::normalize('0812-3456-7890'));
        $this->assertSame('120363012345678901@g.us', WhatsAppGateway::normalize('120363012345678901@g.us'));
        $this->assertSame('1203****@g.us', WhatsAppGateway::mask('120363012345678901@g.us'));
        $this->assertNull(WhatsAppGateway::normalize(''));
        $this->assertNull(WhatsAppGateway::normalize('12345'));
        $this->assertNull(WhatsAppGateway::normalize('bukan-grup@g.us'));
    }

    public function test_webhook_whatsapp_verifikasi_hmac_dedupe_dan_alert_sesi_putus(): void
    {
        Http::fake();
        Mail::fake();

        app(SettingsService::class)->set('mail_admin_addresses', 'ops@cravacloud.id');

        // 1. Tanpa secret -> 503
        config(['whatsapp.webhook_secret' => '']);
        $this->postJson(route('api.webhooks.whatsapp'), ['event' => 'session.status'])
            ->assertStatus(503);

        // 2. Signature tidak valid -> 401
        config(['whatsapp.webhook_secret' => 'rahasia-webhook-123']);
        $payload = [
            'event'       => 'session.status',
            'timestamp'   => '2026-11-24T08:30:00+07:00',
            'delivery_id' => '01K4DELIVERY123456',
            'data'        => [
                'session_id'   => '01m8429fd7bwq2y22h9c06mm4y',
                'name'         => 'Cs Utama Cravacloud',
                'status'       => 'disconnected',
                'phone_number' => '6285776361743',
                'reason'       => 'logged_out',
            ],
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
            'HTTP_X_VEXAHOST_EVENT'   => 'session.status',
            'HTTP_X_VEXAHOST_SIGNATURE' => 'salah-signature',
        ], $rawBody)->assertUnauthorized();

        // 3. Signature valid -> 204, cache event & kirim email alert (TANPA WA)
        $validSignature = hash_hmac('sha256', $rawBody, 'rahasia-webhook-123');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_ACCEPT'               => 'application/json',
            'HTTP_X_VEXAHOST_EVENT'     => 'session.status',
            'HTTP_X_VEXAHOST_SIGNATURE' => $validSignature,
        ], $rawBody)->assertNoContent();

        $lastEvent = Cache::get(WhatsAppGateway::LAST_SESSION_EVENT_CACHE_KEY);
        $this->assertSame('disconnected', $lastEvent['status']);
        $this->assertSame('logged_out', $lastEvent['reason']);

        $recent = app(AdminAlertService::class)->recentAlerts();
        $this->assertNotEmpty($recent);
        $this->assertSame('whatsapp.session_disconnected', $recent[0]['event_key']);
        $this->assertTrue($recent[0]['email_sent']);
        $this->assertFalse($recent[0]['wa_sent']);

        // 4. Deduplikasi delivery_id: kiriman ulang diabaikan dengan 204 tanpa duplikat alert
        $beforeCount = count($recent);
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_ACCEPT'               => 'application/json',
            'HTTP_X_VEXAHOST_EVENT'     => 'session.status',
            'HTTP_X_VEXAHOST_SIGNATURE' => $validSignature,
        ], $rawBody)->assertNoContent();

        $this->assertCount($beforeCount, app(AdminAlertService::class)->recentAlerts());
        Http::assertNothingSent();
    }

    public function test_notifikasi_pelanggan_hanya_email_dan_database_tanpa_whatsapp(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.vexahostcloud.my.id',
            'whatsapp.key'     => 'key-no-cust-wa',
        ]);
        Http::fake();

        $user = $this->customer('081299998888');
        $spec = VpsSpec::first();
        $order = $this->createOrder($user, $spec, 'pending');

        $invoice = Invoice::create([
            'order_id'       => $order->id,
            'invoice_number' => 'INV-EMAILONLY-2026',
            'amount'         => 150000,
            'subtotal'       => 150000,
            'status'         => 'unpaid',
            'issued_at'      => now(),
            'due_at'         => now()->addDays(3),
        ]);

        $invNotif = new InvoiceCreatedNotification($invoice);
        $payNotif = new PaymentReceivedNotification($order, $invoice);

        $this->assertSame(['mail', 'database'], $invNotif->via($user));
        $this->assertSame(['mail', 'database'], $payNotif->via($user));

        $user->notify($invNotif);
        $user->notify($payNotif);

        // Pelanggan tidak pernah memicu HTTP ke WA Gateway.
        Http::assertNothingSent();
    }

    public function test_email_vps_provisioned_tidak_mengirim_password_plaintext(): void
    {
        $user = $this->customer('081299998888');
        $spec = VpsSpec::first();
        $order = $this->createOrder($user, $spec, 'active');

        $instance = VpsInstance::create([
            'customer_id'           => $user->id,
            'order_id'              => $order->id,
            'hostname'              => 'vps-sec.cravacloud.id',
            'public_ip'             => '103.150.190.10',
            'ssh_port'              => 22,
            'ssh_username'          => 'ubuntu',
            'initial_root_password' => 'SuperSecretPwd#2026',
            'os'                    => 'ubuntu2404',
            'control_panel'         => 'coolify',
            'app_url'               => 'http://103.150.190.10:8000',
            'status'                => 'running',
            'starts_at'             => now(),
            'expires_at'            => now()->addDays(30),
        ]);

        $notification = new VpsProvisionedNotification($instance);
        $this->assertSame(['mail', 'database'], $notification->via($user));

        $html = (string) $notification->toMail($user)->render();
        $this->assertStringNotContainsString('SuperSecretPwd#2026', $html);
        $this->assertStringContainsString('ssh ubuntu@103.150.190.10 -p 22', $html);
        $this->assertStringContainsString('tab Akses', $html);
        $this->assertStringContainsString('http://103.150.190.10:8000', $html);
    }

    public function test_admin_dapat_menyimpan_id_grup_whatsapp_untuk_notifikasi(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.notification'), [
                'admin_notification_whatsapp' => '081222333444, 120363012345678901@g.us',
                'fulfillment_sla_minutes'     => 120,
                'renewal_grace_days_vps'      => 3,
            ])
            ->assertSessionHas('success');

        $this->assertSame(
            ['6281222333444', '120363012345678901@g.us'],
            app(AdminAlertService::class)->adminWhatsAppNumbers()
        );
    }
}
