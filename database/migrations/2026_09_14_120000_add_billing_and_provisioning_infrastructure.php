<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk Poin 3 (Billing SaaS berulang) & Poin 4 (Provisioning engine).
 *
 * POIN 3 — Billing:
 *   - invoice_items      : line items immutable (snapshot harga & pajak per item)
 *   - subscriptions      : entitas langganan yang di-renew otomatis
 *   - coupons            : master kupon (percent/fixed, redemption limit, expiry)
 *   - coupon_redemptions : catatan setiap pemakaian kupon
 *   - credit_transactions: ledger saldo credit pelanggan (topup/apply/refund)
 *   - tax_rates          : master pajak (contoh PPN 11%)
 *   - refunds            : catatan refund per transaksi
 *   - invoices           : + subtotal, tax_total, discount_total, currency, notes,
 *                          subscription_id, period_start, period_end, tax_rate_id
 *   - orders             : + subscription_id, next_billing_at, currency
 *
 * POIN 4 — Provisioning:
 *   - datacenter_regions : master region/DC dengan kapasitas & provider mapping
 *   - provisioning_tasks : async job tracking (progress, error, attempts)
 *   - vps_instances      : + provider, provider_resource_id, provider_meta,
 *                          last_reconciled_at, datacenter_region_id
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // POIN 3 - Master data & ledger tables
        // ============================================================
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();     // PPN_11, PPN_12
            $table->string('name', 100);              // "PPN 11%"
            $table->decimal('rate', 6, 4);            // 0.1100 (11%)
            $table->string('country', 2)->default('ID');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->enum('type', ['percent', 'fixed']);
            $table->decimal('value', 12, 2);          // 15 (percent) atau 50000 (fixed IDR)
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_per_customer')->default(1);
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('applies_to')->nullable();   // ['spec_ids'=>[], 'cycles'=>[]]
            $table->timestamps();
            $table->index(['is_active', 'ends_at']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamps();
            $table->index(['coupon_id', 'customer_id']);
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['topup', 'apply', 'refund', 'adjustment']);
            // Positive = credit masuk, negative = credit keluar.
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 14, 2); // running balance untuk audit
            $table->string('reason');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('refund_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        // ============================================================
        // POIN 3 - Subscriptions (entitas renewable)
        // ============================================================
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vps_spec_id')->constrained('vps_specs');
            $table->foreignId('vps_instance_id')->nullable()->constrained('vps_instances')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            // Status: active | past_due | grace_period | suspended | cancelled | terminated
            $table->string('status', 30)->default('active');
            $table->string('billing_cycle', 30)->default('monthly');

            $table->decimal('unit_amount', 12, 2);   // harga per periode (setelah diskon cycle)
            $table->string('currency', 8)->default('IDR');
            $table->boolean('auto_renew')->default(true);

            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->unsignedTinyInteger('renewal_failures')->default(0);
            $table->timestamp('last_renewal_attempt_at')->nullable();
            $table->text('last_renewal_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_billing_at']);
            $table->index(['customer_id', 'status']);
        });

        // ============================================================
        // POIN 3 - Invoice line items (IMMUTABLE — jangan pernah UPDATE nilainya)
        // ============================================================
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('type', 30);              // service, setup_fee, discount, tax, credit_apply, refund_adjustment, proration
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);    // untuk diskon nilainya negative
            $table->decimal('subtotal', 14, 2);      // quantity * unit_price
            $table->decimal('tax_rate', 6, 4)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 14, 2);
            // Snapshot: kalau paket dihapus/diubah, data historis tetap ada.
            $table->foreignId('vps_spec_id')->nullable()->constrained('vps_specs')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at: immutable.

            $table->index(['invoice_id', 'type']);
        });

        // ============================================================
        // POIN 3 - Refunds
        // ============================================================
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->enum('method', ['credit_balance', 'gateway_refund', 'manual_transfer']);
            $table->enum('status', ['pending', 'processed', 'failed', 'cancelled'])->default('pending');
            $table->string('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // FK circular: credit_transactions.refund_id → refunds.id
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->foreign('refund_id')->references('id')->on('refunds')->nullOnDelete();
        });

        // ============================================================
        // POIN 3 - Invoices: extend dengan breakdown
        // ============================================================
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('order_id')->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->after('paid_via_transaction_id')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('subtotal', 14, 2)->nullable()->after('amount');       // total items sebelum diskon & pajak
            $table->decimal('discount_total', 12, 2)->default(0)->after('subtotal');
            $table->decimal('tax_total', 12, 2)->default(0)->after('discount_total');
            $table->decimal('credit_applied', 12, 2)->default(0)->after('tax_total');
            $table->string('currency', 8)->default('IDR')->after('credit_applied');
            $table->timestamp('period_start')->nullable()->after('due_at');
            $table->timestamp('period_end')->nullable()->after('period_start');
            $table->boolean('is_renewal')->default(false)->after('period_end');
            $table->text('notes')->nullable();
        });

        // ============================================================
        // POIN 3 - Orders: link subscription & currency
        // ============================================================
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('vps_spec_id')->constrained('subscriptions')->nullOnDelete();
            $table->string('currency', 8)->default('IDR')->after('amount');
        });

        // ============================================================
        // POIN 4 - Datacenter regions (inventory & capacity)
        // ============================================================
        Schema::create('datacenter_regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();       // ID-CGK01, SG-SIN01
            $table->string('name');                     // Jakarta, Indonesia
            $table->string('country', 2)->default('ID');
            $table->string('city')->nullable();
            $table->string('provider', 40)->nullable(); // manual, supplier, biznetgio, dst.
            $table->string('provider_region_id')->nullable();
            $table->unsignedInteger('capacity')->nullable();       // total slot
            $table->unsignedInteger('capacity_used')->default(0);  // slot terpakai
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // ============================================================
        // POIN 4 - VPS Instance: provider fields
        // ============================================================
        Schema::table('vps_instances', function (Blueprint $table) {
            $table->string('provider', 40)->default('manual')->after('control_panel');
            $table->string('provider_resource_id')->nullable()->after('provider');
            $table->json('provider_meta')->nullable()->after('provider_resource_id');
            $table->timestamp('last_reconciled_at')->nullable()->after('provider_meta');
            $table->foreignId('datacenter_region_id')->nullable()->after('datacenter_location')
                ->constrained('datacenter_regions')->nullOnDelete();
            $table->index(['provider', 'provider_resource_id']);
        });

        // ============================================================
        // POIN 4 - Provisioning tasks (async job tracking)
        // ============================================================
        Schema::create('provisioning_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 40)->unique();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('vps_instance_id')->nullable()->constrained('vps_instances')->nullOnDelete();

            // Kind: provision, start, stop, reboot, force_reboot, reinstall, destroy, reconcile
            $table->string('kind', 30);
            // Status: pending, running, succeeded, failed, cancelled
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);       // 0-100
            $table->string('current_step')->nullable();                // "Booting", "Installing OS", ...

            $table->string('provider', 40)->default('manual');
            $table->json('input')->nullable();                         // parameter yang dipakai
            $table->json('output')->nullable();                        // hasil dari provider
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['vps_instance_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_tasks');

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->dropForeign(['datacenter_region_id']);
            $table->dropIndex(['provider', 'provider_resource_id']);
            $table->dropColumn(['provider', 'provider_resource_id', 'provider_meta', 'last_reconciled_at', 'datacenter_region_id']);
        });

        Schema::dropIfExists('datacenter_regions');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropColumn(['subscription_id', 'currency']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn([
                'subscription_id', 'tax_rate_id',
                'subtotal', 'discount_total', 'tax_total', 'credit_applied', 'currency',
                'period_start', 'period_end', 'is_renewal', 'notes',
            ]);
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropForeign(['refund_id']);
        });

        Schema::dropIfExists('refunds');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('tax_rates');
    }
};
