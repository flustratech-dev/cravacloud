<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Support\KatalogDokumentasi;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createCustomerWithVps(): array
    {
        $customer = User::create([
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'full_name' => 'Test Customer',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);

        $org = $customer->createPersonalOrganization();
        $customer->switchToOrganization($org);

        $spec = VpsSpec::first();

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'hostname' => 'vps-testuser',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $spec->sell_price,
            'paid_at' => now(),
        ]);

        $vps = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'hostname' => 'vps-testuser',
            'public_ip' => '139.180.200.10',
            'private_ip' => '10.0.1.10',
            'os' => 'Ubuntu 24.04 LTS',
            'status' => 'running',
            'cpu' => $spec->cpu,
            'ram' => $spec->ram,
            'disk' => $spec->disk,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'uptime_percent' => 99.98,
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'organization_id' => $org->id,
            'invoice_number' => 'INV-TEST-0001',
            'amount' => $spec->sell_price,
            'status' => 'paid',
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
            'paid_at' => now(),
        ]);

        return [$customer, $vps, $order, $invoice];
    }

    public function test_landing_page_returns_successful_response(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('CravaCloud');
        $response->assertSee('Starter XS');
        $response->assertSee('Starter S');
        $response->assertSee('Starter M');
        $response->assertSee('Starter L');
        $response->assertSee('115.000');
        $response->assertSee('165.000');
        $response->assertSee('Enterprise Pro');
        $response->assertSee('Enterprise Extreme');
        $response->assertSee('975.000');
        $response->assertSee('Pilih Paket');
        $response->assertDontSee('Deploy Server');
        $response->assertDontSee('DEPLOY CRAVACLOUD VPS');

        $starterXs = VpsSpec::where('name', 'Starter XS')->first();
        $this->assertNotNull($starterXs);
        $response->assertSee(route('checkout', $starterXs->id));
    }

    public function test_order_page_returns_successful_response(): void
    {
        $response = $this->get('/checkout');
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Pesanan');
        $response->assertSee('Coolify');

        $enterpriseSpec = VpsSpec::where('name', 'Enterprise Pro')->first();
        $this->assertNotNull($enterpriseSpec);

        $responseEnterprise = $this->get('/checkout/'.$enterpriseSpec->id);
        $responseEnterprise->assertStatus(200);
        $responseEnterprise->assertSee('Enterprise Pro');
    }

    public function test_self_serve_order_flow_as_guest(): void
    {
        $spec = VpsSpec::where('name', 'Starter S')->first();

        $response = $this->post('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'hostname' => 'my-new-guest-server',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Calon Pelanggan Baru',
            'email' => 'pelangganbaru@student.id',
            'phone' => '081299887766',
            'password' => 'SecurePass@123',
        ]);

        $order = Order::whereHas('customer', fn ($q) => $q->where('email', 'pelangganbaru@student.id'))->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('order.payment', $order->id));

        // Invoice status 'sent', order 'pending' awaiting payment & admin provision
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id, 'status' => 'sent']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_admin_can_login_with_username_or_email(): void
    {
        // By username
        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => '12345678',
        ]);
        $response->assertRedirect(route('admin.index'));

        // Logout
        $this->post('/logout');

        // By email
        $response2 = $this->post('/login', [
            'username' => 'admin@gmail.com',
            'password' => '12345678',
        ]);
        $response2->assertRedirect(route('admin.index'));
    }

    public function test_customer_dashboard_renders_for_authenticated_customer(): void
    {
        [$user] = $this->createCustomerWithVps();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('VPS Instances Saya');
    }

    public function test_customer_vps_detail_page(): void
    {
        [$user, $vps] = $this->createCustomerWithVps();

        $response = $this->actingAs($user)->get('/dashboard/vps/'.$vps->id);
        $response->assertStatus(200);
        $response->assertSee($vps->hostname);
        $response->assertSee($vps->public_ip);
    }

    public function test_customer_reboot_is_self_service_not_a_fake_button(): void
    {
        [$user, $vps] = $this->createCustomerWithVps();

        // Panel tidak lagi berpura-pura me-reboot server (tanpa API supplier).
        $this->actingAs($user)->post('/dashboard/vps/'.$vps->id.'/reboot')->assertNotFound();

        // Pelanggan diberi panduan reboot lewat SSH.
        $this->actingAs($user)->get('/dashboard/vps/'.$vps->id)
            ->assertOk()
            ->assertSee('sudo reboot');
    }

    public function test_customer_reinstall_is_submitted_as_request(): void
    {
        [$user, $vps] = $this->createCustomerWithVps();

        $response = $this->actingAs($user)->post('/dashboard/vps/'.$vps->id.'/requests/reinstall', [
            'os' => 'ubuntu2204',
            'control_panel' => 'dokploy',
            'confirm_hostname' => 'vps-testuser',
        ]);
        $response->assertSessionHas('success');

        // Data server baru berubah setelah admin menyelesaikan permintaan.
        $this->assertDatabaseHas('vps_instances', [
            'id' => $vps->id,
            'control_panel' => 'coolify',
        ]);
        $this->assertDatabaseHas('support_tickets', [
            'vps_instance_id' => $vps->id,
            'type' => 'reinstall',
            'status' => 'open',
        ]);
    }

    public function test_customer_billing_and_printable_invoice(): void
    {
        [$user, $vps, $order, $invoice] = $this->createCustomerWithVps();

        $response = $this->actingAs($user)->get('/dashboard/billing');
        $response->assertStatus(200);
        $response->assertSee($invoice->invoice_number);

        $printResponse = $this->actingAs($user)->get('/dashboard/invoices/'.$invoice->id.'/print');
        $printResponse->assertStatus(200);
        $printResponse->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $printResponse->getContent());

        $htmlResponse = $this->actingAs($user)->get('/dashboard/invoices/'.$invoice->id.'/print?format=html');
        $htmlResponse->assertStatus(200);
        $htmlResponse->assertSee($invoice->invoice_number);
        $htmlResponse->assertSee('FAKTUR / INVOICE');
    }

    public function test_customer_can_create_support_ticket_and_reply(): void
    {
        [$user] = $this->createCustomerWithVps();

        $response = $this->actingAs($user)->post('/dashboard/support', [
            'subject' => 'Kendala port firewall open',
            'priority' => 'medium',
            'message' => 'Apakah port 80 dan 443 sudah dibuka secara default di VPS saya?',
        ]);

        $response->assertSessionHas('success');
        $ticket = SupportTicket::where('customer_id', $user->id)->where('subject', 'Kendala port firewall open')->first();
        $this->assertNotNull($ticket);

        // Reply to ticket
        $replyResponse = $this->actingAs($user)->post('/dashboard/support/'.$ticket->id.'/reply', [
            'message' => 'Terima kasih, mohon konfirmasinya ya.',
        ]);
        $replyResponse->assertSessionHas('success');
    }

    public function test_customer_can_update_profile(): void
    {
        [$user] = $this->createCustomerWithVps();

        $response = $this->actingAs($user)->post('/dashboard/settings/profile', [
            'full_name' => 'Updated Customer Name',
            'phone' => '081234567899',
            'company' => 'New Company',
            'address' => 'New Address',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'full_name' => 'Updated Customer Name',
            'phone' => '081234567899',
        ]);
    }

    public function test_admin_panel_protected_for_regular_customers(): void
    {
        [$user] = $this->createCustomerWithVps();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_admin_panel_accessible_for_admin(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Ringkasan');
    }

    public function test_admin_views_orders_instances_customers_tickets(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();

        $this->actingAs($admin)->get('/admin/orders')->assertStatus(200)->assertSee('Antrean & Manajemen Order');
        $this->actingAs($admin)->get('/admin/shopee')->assertStatus(200)->assertSee('Proses Pesanan Shopee');
        $this->actingAs($admin)->get('/admin/instances')->assertStatus(200)->assertSee('Daftar VPS Instance Aktif');
        $this->actingAs($admin)->get('/admin/customers')->assertStatus(200)->assertSee('Manajemen Pelanggan');
        $this->actingAs($admin)->get('/admin/tickets')->assertStatus(200)->assertSee('Tiket Bantuan');
    }

    public function test_admin_can_provision_pending_order(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        [$customer] = $this->createCustomerWithVps();

        $pendingOrder = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => 1,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'hostname' => 'vps-new-order',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 80000,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post('/admin/orders/'.$pendingOrder->id.'/provision', [
            'public_ip' => '139.180.222.111',
            'app_url' => 'https://139.180.222.111:8000',
            'root_password' => 'CravaSecretPass123!',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('orders', ['id' => $pendingOrder->id, 'status' => 'active']);
        $this->assertDatabaseHas('vps_instances', ['customer_id' => $customer->id, 'public_ip' => '139.180.222.111']);
    }

    public function test_shopee_order_processing_by_admin(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $spec = VpsSpec::first();

        $response = $this->actingAs($admin)->post('/admin/shopee/process', [
            'shopee_order_id' => 'SHOPEE-TEST-999',
            'customer_name' => 'Testing Shopee Buyer',
            'customer_email' => 'buyer@testshopee.com',
            'customer_phone' => '089912345678',
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
        ]);

        $response->assertSessionHas('shopee_credentials');
        $this->assertDatabaseHas('users', ['email' => 'buyer@testshopee.com', 'channel' => 'shopee']);
        $this->assertDatabaseHas('orders', ['shopee_order_id' => 'SHOPEE-TEST-999', 'channel' => 'shopee']);
    }

    public function test_api_contracts_payment_webhook(): void
    {
        [$customer] = $this->createCustomerWithVps();

        $serverKey = 'midtrans_test_key_123';
        config(['services.midtrans.server_key' => $serverKey]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => 1,
            'control_panel' => 'coolify',
            'provider' => 'cravacloud',
            'hostname' => 'vps-webhook-test',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'amount' => 80000,
        ]);

        $orderId = (string) $order->id;
        $statusCode = '200';
        $grossAmount = '80000.00';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $response = $this->postJson('/api/webhooks/payment', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'TXN-9999',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertNotNull(Order::find($order->id)->paid_at);
    }

    public function test_api_admin_routes_reject_unauthorized_requests(): void
    {
        $response = $this->postJson('/api/admin/shopee/process-order', []);
        $response->assertStatus(401);
    }

    public function test_api_contracts_shopee_order_processing(): void
    {
        $adminKey = config('cravacloud.admin_api_key', 'cv_sec_k9f83n2x9v1b7a6d8e4f5c2b0e9a1d3f');

        $response = $this->withHeader('X-Admin-Key', $adminKey)
            ->postJson('/api/admin/shopee/process-order', [
                'shopee_order_id' => 'SHOPEE-API-2026',
                'customer_name' => 'API Customer Test',
                'customer_email' => 'api.customer@shopee.co.id',
                'package' => 'standard',
                'control_panel' => 'dokploy',
                'datacenter_location' => 'indonesia',
                'os' => 'ubuntu2404',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('users', ['email' => 'api.customer@shopee.co.id', 'channel' => 'shopee']);
    }

    public function test_api_contracts_shopee_process_order_enterprise_pro(): void
    {
        $adminKey = config('cravacloud.admin_api_key', 'cv_sec_k9f83n2x9v1b7a6d8e4f5c2b0e9a1d3f');

        $response = $this->withHeader('X-Admin-Key', $adminKey)
            ->postJson('/api/admin/shopee/process-order', [
                'shopee_order_id' => 'SHOPEE-BIZ-2026',
                'customer_name' => 'Enterprise Customer Test',
                'customer_email' => 'business.customer@shopee.co.id',
                'package' => 'enterprise-pro',
                'control_panel' => 'ollama',
                'datacenter_location' => 'singapore',
                'os' => 'ubuntu2404',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $order = Order::where('shopee_order_id', 'SHOPEE-BIZ-2026')->first();
        $this->assertNotNull($order);
        $this->assertEquals('Enterprise Pro', $order->vpsSpec->name);
        $this->assertEquals('cravacloud', $order->provider);
        $this->assertEquals('singapore', $order->datacenter_location);
        $this->assertEquals(4, $order->vpsSpec->cpu);
        $this->assertEquals(8, $order->vpsSpec->ram);
        $this->assertEquals(180, $order->vpsSpec->disk);
        $this->assertEquals(975000, (int) $order->vpsSpec->sell_price);
    }

    public function test_api_contracts_shopee_process_order_starter_xs(): void
    {
        $adminKey = config('cravacloud.admin_api_key', 'cv_sec_k9f83n2x9v1b7a6d8e4f5c2b0e9a1d3f');

        $response = $this->withHeader('X-Admin-Key', $adminKey)
            ->postJson('/api/admin/shopee/process-order', [
                'shopee_order_id' => 'SHOPEE-MHS-2026',
                'customer_name' => 'Starter Test Shopee',
                'customer_email' => 'mhs.shopee@test.id',
                'package' => 'starter-xs',
                'control_panel' => 'docker',
                'datacenter_location' => 'singapore',
                'os' => 'ubuntu2404',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $order = Order::where('shopee_order_id', 'SHOPEE-MHS-2026')->first();
        $this->assertNotNull($order);
        $this->assertEquals('Starter XS', $order->vpsSpec->name);
        $this->assertEquals(2, $order->vpsSpec->cpu);
        $this->assertEquals(2, $order->vpsSpec->ram);
        $this->assertEquals(40, $order->vpsSpec->disk);
        $this->assertEquals(115000, (int) $order->vpsSpec->sell_price);
    }

    public function test_starter_plan_checkout_succeeds_without_provider_in_indonesia_and_singapore(): void
    {
        $starterXs = VpsSpec::where('name', 'Starter XS')->first();
        $starterS = VpsSpec::where('name', 'Starter S')->first();

        $resId = $this->post('/checkout', [
            'vps_spec_id' => $starterXs->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'starter-xs-id',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Starter XS Indonesia',
            'email' => 'starter.xs.id@customer.id',
            'phone' => '081233445588',
            'password' => 'SecurePass@123',
        ]);

        $orderId = Order::whereHas('customer', fn ($q) => $q->where('email', 'starter.xs.id@customer.id'))->first();
        $this->assertNotNull($orderId);
        $this->assertEquals('cravacloud', $orderId->provider);
        $this->assertEquals('indonesia', $orderId->datacenter_location);
        $resId->assertSessionDoesntHaveErrors();
        $resId->assertRedirect(route('order.payment', $orderId->id));

        auth()->logout();
        session()->flush();

        $resSg = $this->post('/checkout', [
            'vps_spec_id' => $starterS->id,
            'control_panel' => 'coolify',
            'datacenter_location' => 'singapore',
            'os' => 'debian12',
            'hostname' => 'starter-s-sg',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Starter S Singapore',
            'email' => 'starter.s.sg@customer.id',
            'phone' => '081233445599',
            'password' => 'SecurePass@123',
        ]);

        $orderSg = Order::whereHas('customer', fn ($q) => $q->where('email', 'starter.s.sg@customer.id'))->first();
        $this->assertNotNull($orderSg);
        $this->assertEquals('cravacloud', $orderSg->provider);
        $this->assertEquals('singapore', $orderSg->datacenter_location);
        $resSg->assertRedirect(route('order.payment', $orderSg->id));
    }

    public function test_enterprise_plan_checkout_succeeds_with_cravacloud_provider(): void
    {
        $spec = VpsSpec::where('name', 'Enterprise Pro')->first();

        $response = $this->post('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'ollama',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'enterprise-pro-srv',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Enterprise Pro Customer',
            'email' => 'biz.success@customer.id',
            'phone' => '081233445577',
            'password' => 'SecurePass@123',
        ]);

        $order = Order::whereHas('customer', fn ($q) => $q->where('email', 'biz.success@customer.id'))->first();
        $this->assertNotNull($order);
        $this->assertEquals('cravacloud', $order->provider);
        $this->assertEquals('indonesia', $order->datacenter_location);
        $this->assertEquals($spec->id, $order->vps_spec_id);
        $response->assertRedirect(route('order.payment', $order->id));
    }

    public function test_public_and_checkout_pages_contain_zero_tencent_or_cloudeka_references(): void
    {
        $starterXs = VpsSpec::where('name', 'Starter XS')->first();

        foreach (['/', '/vps', '/checkout', '/checkout/' . $starterXs->id, '/docs'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsStringIgnoringCase('tencent', $html, "Found tencent in {$url}");
            $this->assertStringNotContainsStringIgnoringCase('cloudeka', $html, "Found cloudeka in {$url}");
            $this->assertStringNotContainsStringIgnoringCase('lintasarta', $html, "Found lintasarta in {$url}");
        }
    }

    public function test_api_contracts_vps_status(): void
    {
        [$customer, $vps] = $this->createCustomerWithVps();
        $adminKey = config('cravacloud.admin_api_key', 'cv_sec_k9f83n2x9v1b7a6d8e4f5c2b0e9a1d3f');

        $response = $this->withHeader('X-Admin-Key', $adminKey)
            ->getJson('/api/admin/vps/'.$vps->id.'/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id', 'hostname', 'public_ip', 'status', 'cpu', 'ram', 'disk', 'os', 'control_panel', 'billing_cycle', 'last_check',
        ]);
    }

    public function test_admin_packages_crud_and_core_protection(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();

        // 1. Index page view
        $indexRes = $this->actingAs($admin)->get('/admin/packages');
        $indexRes->assertStatus(200);
        $indexRes->assertSee('Katalog Spesifikasi Cloud VPS');
        $indexRes->assertSee('Starter XS');
        $indexRes->assertSee('Starter L');
        $indexRes->assertSee('Enterprise Pro');

        // 2. Store new custom package
        $storeRes = $this->actingAs($admin)->post('/admin/packages', [
            'name' => 'Custom Developer Node',
            'cpu' => 6,
            'ram' => 12,
            'disk' => 120,
            'bandwidth' => 3000,
            'cost_price' => 200000,
            'sell_price' => 320000,
            'is_active' => '1',
        ]);
        $storeRes->assertSessionHas('success');
        $this->assertDatabaseHas('vps_specs', ['name' => 'Custom Developer Node', 'sell_price' => 320000]);

        $customSpec = VpsSpec::where('name', 'Custom Developer Node')->first();
        $this->assertNotNull($customSpec);

        // 3. Update custom package
        $updateRes = $this->actingAs($admin)->put('/admin/packages/'.$customSpec->id, [
            'name' => 'Custom Developer Node v2',
            'cpu' => 8,
            'ram' => 16,
            'disk' => 160,
            'bandwidth' => 4000,
            'cost_price' => 250000,
            'sell_price' => 390000,
            'is_active' => '1',
        ]);
        $updateRes->assertSessionHas('success');
        $this->assertDatabaseHas('vps_specs', ['name' => 'Custom Developer Node v2', 'cpu' => 8]);

        // 4. Core plans protection against deletion
        $corePlan = VpsSpec::where('name', 'Starter L')->first();
        $this->assertNotNull($corePlan);
        $delCoreRes = $this->actingAs($admin)->delete('/admin/packages/'.$corePlan->id);
        $delCoreRes->assertSessionHas('error');
        $this->assertDatabaseHas('vps_specs', ['id' => $corePlan->id]);

        // 5. Delete custom package without orders succeeds
        $customSpec->refresh();
        $delCustomRes = $this->actingAs($admin)->delete('/admin/packages/'.$customSpec->id);
        $delCustomRes->assertSessionHas('success');
        $this->assertDatabaseMissing('vps_specs', ['name' => 'Custom Developer Node v2']);
    }

    public function test_docs_pages_render_enterprise_portal(): void
    {
        // Dokumentasi sekarang terpecah menjadi indeks + satu halaman per
        // kelompok. Pemeriksaannya berjalan atas gabungan seluruhnya: yang
        // dijaga di sini isinya, bukan di halaman mana ia kebetulan berada.
        $halaman = ['/docs'];
        foreach (array_keys(KatalogDokumentasi::kelompok()) as $slug) {
            $halaman[] = "/docs/{$slug}";
        }

        $gabungan = '';
        foreach ($halaman as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
            $gabungan .= $response->getContent();
        }

        $this->get('/docs')->assertSee('Dokumentasi Teknis');

        foreach ([
            'Pengenalan Arsitektur Cloud',
            'Coolify Platform Deployment',
            'Dokploy Modern PaaS',
            'aaPanel',
            'Traditional Stack',
            'Penyimpanan NVMe SSD',
            // Panduan operasional sesuai alur dashboard saat ini.
            'sudo reboot',
            'Ajukan Reinstall OS',
            '/api/admin/vps/{id}/status',
            '/api/admin/shopee/process-order',
        ] as $wajibAda) {
            $this->assertStringContainsString($wajibAda, $gabungan);
        }

        foreach ([
            // Klaim performa yang tidak bisa dibuktikan sudah dihapus.
            'Storage Durability',
            'Random Read IOPS',
            'siaga 24/7',
            'Graceful Shutdown',
            // Tidak ada emoji di dokumentasi teknis.
            "\u{1F680}",
            "\u{2699}\u{FE0F}",
            "\u{1F6E1}\u{FE0F}",
            "\u{1F50C}",
            "\u{2753}",
        ] as $tidakBolehAda) {
            $this->assertStringNotContainsString($tidakBolehAda, $gabungan);
        }
    }

    /**
     * Tautan lama berbentuk /docs#stack-coolify sudah tersebar di riwayat chat
     * dan bookmark pelanggan. Anchor tidak dikirim ke server, jadi halaman
     * indeks yang harus mengantarkannya — kalau petanya hilang, tautan itu
     * berakhir di indeks tanpa penjelasan apa pun.
     */
    public function test_old_documentation_anchors_still_lead_somewhere(): void
    {
        $peta = KatalogDokumentasi::petaSeksi();

        $this->assertSame('control-panel', $peta['stack-coolify']);
        $this->assertCount(21, $peta);

        $this->get('/docs')->assertSee('peta-seksi-dokumentasi', false);
    }
}
