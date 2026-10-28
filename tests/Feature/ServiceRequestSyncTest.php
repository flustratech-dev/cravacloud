<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pastikan status permintaan layanan selalu sinkron antara dashboard pelanggan,
 * panel admin, dan email.
 */
class ServiceRequestSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    /**
     * @return array{0: User, 1: VpsInstance}
     */
    private function customerWithVps(array $vpsAttributes = []): array
    {
        $customer = User::create([
            'username' => 'synccustomer',
            'email' => 'synccustomer@example.com',
            'full_name' => 'Pelanggan Sinkron',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $customer->switchToOrganization($customer->createPersonalOrganization());
        $customer = $customer->fresh();

        $spec = VpsSpec::where('category', 'vps')->firstOrFail();
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'hostname' => 'srv-sync',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $spec->sell_price,
            'paid_at' => now(),
        ]);

        $vps = VpsInstance::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => 'srv-sync',
            'public_ip' => '139.180.200.50',
            'os' => 'ubuntu2404',
            'status' => 'running',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'initial_root_password' => 'PasswordAwal#123',
            'expires_at' => now()->addDays(20),
        ], $vpsAttributes));

        return [$customer, $vps];
    }

    private function requestReinstall(User $customer, VpsInstance $vps): SupportTicket
    {
        $this->actingAs($customer)->post("/dashboard/vps/{$vps->id}/requests/reinstall", [
            'os' => 'ubuntu2204',
            'control_panel' => 'dokploy',
            'confirm_hostname' => $vps->hostname,
        ])->assertSessionHas('success');

        return SupportTicket::where('vps_instance_id', $vps->id)->latest('id')->firstOrFail();
    }

    public function test_customer_thank_you_reply_does_not_revive_finished_reinstall(): void
    {
        [$customer, $vps] = $this->customerWithVps();
        $ticket = $this->requestReinstall($customer, $vps);

        $this->actingAs($this->admin())->post(route('admin.tickets.complete-reinstall', $ticket->id), [
            'os' => 'ubuntu2204',
            'control_panel' => 'dokploy',
            'root_password' => 'PasswordBaru#456',
        ])->assertSessionHas('success');

        // Pelanggan membalas "terima kasih" -> tiket terbuka lagi sebagai percakapan.
        $this->actingAs($customer)->post("/dashboard/support/{$ticket->id}/reply", ['message' => 'Terima kasih, sudah bisa login.']);
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertFalse($ticket->fresh()->isAwaitingTeam());

        // Dashboard tidak boleh menampilkan reinstall seolah masih diproses.
        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertDontSee('Reinstall Sedang Diproses')
            ->assertDontSee('Reinstall OS sedang')
            ->assertSee('Ajukan Reinstall OS');

        // Permintaan reinstall baru tetap bisa diajukan (tidak dialihkan ke tiket lama).
        $this->actingAs($customer)->post("/dashboard/vps/{$vps->id}/requests/reinstall", [
            'os' => 'debian12',
            'control_panel' => 'none',
            'confirm_hostname' => $vps->hostname,
        ])->assertRedirect(route('dashboard.vps.show', $vps->id));

        $this->assertSame(2, SupportTicket::where('type', SupportTicket::TYPE_REINSTALL)->count());
    }

    public function test_admin_reopening_request_shows_it_as_pending_again(): void
    {
        [$customer, $vps] = $this->customerWithVps();
        $ticket = $this->requestReinstall($customer, $vps);
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        // Admin membuka kembali lewat form balasan (status in_progress mengosongkan resolved_at).
        $this->actingAs($this->admin())->post(route('admin.tickets.reply', $ticket->id), [
            'message' => 'Kami kerjakan ulang reinstall-nya.',
            'status' => 'in_progress',
        ])->assertSessionHas('success');

        $this->assertTrue($ticket->fresh()->isAwaitingTeam());
        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertSee('Reinstall Sedang Diproses');
    }

    public function test_stopped_server_hides_ssh_reboot_guide_but_keeps_requests(): void
    {
        [$customer, $vps] = $this->customerWithVps(['status' => 'stopped']);

        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertDontSee('>Cara Reboot<', false)
            ->assertDontSee('Lihat Caranya')
            ->assertSee('Ajukan Reinstall OS')
            ->assertSee('Laporkan ke Tim');
    }

    public function test_admin_banner_link_lists_all_active_service_requests(): void
    {
        [$customer, $vps] = $this->customerWithVps();
        $reinstall = $this->requestReinstall($customer, $vps);
        $reinstall->update(['status' => 'in_progress']);
        $this->actingAs($customer)->post("/dashboard/vps/{$vps->id}/requests/unreachable");
        $this->actingAs($customer)->post('/dashboard/support', [
            'subject' => 'Tiket umum lain',
            'priority' => 'low',
            'message' => 'Pertanyaan umum tentang domain.',
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/tickets')
            ->assertOk()
            ->assertSee('2 permintaan server menunggu dikerjakan')
            // Di atribut HTML "&" di-escape menjadi "&amp;", jadi dibandingkan dalam bentuk ter-escape.
            ->assertSee(route('admin.tickets', ['status' => 'active', 'type' => 'service']));

        $this->actingAs($admin)->get(route('admin.tickets', ['status' => 'active', 'type' => 'service']))
            ->assertOk()
            ->assertSee('Permintaan Reinstall OS')
            ->assertSee('Server Tidak Bisa Diakses')
            ->assertDontSee('Tiket umum lain');
    }

    public function test_emails_show_wib_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 01:24:00', 'UTC'));

        $html = view('emails.new-login-alert', [
            'user' => (object) ['full_name' => 'Pelanggan Sinkron'],
            'activity' => (object) [
                'device_label' => 'Chrome di Windows',
                'ip_address' => '203.0.113.5',
                'created_at' => now(),
            ],
        ])->render();

        $this->assertStringContainsString('08:24 WIB', $html);
        $this->assertStringNotContainsString('01:24 WIB', $html);

        $received = view('emails.payment-received', $this->paymentReceivedData())->render();
        $this->assertStringContainsString('08:24 WIB', $received);
    }

    private function paymentReceivedData(): array
    {
        [$customer, $vps] = $this->customerWithVps();
        $order = Order::where('customer_id', $customer->id)->firstOrFail();
        $invoice = \App\Models\Invoice::create([
            'order_id' => $order->id,
            'organization_id' => $order->organization_id,
            'invoice_number' => 'INV-SYNC-0001',
            'amount' => $order->amount,
            'status' => 'paid',
            'issued_at' => now(),
            'due_at' => now()->addDay(),
            'paid_at' => now(),
        ]);

        return ['user' => $customer, 'order' => $order->load('vpsSpec'), 'invoice' => $invoice];
    }
}
