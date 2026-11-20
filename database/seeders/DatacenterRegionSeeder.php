<?php

namespace Database\Seeders;

use App\Models\DatacenterRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tencent integration Phase 2 — datacenter regions for the upstream provider.
 *
 * ap-jakarta  : primary, active.
 * ap-singapore: fallback choice offered at checkout when the primary pool is
 *               full (D4: hard-stop + explicit customer choice, never a
 *               silent auto-fallback). Inactive by default.
 *
 * White-label: `provider` here is an INTERNAL column; customer-facing output
 * always uses Order::customerProviderLabel() ('cravacloud').
 *
 * Idempotent: firstOrCreate on unique code; existing admin edits survive.
 */
class DatacenterRegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            [
                'code' => 'ap-jakarta',
                'name' => 'Jakarta, Indonesia',
                'country' => 'ID',
                'city' => 'Jakarta',
                'provider' => 'tencent',
                'provider_region_id' => 'ap-jakarta',
                'capacity' => null, // capacity governed by upstream_quota_pools
                'is_active' => true,
                'metadata' => [
                    'location' => 'indonesia', // maps orders.datacenter_location
                    'pools' => ['STARTER_BUNDLE' => 20, 'GENERAL_BUNDLE' => 20],
                ],
            ],
            [
                'code' => 'ap-singapore',
                'name' => 'Singapore',
                'country' => 'SG',
                'city' => 'Singapore',
                'provider' => 'tencent',
                'provider_region_id' => 'ap-singapore',
                'capacity' => null,
                'is_active' => false, // offered as explicit alternative only (D4)
                'metadata' => [
                    'location' => 'singapore',
                    'pools' => ['STARTER_BUNDLE' => 20, 'GENERAL_BUNDLE' => 20],
                ],
            ],
        ];

        foreach ($regions as $region) {
            DatacenterRegion::firstOrCreate(
                ['code' => $region['code']],
                $region,
            );

            // Ensure a quota pool row exists for every bundle family.
            foreach (array_keys($region['metadata']['pools']) as $bundleType) {
                DB::table('upstream_quota_pools')->insertOrIgnore([
                    'region_code' => $region['code'],
                    'bundle_type' => $bundleType,
                    'capacity' => $region['metadata']['pools'][$bundleType],
                    'reserved' => 0,
                    'used' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Fresh DBs run migrations before seeding, so the guard-settings
        // migration could not resolve the Starter XS id yet. Backfill the
        // canary whitelist (D13) only while it is still empty — admin edits
        // are never overwritten.
        $whitelist = DB::table('settings')->where('key', 'provisioning_auto_spec_whitelist')->first();
        if ($whitelist && in_array(json_decode((string) $whitelist->value, true) ?: [], [[], null], true)) {
            $starterXsId = DB::table('vps_specs')
                ->where('category', 'vps')
                ->where('name', 'Starter XS')
                ->value('id');
            if ($starterXsId) {
                DB::table('settings')
                    ->where('key', 'provisioning_auto_spec_whitelist')
                    ->update(['value' => json_encode([(int) $starterXsId]), 'updated_at' => now()]);
            }
        }
    }
}