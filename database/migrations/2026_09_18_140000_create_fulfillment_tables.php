<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 5 - Fulfillment Workboard.
 *
 * Kolom papan kerja ditentukan oleh orders.status (tidak diubah) ditambah
 * orders.fulfillment_stage. Dengan begitu state machine order tetap utuh:
 * papan kerja hanya menandai kemajuan kerja manual di antara 'paid' dan 'active'.
 *
 * Tabel yang dibuat:
 *   - fulfillment_checklists : butir daftar periksa setup per order
 *   - supplier_purchases     : catatan pembelian di Supplier (dipakai ulang Phase 6 dan 8)
 *
 * Kolom tambahan pada orders:
 *   - fulfillment_stage : null (siap diproses) | purchased | setup | delivered
 *   - delivered_at      : kapan kredensial diserahkan ke pelanggan
 *   - sla_alerted_at    : kapan admin diperingatkan karena melewati ambang waktu tanggap
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_stage', 20)->nullable()->after('status');
            $table->dateTime('delivered_at')->nullable()->after('paid_at');
            $table->dateTime('sla_alerted_at')->nullable()->after('delivered_at');

            $table->index(['status', 'fulfillment_stage']);
        });

        Schema::create('fulfillment_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('step_key', 40);
            $table->boolean('is_done')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'step_key']);
        });

        Schema::create('supplier_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('vps_instance_id')->nullable()->constrained('vps_instances')->nullOnDelete();
            $table->string('supplier', 40)->default('supplier');
            $table->string('supplier_order_no', 120);
            $table->decimal('actual_cost', 12, 2);
            $table->dateTime('purchased_at');
            // Masa aktif di sisi supplier. Pembelian prepaid satu bulan.
            $table->dateTime('supplier_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('purchased_at');
            $table->index('supplier_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchases');
        Schema::dropIfExists('fulfillment_checklists');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'fulfillment_stage']);
            $table->dropColumn(['fulfillment_stage', 'delivered_at', 'sla_alerted_at']);
        });
    }
};
