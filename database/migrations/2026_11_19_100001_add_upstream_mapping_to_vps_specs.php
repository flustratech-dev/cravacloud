<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tencent integration Phase 2 — upstream bundle mapping on the catalog.
 * Nullable: unmapped specs (ai_combo, managed_db — HOLD per D3) stay manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vps_specs', function (Blueprint $table) {
            $table->string('upstream_bundle_id', 60)->nullable()->after('default_provider');
            $table->string('upstream_bundle_type', 30)->nullable()->after('upstream_bundle_id');
        });
    }

    public function down(): void
    {
        Schema::table('vps_specs', function (Blueprint $table) {
            $table->dropColumn(['upstream_bundle_id', 'upstream_bundle_type']);
        });
    }
};