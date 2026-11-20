<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Security\TwoFactorService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientPortalViewsSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_client_portal_security_and_organization_views_render_without_errors_or_dark_theme(): void
    {
        $user = User::create([
            'username' => 'smokecustomer',
            'email' => 'smoke@cravacloud.test',
            'full_name' => 'Smoke Customer',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);

        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);
        $user = $user->fresh();

        $ticket = SupportTicket::create([
            'customer_id' => $user->id,
            'organization_id' => $org->id,
            'subject' => 'Uji Tiket Render Portal',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $routes = [
            route('dashboard.index'),
            route('dashboard.billing'),
            route('dashboard.support'),
            route('dashboard.support.show', $ticket->id),
            route('dashboard.settings'),
            route('organizations.index'),
            route('organizations.create'),
            route('organizations.show', $org->id),
            route('security.settings'),
            route('security.sessions'),
            route('security.login-activity'),
            route('security.two-factor.enable'),
        ];

        foreach ($routes as $url) {
            $res = $this->actingAs($user)->get($url);
            $res->assertOk();
            $this->assertStringNotContainsString('dark:bg-', $res->getContent(), "Found leftover dark class on {$url}");
            $res->assertDontSee('Tambah AI Agent');
            $res->assertDontSee('Tambah Database');
            $res->assertDontSee('Ekosistem');
            $res->assertDontSee('WA Gateway');
            $res->assertDontSee('Jasa Web');
            $res->assertDontSee('https://wa.cravacloud.id');
            $res->assertDontSee('https://build.cravacloud.id');
        }

        // Recovery codes requires 2FA enabled
        $twoFa = app(TwoFactorService::class);
        $secret = $twoFa->generateSecret();
        $twoFa->enable($user, $secret);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $recoveryRes = $this->actingAs($user->fresh())
            ->withSession(['2fa.passed' => true])
            ->get(route('security.recovery-codes'));
        $recoveryRes->assertOk();
        $recoveryRes->assertSee('Recovery codes');
        $recoveryRes->assertSee('name="_token"', false);

        // Default checkout preselects default VPS plan without an in-form package selector
        $checkoutRes = $this->get(route('checkout'));
        $checkoutRes->assertOk();
        $checkoutRes->assertSee('Starter XS');
        $checkoutRes->assertDontSee('checkout-package-spec', false);
        $checkoutRes->assertDontSee('DB Micro —');
        $checkoutRes->assertDontSee('Terminal Coding Agent —');
    }

    public function test_all_admin_panel_views_render_without_errors_or_dark_theme(): void
    {
        $admin = User::where('is_admin', true)->first();
        $this->assertNotNull($admin);

        $customer = User::create([
            'username' => 'adminsmoke_cust',
            'email' => 'adminsmoke_cust@cravacloud.test',
            'full_name' => 'Admin Smoke Customer',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $customer->createPersonalOrganization();
        $customer->switchToOrganization($org);

        $spec = \App\Models\VpsSpec::first();
        $order = \App\Models\Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cravacloud',
            'hostname' => 'smoke-admin-ord',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'amount' => $spec->sell_price,
            'status' => 'pending',
            'payment_method' => 'qris',
            'channel' => 'website',
        ]);

        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'subject' => 'Uji Tiket Admin Smoke',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $adminRoutes = [
            route('admin.index'),
            route('admin.payments'),
            route('admin.orders'),
            route('admin.orders.history', $order->id),
            route('admin.shopee'),
            route('admin.instances'),
            route('admin.monitoring'),
            route('admin.reports'),
            route('admin.packages.index'),
            route('admin.customers'),
            route('admin.tickets'),
            route('admin.tickets.show', $ticket->id),
            route('admin.settings.index'),
            route('admin.maintenance.index'),
            route('admin.abuse.index'),
            route('admin.templates.index'),
            route('admin.broadcast.index'),
            route('admin.whatsapp.index'),
            route('admin.gateways.index'),
            route('admin.webhooks.index'),
            route('admin.fulfillment.index'),
            route('admin.billing.index'),
            route('admin.audit.index'),
            route('admin.supplier.index'),
        ];

        foreach ($adminRoutes as $url) {
            $res = $this->actingAs($admin)->get($url);
            $res->assertOk();
            $html = $res->getContent();
            $this->assertStringNotContainsString('dark:bg-', $html, "Found leftover dark class on {$url}");
            $this->assertStringNotContainsString('rounded-lg', $html, "Found rounded-lg on brutalist admin view {$url}");
            $this->assertStringContainsString('images/logo.svg', $html, "Missing SVG logo on {$url}");
        }

        // Inactive spec with 0 orders is excluded from /admin distribution & accent defaults to #001AFF
        $retired = \App\Models\VpsSpec::create([
            'name' => 'Legacy Discontinued Pack',
            'category' => 'vps',
            'cpu' => 1,
            'ram' => 1,
            'disk' => 10,
            'bandwidth' => 10,
            'cost_price' => 50000,
            'sell_price' => 75000,
            'is_active' => false,
        ]);
        $this->actingAs($admin)->get(route('admin.index'))
            ->assertOk()
            ->assertDontSee($retired->name)
            ->assertSee('Jakarta (DCI-ID)');
        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('#001AFF');
    }
}
