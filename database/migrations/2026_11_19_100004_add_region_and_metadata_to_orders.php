<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tencent integration Phase 2 — link orders to a datacenter region row and
 * give them a metadata JSON bag (region choice, quota decisions, upstream
 * references). vps_instances already has datacenter_region_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('datacenter_region_id')->nullable()->after('datacenter_location')
                ->constrained('datacenter_regions')->nullOnDelete();
            $table->json('metadata')->nullable()->after('admin_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('datacenter_region_id');
            $table->dropColumn('metadata');
        });
    }
};