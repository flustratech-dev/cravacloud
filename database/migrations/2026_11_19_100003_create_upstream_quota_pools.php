<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tencent integration Phase 2 — quota pools per region × bundle_type.
 * Tencent Lighthouse limits instances per bundle family per region
 * (20 STARTER_BUNDLE + 20 GENERAL_BUNDLE in ap-jakarta, verified live).
 *
 * reserved = hard reservations taken at `paid` (NOT at checkout — revision 1
 * anti-DoS: pending orders must not hold slots for their 24h TTL).
 * used     = live upstream instances (running/stopped/suspended/shutdown).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upstream_quota_pools', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 30);
            $table->string('bundle_type', 30);
            $table->unsignedInteger('capacity')->default(20);
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
            $table->unique(['region_code', 'bundle_type']);
        });

        // Seed pools for the primary region. DatacenterRegionSeeder also
        // ensures pools for every region it manages (idempotent firstOrCreate).
        foreach (['STARTER_BUNDLE', 'GENERAL_BUNDLE'] as $type) {
            DB::table('upstream_quota_pools')->insertOrIgnore([
                'region_code' => 'ap-jakarta',
                'bundle_type' => $type,
                'capacity' => 20,
                'reserved' => 0,
                'used' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upstream_quota_pools');
    }
};