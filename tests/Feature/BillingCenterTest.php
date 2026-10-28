<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Refund;
use App\Models\SupplierPurchase;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\VpsSpec;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\MarginService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PHASE 6 - Billing Center.
 */
class BillingCenterTest extends TestCase
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
            'username' => 'bl' . uniqid(),
            'email' => 'bl' . uniqid() . '@test.id',
            'full_name' => 'Pelanggan Billing',
            'password' => bcrypt('password123'),
            'channel' => 'website',
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return $user;
    }

    /**
     * Order lunas beserta fakturnya.
     */
    protected function paidInvoice(float $amount = 100000, array $orderAttrs = []): Invoice
    {
        $customer = $this->customer();

        $order = Order::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => VpsSpec::first()->id,
            'control_panel' => 'none',
            'provider' => 'tencent',
            'hostname' => 'bl-' . uniqid(),
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $amount,
            'paid_at' => now(),
        ], $orderAttrs));

        return Invoice::create([
            'order_id' => $order->id,
            'organization_id' => $order->organization_id,
            'invoice_number' => 'INV-UJI-' . strtoupper(uniqid()),
            'amount' => $amount,
            'status' => 'paid',
            'issued_at' => now(),
            'paid_at' => now(),
        ]);
    }

    // ======================= Halaman =======================

    public function test_every_tab_renders(): void
    {
        $this->paidInvoice();

        foreach (['invoices', 'subscriptions', 'refunds', 'coupons', 'taxes', 'credits', 'margin'] as $tab) {
            $this->actingAs($this->admin())->get('/admin/billing?tab=' . $tab)->assertStatus(200);
        }
    }

    public function test_unknown_tab_falls_back_to_invoices(): void
    {
        $this->actingAs($this->admin())->get('/admin/billing?tab=ngawur')
            ->assertStatus(200)
            ->assertSee('Daftar Faktur');
    }

    public function test_invoice_search_filters_results(): void
    {
        $invoice = $this->paidInvoice();
        $other = $this->paidInvoice();

        $this->actingAs($this->admin())
            ->get('/admin/billing?tab=invoices&q=' . $invoice->invoice_number)
            ->assertSee($invoice->invoice_number)
            ->assertDontSee($other->invoice_number);
    }

    public function test_customer_cannot_access_billing_center(): void
    {
        $this->actingAs($this->customer())->get('/admin/billing')->assertStatus(403);
    }

    public function test_admin_can_resend_invoice(): void
    {
        $invoice = $this->paidInvoice();

        $this->actingAs($this->admin())
            ->post("/admin/billing/invoices/{$invoice->id}/resend")
            ->assertSessionHas('success');

        Notification::assertSentTo($invoice->order->customer, InvoiceCreatedNotification::class);
    }

    // ======================= Pengembalian dana =======================

    public function test_refund_to_credit_balance_adds_credit_after_approval(): void
    {
        $invoice = $this->paidInvoice(100000);
        $customer = $invoice->order->customer;

        $this->actingAs($this->admin())->post('/admin/billing/refunds', [
            'invoice_id' => $invoice->id,
            'amount' => 40000,
            'method' => 'credit_balance',
            'reason' => 'Layanan terganggu 3 hari.',
        ])->assertSessionHas('success');

        $refund = Refund::first();
        $this->assertSame('pending', $refund->status);
        // Belum disetujui: saldo belum bertambah.
        $this->assertEquals(0, CreditTransaction::balanceFor($customer->id));

        $this->actingAs($this->admin())->post("/admin/billing/refunds/{$refund->id}/approve")->assertSessionHas('success');

        $this->assertSame('processed', $refund->fresh()->status);
        $this->assertEquals(40000, CreditTransaction::balanceFor($customer->id));
    }

    public function test_refund_cannot_exceed_remaining_invoice_amount(): void
    {
        $invoice = $this->paidInvoice(100000);

        $this->actingAs($this->admin())->post('/admin/billing/refunds', [
            'invoice_id' => $invoice->id, 'amount' => 70000, 'method' => 'manual_transfer', 'reason' => 'Pertama',
        ]);

        $this->actingAs($this->admin())->post('/admin/billing/refunds', [
            'invoice_id' => $invoice->id, 'amount' => 40000, 'method' => 'manual_transfer', 'reason' => 'Kedua',
        ])->assertSessionHas('error');

        $this->assertSame(1, Refund::count());
    }

    public function test_refund_approval_is_not_repeatable(): void
    {
        $invoice = $this->paidInvoice(100000);
        $customer = $invoice->order->customer;

        $this->actingAs($this->admin())->post('/admin/billing/refunds', [
            'invoice_id' => $invoice->id, 'amount' => 25000, 'method' => 'credit_balance', 'reason' => 'Uji',
        ]);
        $refund = Refund::first();

        $this->actingAs($this->admin())->post("/admin/billing/refunds/{$refund->id}/approve");
        $this->actingAs($this->admin())->post("/admin/billing/refunds/{$refund->id}/approve")->assertSessionHas('error');

        $this->assertEquals(25000, CreditTransaction::balanceFor($customer->id));
    }

    public function test_cancelled_refund_frees_the_amount(): void
    {
        $invoice = $this->paidInvoice(100000);

        $this->actingAs($this->admin())->post('/admin/billing/refunds', [
            'invoice_id' => $invoice->id, 'amount' => 100000, 'method' => 'manual_transfer', 'reason' => 'Salah input',
        ]);
        $this->actingAs($this->admin())->post('/admin/billing/refunds/' . Refund::first()->id . '/cancel');

        $this->actingAs($this->admin())->post('/admin/billing/refunds', [
            'invoice_id' => $invoice->id, 'amount' => 50000, 'method' => 'manual_transfer', 'reason' => 'Benar',
        ])->assertSessionHas('success');
    }

    public function test_refund_only_for_paid_invoice(): void
    {
        $invoice = $this->paidInvoice(100000);
        $invoice->update(['status' => 'sent']);

        $this->actingAs($this->admin())->post('/admin/billing/refunds', [
            'invoice_id' => $invoice->id, 'amount' => 1000, 'method' => 'manual_transfer', 'reason' => 'Uji',
        ])->assertSessionHas('error');
    }

    // ======================= Saldo =======================

    public function test_admin_can_add_and_subtract_credit(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())->post('/admin/billing/credits/adjust', [
            'customer_id' => $customer->id, 'direction' => 'add', 'amount' => 50000, 'reason' => 'Kompensasi',
        ])->assertSessionHas('success');

        $this->actingAs($this->admin())->post('/admin/billing/credits/adjust', [
            'customer_id' => $customer->id, 'direction' => 'subtract', 'amount' => 20000, 'reason' => 'Koreksi',
        ])->assertSessionHas('success');

        $this->assertEquals(30000, CreditTransaction::balanceFor($customer->id));
        $this->assertSame('adjustment', CreditTransaction::latest('id')->value('type'));
    }

    public function test_credit_cannot_go_negative(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())->post('/admin/billing/credits/adjust', [
            'customer_id' => $customer->id, 'direction' => 'subtract', 'amount' => 1000, 'reason' => 'Uji',
        ])->assertSessionHas('error');

        $this->assertSame(0, CreditTransaction::where('customer_id', $customer->id)->count());
    }

    // ======================= Kupon & pajak =======================

    public function test_coupon_code_is_uppercased_and_unique_case_insensitively(): void
    {
        $payload = ['name' => 'Promo', 'type' => 'percent', 'value' => 10, 'max_per_customer' => 1, 'is_active' => 1];

        $this->actingAs($this->admin())->post('/admin/billing/coupons', $payload + ['code' => 'hemat10'])->assertSessionHas('success');
        $this->assertDatabaseHas('coupons', ['code' => 'HEMAT10']);

        $this->actingAs($this->admin())->post('/admin/billing/coupons', $payload + ['code' => 'HEMAT10'])->assertSessionHasErrors('code');
        $this->assertSame(1, Coupon::count());
    }

    public function test_percent_coupon_cannot_exceed_100(): void
    {
        $this->actingAs($this->admin())->post('/admin/billing/coupons', [
            'code' => 'GRATIS', 'name' => 'Salah', 'type' => 'percent', 'value' => 150, 'max_per_customer' => 1,
        ])->assertSessionHasErrors('value');
    }

    public function test_coupon_can_be_deactivated(): void
    {
        $coupon = Coupon::create(['code' => 'AKTIF', 'name' => 'Aktif', 'type' => 'fixed', 'value' => 5000, 'max_per_customer' => 1, 'is_active' => true]);

        $this->actingAs($this->admin())->put("/admin/billing/coupons/{$coupon->id}", [
            'name' => 'Aktif', 'type' => 'fixed', 'value' => 5000, 'max_per_customer' => 1,
        ])->assertSessionHas('success');

        $this->assertFalse($coupon->fresh()->is_active);
        $this->assertSame('AKTIF', $coupon->fresh()->code);
    }

    public function test_tax_rate_is_stored_as_fraction(): void
    {
        $this->actingAs($this->admin())->post('/admin/billing/taxes', [
            'code' => 'PPN_12', 'name' => 'PPN 12%', 'rate_percent' => 12, 'country' => 'ID', 'is_active' => 1,
        ])->assertSessionHas('success');

        $tax = TaxRate::where('code', 'PPN_12')->first();
        $this->assertEquals(0.12, (float) $tax->rate);
        $this->assertSame($tax->id, TaxRate::defaultForCountry('ID')?->id);
    }

    // ======================= Margin =======================

    public function test_margin_uses_actual_supplier_cost(): void
    {
        $invoice = $this->paidInvoice(100000);
        SupplierPurchase::create([
            'order_id' => $invoice->order_id,
            'supplier_order_no' => 'SMP-MARGIN',
            'actual_cost' => 65000,
            'purchased_at' => now(),
        ]);

        $row = app(MarginService::class)->perOrder()->firstWhere('order.id', $invoice->order_id);
        $this->assertEquals(35000, $row['margin']);
        $this->assertEquals(35.0, $row['margin_percent']);

        $month = app(MarginService::class)->monthly(1)->first();
        $this->assertEquals(100000, $month['revenue']);
        $this->assertEquals(65000, $month['cost']);
    }

    public function test_orders_without_cost_are_flagged_not_counted_as_profit(): void
    {
        $invoice = $this->paidInvoice(100000);

        $row = app(MarginService::class)->perOrder()->firstWhere('order.id', $invoice->order_id);
        $this->assertNull($row['cost']);
        $this->assertNull($row['margin']);
        $this->assertSame(1, app(MarginService::class)->missingCostCount());

        $this->actingAs($this->admin())->get('/admin/billing?tab=margin')->assertSee('Data Belum Lengkap');
    }
}
