<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\VpsProvisionedNotification;
use App\Services\WhatsAppGateway;
use App\Services\WhatsAppSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminWhatsAppGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        WhatsAppSettings::flush();
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

    public function test_admin_dapat_melihat_halaman_whatsapp_gateway(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.whatsapp.index'));

        $response->assertOk();
        $response->assertSee('WhatsApp Gateway');
        $response->assertSee('Status Koneksi WhatsApp Gateway');
        $response->assertSee('Kredensial &amp; Konfigurasi Gateway', false);
        $response->assertSee('Uji Kirim Pesan WhatsApp');
        $response->assertSee(json_encode(route('admin.whatsapp.status')), false);
    }

    public function test_admin_dapat_menyimpan_kredensial_dan_api_key_tersimpan_terenkripsi(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.whatsapp.settings'), [
            'wa_enabled' => '1',
            'wa_url'     => 'https://wa.cravacloud.id',
            'wa_key'     => 'rahasia-api-key-12345',
            'wa_session' => 'session_utama',
            'wa_timeout' => 15,
        ]);

        $response->assertRedirect(route('admin.whatsapp.index'));
        $response->assertSessionHas('success');

        // Pastikan tersimpan di basis data tabel settings
        $keyRow = Setting::where('key', 'wa_key')->first();
        $this->assertNotNull($keyRow);
        $this->assertNotEquals('rahasia-api-key-12345', $keyRow->value); // Harus terenkripsi, bukan plain text!
        $this->assertSame('rahasia-api-key-12345', Crypt::decryptString($keyRow->value));

        // Pastikan config ter-apply
        WhatsAppSettings::flush();
        WhatsAppSettings::apply();

        $this->assertTrue(config('whatsapp.enabled'));
        $this->assertSame('https://wa.cravacloud.id', config('whatsapp.url'));
        $this->assertSame('rahasia-api-key-12345', config('whatsapp.key'));
        $this->assertSame('session_utama', config('whatsapp.session'));
        $this->assertSame(15, config('whatsapp.timeout'));
    }

    public function test_admin_dapat_mereset_pengaturan_agar_kembali_ke_default(): void
    {
        $admin = $this->admin();

        // Simpan dulu ke DB
        WhatsAppSettings::save([
            'wa_url' => 'https://custom-gateway.id',
        ]);

        $this->assertDatabaseHas('settings', ['key' => 'wa_url']);

        // Reset kolom wa_url
        $response = $this->actingAs($admin)->post(route('admin.whatsapp.settings.forget'), [
            'field' => 'wa_url',
        ]);

        $response->assertRedirect(route('admin.whatsapp.index'));
        $this->assertDatabaseMissing('settings', ['key' => 'wa_url']);
    }

    public function test_endpoint_status_live_mengembalikan_informasi_gateway(): void
    {
        config([
            'whatsapp.url' => 'https://wa.cravacloud.id',
            'whatsapp.key' => 'dummy-api-key',
        ]);

        Http::fake([
            'https://wa.cravacloud.id/api/v1/health' => Http::response([
                'status' => 'ok',
                'data'   => [
                    'workspace' => 'CravaCloud',
                    'sessions'  => ['total' => 2, 'connected' => 1],
                    'usage'     => ['messages_sent' => 150, 'messages_failed' => 2],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.whatsapp.status'));

        $response->assertOk();
        $response->assertJson([
            'status'    => 'ready',
            'workspace' => 'CravaCloud',
            'sessions'  => [
                'total'     => 2,
                'connected' => 1,
            ],
            'usage' => [
                'messages_sent'   => 150,
                'messages_failed' => 2,
            ],
        ]);
    }

    public function test_uji_kirim_pesan_sukses(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.cravacloud.id',
            'whatsapp.key'     => 'test-key-valid',
            'whatsapp.session' => 'sess_1',
        ]);

        Http::fake([
            'https://wa.cravacloud.id/api/v1/messages/text' => Http::response([
                'success' => true,
                'data'    => [
                    'id'      => 'msg_test_999',
                    'status'  => 'queued',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin())->postJson(route('admin.whatsapp.test'), [
            'phone'   => '081234567890',
            'message' => 'Tes pesan dari CravaCloud',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status'     => 'ok',
            'to'         => '6281234567890',
            'message_id' => 'msg_test_999',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://wa.cravacloud.id/api/v1/messages/text'
                && $request['to'] === '6281234567890'
                && $request['message'] === 'Tes pesan dari CravaCloud'
                && $request->header('X-Api-Key')[0] === 'test-key-valid';
        });
    }

    public function test_normalisasi_nomor_telepon(): void
    {
        $this->assertSame('6281234567890', WhatsAppGateway::normalize('081234567890'));
        $this->assertSame('6281234567890', WhatsAppGateway::normalize('+6281234567890'));
        $this->assertSame('6281234567890', WhatsAppGateway::normalize('6281234567890'));
        $this->assertSame('6281234567890', WhatsAppGateway::normalize('0812-3456-7890'));
        $this->assertNull(WhatsAppGateway::normalize(''));
        $this->assertNull(WhatsAppGateway::normalize('12345'));
    }

    public function test_notifikasi_pelanggan_hanya_email_dan_database_tanpa_whatsapp(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url'     => 'https://wa.cravacloud.id',
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

    public function test_whatsapp_controller_mencatat_audit_log(): void
    {
        $admin = $this->admin();

        Http::fake([
            'https://wa.cravacloud.id/api/v1/messages/text' => Http::response([
                'success' => true,
                'data'    => ['id' => 'msg_audit_1'],
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.whatsapp.settings'), [
            'wa_enabled' => '1',
            'wa_url'     => 'https://wa.cravacloud.id',
            'wa_key'     => 'audit-secret-key',
            'wa_session' => 'crava-ops',
            'wa_timeout' => 15,
        ])->assertRedirect(route('admin.whatsapp.index'));

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action'        => 'whatsapp.settings_updated',
        ]);

        WhatsAppSettings::apply();

        $this->actingAs($admin)->postJson(route('admin.whatsapp.test'), [
            'phone'   => '081234567890',
            'message' => 'Tes audit',
        ])->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action'        => 'whatsapp.test_sent',
        ]);
    }
}
