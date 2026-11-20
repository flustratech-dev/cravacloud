<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tencent integration Phase 2 — supplier_purchases gains currency/exchange
 * rate/upstream reference/metadata so automated Tencent purchases (USD HPP
 * converted at the admin-managed rate, D9) feed MarginService unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchases', function (Blueprint $table) {
            $table->string('currency', 8)->default('IDR')->after('actual_cost');
            $table->decimal('exchange_rate', 12, 4)->nullable()->after('currency');
            $table->string('upstream_reference', 120)->nullable()->after('supplier_order_no');
            $table->json('metadata')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchases', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'upstream_reference', 'metadata']);
        });
    }
};