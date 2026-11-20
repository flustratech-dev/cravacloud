<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tencent integration Phase 2 — map the 8 VPS packages to verified Lighthouse
 * bundles (prices confirmed live via tencent:probe, 2026-11-19).
 *
 * D3 FINAL: ai_combo (4) and managed_db (3) are intentionally NOT mapped —
 * their current sell prices lose money vs Tencent HPP. They stay manual
 * until Ryan reprices them.
 *
 * Idempotent: updates by name+category; safe to re-run. Skips empty catalogs
 * (fresh test DBs are filled by VpsSpecSeeder instead).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('vps_specs')->count() === 0) {
            return;
        }

        $map = [
            'Starter XS' => ['bundle_starter_nmc_lin_med2_01', 'STARTER_BUNDLE'],
            'Starter S' => ['bundle_starter_nmc_lin_med4_01', 'STARTER_BUNDLE'],
            'Starter M' => ['bundle_starter_nmc_lin_med8_01', 'STARTER_BUNDLE'],
            'Starter L' => ['bundle_starter_nmc_lin_med8_02', 'STARTER_BUNDLE'],
            'Enterprise Pro' => ['bundle_gen_nmc_lin_lg8_01', 'GENERAL_BUNDLE'],
            'Enterprise Max' => ['bundle_gen_nmc_lin_lg16_01', 'GENERAL_BUNDLE'],
            'Enterprise Ultra' => ['bundle_gen_nmc_lin_2xlg16_01', 'GENERAL_BUNDLE'],
            'Enterprise Extreme' => ['bundle_gen_nmc_lin_2xlg32_01', 'GENERAL_BUNDLE'],
        ];

        foreach ($map as $name => [$bundleId, $bundleType]) {
            DB::table('vps_specs')
                ->where('category', 'vps')
                ->where('name', $name)
                ->update([
                    'upstream_bundle_id' => $bundleId,
                    'upstream_bundle_type' => $bundleType,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('vps_specs')
            ->whereNotNull('upstream_bundle_id')
            ->update([
                'upstream_bundle_id' => null,
                'upstream_bundle_type' => null,
                'updated_at' => now(),
            ]);
    }
};