<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\ImpersonationLog;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsSpec;
use App\Notifications\NewLoginAlertNotification;
use App\Services\ImpersonationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PHASE 7 - Impersonation dan Audit Log.
 *
 * Fase dengan risiko tertinggi karena menyentuh autentikasi. Tes di sini
 * sengaja mencoba menyalahgunakan sesi impersonation dari berbagai arah.
 */
class ImpersonationAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
    }

    protected function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    protected function customer(): User
    {
        $user = User::create([
            'username' => 'imp' . uniqid(),
            'email' => 'imp' . uniqid() . '@test.id',
            'full_name' => 'Pelanggan Diwakili',
            'password' => Hash::make('KataSandiAsli123'),
            'channel' => 'website',
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return $user->fresh();
    }

    protected function startImpersonating(User $customer)
    {
        return $this->actingAs($this->admin())
            ->withSession(['2fa.passed' => true])
            ->post("/admin/customers/{$customer->id}/impersonate");
    }

    // ======================= Memulai =======================

    public function test_admin_can_impersonate_customer(): void
    {
        $customer = $this->customer();

        $this->startImpersonating($customer)->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($customer);
        $this->assertNotNull(session(ImpersonationService::SESSION_KEY));

        $log = ImpersonationLog::first();
        $this->assertSame($this->admin()->id, $log->admin_user_id);
        $this->assertSame($customer->id, $log->impersonated_user_id);
        $this->assertNull($log->ended_at);

        // Audit dicatat atas nama ADMIN, bukan pelanggan yang sedang login.
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'impersonation.started',
            'admin_user_id' => $this->admin()->id,
            'subject_id' => $customer->id,
        ]);
    }

    public function test_customer_panel_shows_banner(): void
    {
        $customer = $this->customer();
        $this->startImpersonating($customer);

        $this->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Masuk sebagai ' . $customer->full_name)
            ->assertSee('Kembali ke Admin');
    }

    public function test_cannot_impersonate_another_admin(): void
    {
        $otherAdmin = User::create([
            'username' => 'admin2' . uniqid(),
            'email' => 'admin2' . uniqid() . '@test.id',
            'full_name' => 'Admin Kedua',
            'password' => Hash::make('password123'),
        ]);
        $otherAdmin->forceFill(['is_admin' => true])->save();

        $this->startImpersonating($otherAdmin)->assertSessionHas('error');

        $this->assertAuthenticatedAs($this->admin());
        $this->assertSame(0, ImpersonationLog::count());
    }

    public function test_customer_cannot_start_impersonation(): void
    {
        $a = $this->customer();
        $b = $this->customer();

        $this->actingAs($a)->post("/admin/customers/{$b->id}/impersonate")->assertStatus(403);
        $this->assertSame(0, ImpersonationLog::count());
    }

    public function test_login_alert_is_not_sent_to_customer(): void
    {
        $customer = $this->customer();
        $this->startImpersonating($customer);

        Notification::assertNotSentTo($customer, NewLoginAlertNotification::class);
    }

    public function test_customer_two_factor_does_not_block_admin(): void
    {
        $customer = $this->customer();
        $customer->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->startImpersonating($customer);

        $this->get('/dashboard')->assertStatus(200);
    }

    // ======================= Pemblokiran aksi =======================

    public function test_password_change_is_blocked(): void
    {
        $customer = $this->customer();
        $originalHash = $customer->password;
        $this->startImpersonating($customer);

        $this->from('/dashboard/settings')->post('/dashboard/settings/password', [
            'current_password' => 'KataSandiAsli123',
            'password' => 'KataSandiBaru999',
            'password_confirmation' => 'KataSandiBaru999',
        ])->assertSessionHas('error');

        $this->assertSame($originalHash, $customer->fresh()->password);
    }

    public function test_checkout_on_behalf_of_customer_is_blocked(): void
    {
        $customer = $this->customer();
        $this->startImpersonating($customer);

        $this->postJson('/checkout', [
            'vps_spec_id' => VpsSpec::first()->id,
            'payment_method' => 'qris',
            'terms_accepted' => 1,
        ])->assertStatus(403)->assertJson(['impersonating' => true]);

        $this->assertSame(0, Order::where('customer_id', $customer->id)->count());
    }

    public function test_replying_ticket_as_customer_is_blocked(): void
    {
        $customer = $this->customer();
        $this->startImpersonating($customer);

        $this->post('/dashboard/support', ['subject' => 'Uji', 'message' => 'Isi', 'priority' => 'low'])
            ->assertSessionHas('error');

        $this->assertSame(0, SupportTicket::count());
        // Membuktikan penolakan datang dari penjaga impersonation, bukan dari validasi form.
        $this->assertSame(1, ImpersonationLog::first()->blocked_actions);
    }

    public function test_blocked_actions_are_counted(): void
    {
        $customer = $this->customer();
        $this->startImpersonating($customer);

        $this->post('/dashboard/settings/profile', ['full_name' => 'Diubah Admin']);
        $this->post('/security/api-keys', ['name' => 'kunci']);

        $this->assertSame(2, ImpersonationLog::first()->blocked_actions);
        $this->assertSame('Pelanggan Diwakili', $customer->fresh()->full_name);
    }

    public function test_nested_impersonation_is_denied(): void
    {
        $a = $this->customer();
        $b = $this->customer();
        $this->startImpersonating($a);

        $this->post("/admin/customers/{$b->id}/impersonate");

        $this->assertAuthenticatedAs($a);
        $this->assertSame(1, ImpersonationLog::count());
    }

    // ======================= Mengakhiri =======================

    public function test_leave_restores_admin_session(): void
    {
        $customer = $this->customer();
        $this->startImpersonating($customer);

        $this->post('/impersonate/leave')->assertRedirect(route('admin.customers'));

        $this->assertAuthenticatedAs($this->admin());
        $this->assertNull(session(ImpersonationService::SESSION_KEY));

        $log = ImpersonationLog::first();
        $this->assertNotNull($log->ended_at);
        $this->assertSame('left', $log->end_reason);

        $this->get('/admin')->assertStatus(200);
    }

    public function test_leave_restores_admin_two_factor_state(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())
            ->withSession(['2fa.passed' => false])
            ->post("/admin/customers/{$customer->id}/impersonate");

        $this->assertTrue(session('2fa.passed'));

        $this->post('/impersonate/leave');

        // Status 2FA pelanggan tidak boleh terbawa ke sesi admin.
        $this->assertFalse(session('2fa.passed'));
    }

    public function test_session_expires_automatically(): void
    {
        $customer = $this->customer();
        $this->startImpersonating($customer);

        $this->travel(ImpersonationService::MAX_MINUTES + 1)->minutes();

        $this->get('/dashboard')->assertRedirect(route('admin.customers'));

        $this->assertAuthenticatedAs($this->admin());
        $this->assertSame('expired', ImpersonationLog::first()->end_reason);
    }

    public function test_logout_during_impersonation_closes_log(): void
    {
        $customer = $this->customer();
        $this->startImpersonating($customer);

        $this->post('/logout');

        $this->assertGuest();
        $this->assertSame('logout', ImpersonationLog::first()->end_reason);
    }

    public function test_revoked_admin_is_not_restored(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();
        $this->startImpersonating($customer);

        // Hak admin dicabut saat sesi masih berjalan.
        $admin->forceFill(['is_admin' => false])->save();

        $this->post('/impersonate/leave')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_leave_without_impersonation_is_harmless(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->post('/impersonate/leave')->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($customer);
    }

    // ======================= Audit log =======================

    public function test_audit_page_tabs_render(): void
    {
        $customer = $this->customer();
        $this->startImpersonating($customer);
        $this->post('/impersonate/leave');

        foreach (['admin', 'login', 'impersonation'] as $tab) {
            $this->get('/admin/audit-logs?tab=' . $tab)->assertStatus(200);
        }

        $this->get('/admin/audit-logs?tab=impersonation')
            ->assertSee($customer->email)
            ->assertSee('Kembali ke admin');
    }

    public function test_audit_filters_by_action_group(): void
    {
        AdminAuditLog::create(['admin_user_id' => $this->admin()->id, 'action' => 'billing.refund_created', 'description' => 'Catatan billing uji']);
        AdminAuditLog::create(['admin_user_id' => $this->admin()->id, 'action' => 'settings.brand_updated', 'description' => 'Catatan pengaturan uji']);

        $this->actingAs($this->admin())
            ->get('/admin/audit-logs?tab=admin&action=billing')
            ->assertSee('Catatan billing uji')
            ->assertDontSee('Catatan pengaturan uji');
    }

    public function test_customer_cannot_view_audit_log(): void
    {
        $this->actingAs($this->customer())->get('/admin/audit-logs')->assertStatus(403);
    }
}
