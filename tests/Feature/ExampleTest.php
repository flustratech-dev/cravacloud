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
            'provider' => 'tencent',
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
            'provider' => 'tencent',
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
        $response->assertSee('Student Basic');
        $response->assertSee('Mahasiswa Basic');
        $response->assertSee('Standard');
        $response->assertSee('Premium');
        $response->assertSee('210.000');
        $response->assertSee('90.000');
        $response->assertSee('Startup');
        $response->assertSee('Business');
        $response->assertSee('590.000');
    }

    public function test_order_page_returns_successful_response(): void
    {
        $response = $this->get('/checkout');
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Pesanan');
        $response->assertSee('Coolify');

        $businessSpec = VpsSpec::where('name', 'Business')->first();
        $this->assertNotNull($businessSpec);

        $responseBusiness = $this->get('/checkout/'.$businessSpec->id);
        $responseBusiness->assertStatus(200);
        $responseBusiness->assertSee('Business');
    }

    public function test_self_serve_order_flow_as_guest(): void
    {
        $spec = VpsSpec::where('name', 'Standard')->first();

        $response = $this->post('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
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
            'provider' => 'tencent',
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
            'provider' => 'tencent',
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

    public function test_api_contracts_shopee_process_order_ai_production_pro(): void
    {
        $adminKey = config('cravacloud.admin_api_key', 'cv_sec_k9f83n2x9v1b7a6d8e4f5c2b0e9a1d3f');

        $response = $this->withHeader('X-Admin-Key', $adminKey)
            ->postJson('/api/admin/shopee/process-order', [
                'shopee_order_id' => 'SHOPEE-BIZ-2026',
                'customer_name' => 'Business Customer Test',
                'customer_email' => 'business.customer@shopee.co.id',
                'package' => 'business',
                'control_panel' => 'ollama',
                'datacenter_location' => 'singapore',
                'os' => 'ubuntu2404',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $order = Order::where('shopee_order_id', 'SHOPEE-BIZ-2026')->first();
        $this->assertNotNull($order);
        $this->assertEquals('Business', $order->vpsSpec->name);
        $this->assertEquals('cloudeka', $order->provider);
        $this->assertEquals('indonesia', $order->datacenter_location);
        $this->assertEquals(8, $order->vpsSpec->cpu);
        $this->assertEquals(16, $order->vpsSpec->ram);
        $this->assertEquals(80, $order->vpsSpec->disk);
        $this->assertEquals(590000, (int) $order->vpsSpec->sell_price);
    }

    public function test_api_contracts_shopee_process_order_mahasiswa_basic(): void
    {
        $adminKey = config('cravacloud.admin_api_key', 'cv_sec_k9f83n2x9v1b7a6d8e4f5c2b0e9a1d3f');

        $response = $this->withHeader('X-Admin-Key', $adminKey)
            ->postJson('/api/admin/shopee/process-order', [
                'shopee_order_id' => 'SHOPEE-MHS-2026',
                'customer_name' => 'Mahasiswa Test Shopee',
                'customer_email' => 'mhs.shopee@test.id',
                'package' => 'mahasiswa basic',
                'control_panel' => 'docker',
                'datacenter_location' => 'singapore',
                'os' => 'ubuntu2404',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $order = Order::where('shopee_order_id', 'SHOPEE-MHS-2026')->first();
        $this->assertNotNull($order);
        $this->assertEquals('Mahasiswa Basic', $order->vpsSpec->name);
        $this->assertEquals('tencent', $order->provider);
        $this->assertEquals(2, $order->vpsSpec->cpu);
        $this->assertEquals(2, $order->vpsSpec->ram);
        $this->assertEquals(40, $order->vpsSpec->disk);
        $this->assertEquals(90000, (int) $order->vpsSpec->sell_price);
    }

    public function test_startup_plan_order_fails_with_tencent_provider(): void
    {
        $startupSpec = VpsSpec::where('name', 'Startup')->first();

        $response = $this->post('/checkout', [
            'vps_spec_id' => $startupSpec->id,
            'control_panel' => 'n8n',
            'provider' => 'tencent',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'startup-test-server',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Startup Customer Fail',
            'email' => 'startup.fail@customer.id',
            'phone' => '081233445566',
            'password' => 'SecurePass@123',
        ]);

        $response->assertSessionHasErrors('provider');
    }

    public function test_business_plan_order_succeeds_with_cloudeka_provider(): void
    {
        $businessSpec = VpsSpec::where('name', 'Business')->first();

        $response = $this->post('/checkout', [
            'vps_spec_id' => $businessSpec->id,
            'control_panel' => 'ollama',
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'business-server',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Business Customer Success',
            'email' => 'biz.success@customer.id',
            'phone' => '081233445577',
            'password' => 'SecurePass@123',
        ]);

        $order = Order::whereHas('customer', fn ($q) => $q->where('email', 'biz.success@customer.id'))->first();
        $this->assertNotNull($order);
        $this->assertEquals('cloudeka', $order->provider);
        $this->assertEquals('indonesia', $order->datacenter_location);
        $this->assertEquals($businessSpec->id, $order->vps_spec_id);
        $response->assertRedirect(route('order.payment', $order->id));
    }

    public function test_student_basic_fails_with_tencent_provider(): void
    {
        $spec = VpsSpec::where('name', 'Student Basic')->first();

        $response = $this->post('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'tencent',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'student-fail-server',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Student Fail',
            'email' => 'student.fail@customer.id',
            'phone' => '081233445588',
            'password' => 'SecurePass@123',
        ]);

        $response->assertSessionHasErrors('provider');
    }

    public function test_student_basic_succeeds_with_cloudeka_provider(): void
    {
        $spec = VpsSpec::where('name', 'Student Basic')->first();

        $response = $this->post('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'student-ok-server',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Student OK',
            'email' => 'student.ok@customer.id',
            'phone' => '081233445599',
            'password' => 'SecurePass@123',
        ]);

        $order = Order::whereHas('customer', fn ($q) => $q->where('email', 'student.ok@customer.id'))->first();
        $this->assertNotNull($order);
        $this->assertEquals('cloudeka', $order->provider);
        $this->assertEquals('indonesia', $order->datacenter_location);
        $response->assertRedirect(route('order.payment', $order->id));
    }

    public function test_mahasiswa_basic_fails_with_cloudeka_provider(): void
    {
        $spec = VpsSpec::where('name', 'Mahasiswa Basic')->first();

        $response = $this->post('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'mhs-fail-server',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Mahasiswa Fail',
            'email' => 'mhs.fail@customer.id',
            'phone' => '081233445511',
            'password' => 'SecurePass@123',
        ]);

        $response->assertSessionHasErrors('provider');
    }

    public function test_mahasiswa_basic_succeeds_with_tencent_provider(): void
    {
        $spec = VpsSpec::where('name', 'Mahasiswa Basic')->first();

        $response = $this->post('/checkout', [
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'hostname' => 'mhs-ok-server',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Mahasiswa OK',
            'email' => 'mhs.ok@customer.id',
            'phone' => '081233445522',
            'password' => 'SecurePass@123',
        ]);

        $order = Order::whereHas('customer', fn ($q) => $q->where('email', 'mhs.ok@customer.id'))->first();
        $this->assertNotNull($order);
        $this->assertEquals('tencent', $order->provider);
        $this->assertEquals('singapore', $order->datacenter_location);
        $response->assertRedirect(route('order.payment', $order->id));
    }

    public function test_standard_and_premium_fail_with_cloudeka(): void
    {
        $std = VpsSpec::where('name', 'Standard')->first();
        $prem = VpsSpec::where('name', 'Premium')->first();

        $res1 = $this->post('/checkout', [
            'vps_spec_id' => $std->id,
            'control_panel' => 'none',
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'std-fail',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Std Fail',
            'email' => 'std.fail@test.com',
            'phone' => '081299887711',
            'password' => 'SecurePass@123',
        ]);
        $res1->assertSessionHasErrors('provider');

        $res2 = $this->post('/checkout', [
            'vps_spec_id' => $prem->id,
            'control_panel' => 'none',
            'provider' => 'cloudeka',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'hostname' => 'prem-fail',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Prem Fail',
            'email' => 'prem.fail@test.com',
            'phone' => '081299887722',
            'password' => 'SecurePass@123',
        ]);
        $res2->assertSessionHasErrors('provider');
    }

    public function test_standard_and_premium_succeed_with_tencent(): void
    {
        $prem = VpsSpec::where('name', 'Premium')->first();

        $res = $this->post('/checkout', [
            'vps_spec_id' => $prem->id,
            'control_panel' => 'none',
            'provider' => 'tencent',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'hostname' => 'prem-ok',
            'root_password' => 'SecurePass@123',
            'terms_accepted' => 1,
            'payment_method' => 'qris',
            'full_name' => 'Prem OK',
            'email' => 'prem.ok@test.com',
            'phone' => '081299887733',
            'password' => 'SecurePass@123',
        ]);
        $res->assertSessionDoesntHaveErrors();
        $order = Order::whereHas('customer', fn ($q) => $q->where('email', 'prem.ok@test.com'))->first();
        $this->assertNotNull($order);
        $this->assertEquals('tencent', $order->provider);
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
        $indexRes->assertSee('Student Basic');
        $indexRes->assertSee('Startup');
        $indexRes->assertSee('Business');

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
        $corePlan = VpsSpec::where('name', 'Startup')->first();
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
