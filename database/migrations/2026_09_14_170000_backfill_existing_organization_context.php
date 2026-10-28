<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $ownerRoleId = DB::table('roles')->where('slug', 'owner')->value('id');

        DB::table('users')->orderBy('id')->chunkById(250, function ($users) use ($ownerRoleId) {
            foreach ($users as $user) {
                $organizationId = $user->current_organization_id
                    ?: DB::table('organization_members')->where('user_id', $user->id)->value('organization_id');

                if (!$organizationId) {
                    $baseSlug = Str::slug($user->username ?: 'user-' . $user->id);
                    $slug = $baseSlug;
                    $suffix = 1;
                    while (DB::table('organizations')->where('slug', $slug)->exists()) {
                        $slug = $baseSlug . '-' . $suffix++;
                    }
                    $organizationId = DB::table('organizations')->insertGetId([
                        'name' => ($user->full_name ?: $user->username) . ' (Personal)',
                        'slug' => $slug,
                        'type' => 'personal',
                        'owner_id' => $user->id,
                        'billing_email' => $user->email,
                        'billing_name' => $user->full_name ?: $user->username,
                        'country' => $user->country ?: 'ID',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('organization_members')->insert([
                        'organization_id' => $organizationId,
                        'user_id' => $user->id,
                        'role_id' => $ownerRoleId,
                        'joined_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('organization_quotas')->insert([
                        'organization_id' => $organizationId,
                        'max_vps' => null,
                        'max_members' => $user->is_admin ? null : 5,
                        'max_api_keys' => 10,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('users')->where('id', $user->id)->update(['current_organization_id' => $organizationId]);
                foreach (['orders', 'vps_instances', 'subscriptions', 'support_tickets'] as $table) {
                    DB::table($table)->where('customer_id', $user->id)->whereNull('organization_id')->update(['organization_id' => $organizationId]);
                }
                DB::table('invoices')->whereNull('organization_id')->whereIn('order_id', function ($query) use ($user) {
                    $query->select('id')->from('orders')->where('customer_id', $user->id);
                })->update(['organization_id' => $organizationId]);
            }
        });
    }

    public function down(): void
    {
        // Data backfill is intentionally irreversible; deleting organizations would risk customer data.
    }
};
