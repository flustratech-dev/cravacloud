<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk Poin 1 & 2 dari roadmap SaaS production-grade:
 *
 * POIN 1 - State Machine Order/VPS:
 *   - order_status_history: audit trail semua transisi status order
 *   - vps_status_history:   audit trail semua transisi status VPS
 *   - Kolom order: failure_reason, provisioning_attempts, grace_period_ends_at
 *   - Perluas enum orders.status: + paid, + grace_period, + suspended, + failed
 *
 * POIN 2 - Celah Pembayaran:
 *   - payment_transactions: catatan setiap transaksi pembayaran (raw gateway payload,
 *     signature, status, amount) - immutable-friendly & auditable.
 *   - webhook_events: idempotency store untuk webhook (unique per provider+event_id)
 *   - Kolom invoice: paid_via_transaction_id (link ke payment_transactions)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // POIN 1: State Machine - History Tables
        // ============================================================
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();  // null saat insert awal
            $table->string('to_status', 40);
            $table->string('reason')->nullable();            // e.g. "payment settlement", "admin manual"
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 20)->default('system'); // system|admin|customer|webhook
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();            // arbitrary context (tx id, error, etc)
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('vps_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vps_instance_id')->constrained('vps_instances')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 20)->default('system');
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['vps_instance_id', 'created_at']);
        });

        // ============================================================
        // POIN 1: Kolom tambahan di orders untuk state machine
        // ============================================================
        Schema::table('orders', function (Blueprint $table) {
            $table->text('failure_reason')->nullable()->after('expires_at');
            $table->unsignedTinyInteger('provisioning_attempts')->default(0)->after('failure_reason');
            $table->timestamp('grace_period_ends_at')->nullable()->after('provisioning_attempts');
            $table->timestamp('last_status_change_at')->nullable()->after('grace_period_ends_at');
        });

        // Perluas enum orders.status: tambah paid, grace_period, suspended, failed
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM(
                'pending','paid','provisioning','active','grace_period','suspended',
                'cancelled','expired','terminated','failed'
            ) NOT NULL DEFAULT 'pending'");
        }

        // ============================================================
        // POIN 2: Payment Transactions (immutable-friendly audit log)
        // ============================================================
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->string('provider', 40);              // midtrans, xendit, manual, shopee
            $table->string('provider_transaction_id')->nullable(); // gateway tx id
            $table->string('provider_order_ref')->nullable();      // gateway order_id
            $table->string('payment_method', 60)->nullable();      // qris, bca_va, gopay, ...

            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('IDR');

            // Status: pending, authorized, settled, failed, expired, refunded, disputed
            $table->string('status', 30)->default('pending');
            $table->string('fraud_status', 30)->nullable();  // accept | challenge | deny

            $table->string('signature_key', 255)->nullable();
            $table->json('raw_payload')->nullable();         // full webhook body
            $table->text('failure_reason')->nullable();

            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // Idempotency: gateway transaction id unik per provider
            $table->unique(['provider', 'provider_transaction_id'], 'ux_payment_provider_tx');
            $table->index(['order_id', 'status']);
            $table->index('provider_order_ref');
        });

        // ============================================================
        // POIN 2: Webhook Events - idempotency store
        // ============================================================
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('event_id', 191);       // provider transaction_id | dedupe key
            $table->string('event_type', 60)->nullable();
            $table->string('signature', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('payload')->nullable();

            $table->string('processing_status', 20)->default('received'); // received|processed|failed|ignored
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();

            $table->timestamps();

            // Idempotency constraint: satu provider + event_id tidak bisa diproses 2x
            $table->unique(['provider', 'event_id'], 'ux_webhook_provider_event');
            $table->index(['provider', 'processing_status']);
        });

        // ============================================================
        // POIN 2: Link invoice -> transaction yang membayarnya
        // ============================================================
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('paid_via_transaction_id')->nullable()->after('paid_at')
                ->constrained('payment_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['paid_via_transaction_id']);
            $table->dropColumn('paid_via_transaction_id');
        });

        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payment_transactions');

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM(
                'pending','provisioning','active','cancelled','expired','terminated'
            ) NOT NULL DEFAULT 'pending'");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'failure_reason',
                'provisioning_attempts',
                'grace_period_ends_at',
                'last_status_change_at',
            ]);
        });

        Schema::dropIfExists('vps_status_history');
        Schema::dropIfExists('order_status_history');
    }
};
