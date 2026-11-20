<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tencent integration Phase 2 — guard & rollout settings (D13).
 *
 * quota_guard_mode:
 *   observe (default, Phase 2) — checkout guards log/alert but never reject;
 *   enforce                  — revision 1 behavior: availability check +
 *                              max 2 pending orders per user at checkout.
 * provisioning_auto_enabled:
 *   false (default) — no auto-provisioning until Phase 3 gates pass;
 *   flip per-spec via whitelist, rollback without deploy.
 * provisioning_auto_spec_whitelist:
 *   spec IDs allowed to auto-provision when enabled (canary: Starter XS first).
 *
 * Idempotent: existing values are never overwritten (admin edits survive).
 */
return new class extends Migration
{
    public function up(): void
    {
        $starterXsId = DB::table('vps_specs')
            ->where('category', 'vps')
            ->where('name', 'Starter XS')
            ->value('id');

        $defaults = [
            [
                'key' => 'quota_guard_mode',
                'value' => 'observe',
                'type' => 'string',
                'group' => 'provisioning',
                'label' => 'Mode Guard Kuota Upstream',
                'description' => 'observe = catat tanpa menolak checkout; enforce = tolak checkout bila pool penuh / >2 order pending per user.',
            ],
            [
                'key' => 'provisioning_auto_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'provisioning',
                'label' => 'Auto-Provisioning Aktif',
                'description' => 'Master switch auto-provisioning upstream. OFF sampai gate Phase 3 lulus dan canary disetujui (D13).',
            ],
            [
                'key' => 'provisioning_auto_spec_whitelist',
                'value' => json_encode($starterXsId ? [(int) $starterXsId] : []),
                'type' => 'json',
                'group' => 'provisioning',
                'label' => 'Whitelist Spec Auto-Provisioning',
                'description' => 'ID paket yang boleh auto-provision saat master switch ON. Canary pertama: Starter XS (D13).',
            ],
        ];

        foreach ($defaults as $row) {
            $exists = DB::table('settings')->where('key', $row['key'])->exists();
            if (! $exists) {
                DB::table('settings')->insert($row + [
                    'is_public' => false,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'quota_guard_mode',
            'provisioning_auto_enabled',
            'provisioning_auto_spec_whitelist',
        ])->delete();
    }
};