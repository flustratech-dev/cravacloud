<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder idempotent untuk:
 * 1. Default roles (owner, admin, billing, operator, viewer)
 * 2. Default permissions matrix
 * 3. Attach permissions per role
 * 4. Bikin Personal Org untuk existing users yang belum punya org
 * 5. Backfill organization_id di orders/vps_instances/subscriptions/invoices
 *
 * Aman dijalankan berkali-kali — semua updateOrCreate / firstOrCreate.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->assignPermissionsToRoles();
        $this->createPersonalOrgsForExistingUsers();
        $this->backfillOrganizationIds();
    }

    protected function seedPermissions(): void
    {
        $perms = [
            // VPS
            ['slug' => 'vps.read',        'name' => 'Lihat VPS',                    'category' => 'vps'],
            ['slug' => 'vps.manage',      'name' => 'Kelola VPS (start/stop/reboot)', 'category' => 'vps'],
            ['slug' => 'vps.reinstall',   'name' => 'Reinstall OS VPS',             'category' => 'vps'],
            ['slug' => 'vps.terminate',   'name' => 'Terminate VPS',                'category' => 'vps'],
            ['slug' => 'vps.credentials', 'name' => 'Lihat kredensial root/SSH',    'category' => 'vps'],

            // Billing
            ['slug' => 'billing.read',    'name' => 'Lihat invoice & billing',      'category' => 'billing'],
            ['slug' => 'billing.manage',  'name' => 'Bayar invoice & renewal',      'category' => 'billing'],
            ['slug' => 'billing.refund',  'name' => 'Request refund',               'category' => 'billing'],

            // Organization
            ['slug' => 'org.read',        'name' => 'Lihat organisasi',             'category' => 'org'],
            ['slug' => 'org.manage',      'name' => 'Edit setting organisasi',     'category' => 'org'],
            ['slug' => 'org.delete',      'name' => 'Hapus organisasi',             'category' => 'org'],

            // Members
            ['slug' => 'member.read',     'name' => 'Lihat anggota',                'category' => 'member'],
            ['slug' => 'member.invite',   'name' => 'Undang anggota baru',          'category' => 'member'],
            ['slug' => 'member.remove',   'name' => 'Hapus anggota',                'category' => 'member'],
            ['slug' => 'member.roles',    'name' => 'Ubah role anggota',            'category' => 'member'],

            // Security / API
            ['slug' => 'security.read',   'name' => 'Lihat login activity',         'category' => 'security'],
            ['slug' => 'apikey.manage',   'name' => 'Kelola API key',               'category' => 'security'],
            ['slug' => 'sshkey.manage',   'name' => 'Kelola SSH key',               'category' => 'security'],
        ];

        foreach ($perms as $p) {
            Permission::updateOrCreate(['slug' => $p['slug']], $p);
        }
    }

    protected function seedRoles(): void
    {
        $roles = [
            ['slug' => 'owner',    'name' => 'Owner',    'priority' => 100, 'is_system' => true, 'description' => 'Full control termasuk hapus organisasi.'],
            ['slug' => 'admin',    'name' => 'Admin',    'priority' => 80,  'is_system' => true, 'description' => 'Kelola VPS, billing, member — tidak bisa hapus org.'],
            ['slug' => 'billing',  'name' => 'Billing',  'priority' => 60,  'is_system' => true, 'description' => 'Fokus billing & invoice.'],
            ['slug' => 'operator', 'name' => 'Operator', 'priority' => 40,  'is_system' => true, 'description' => 'Kelola VPS teknis, tidak bisa lihat billing.'],
            ['slug' => 'viewer',   'name' => 'Viewer',   'priority' => 20,  'is_system' => true, 'description' => 'Read-only.'],
        ];

        foreach ($roles as $r) {
            Role::updateOrCreate(['slug' => $r['slug']], $r);
        }
    }

    protected function assignPermissionsToRoles(): void
    {
        $matrix = [
            'owner' => ['*'], // semua
            'admin' => [
                'vps.read', 'vps.manage', 'vps.reinstall', 'vps.credentials',
                'billing.read', 'billing.manage',
                'org.read', 'org.manage',
                'member.read', 'member.invite', 'member.remove', 'member.roles',
                'security.read', 'apikey.manage', 'sshkey.manage',
            ],
            'billing' => [
                'vps.read',
                'billing.read', 'billing.manage', 'billing.refund',
                'org.read',
                'member.read',
            ],
            'operator' => [
                'vps.read', 'vps.manage', 'vps.reinstall', 'vps.credentials',
                'org.read',
                'sshkey.manage',
            ],
            'viewer' => [
                'vps.read',
                'billing.read',
                'org.read',
                'member.read',
            ],
        ];

        foreach ($matrix as $roleSlug => $permSlugs) {
            $role = Role::bySlug($roleSlug);
            if (!$role) continue;

            if ($permSlugs === ['*']) {
                $ids = Permission::pluck('id')->all();
            } else {
                $ids = Permission::whereIn('slug', $permSlugs)->pluck('id')->all();
            }
            $role->permissions()->sync($ids);
        }
    }

    protected function createPersonalOrgsForExistingUsers(): void
    {
        $ownerRole = Role::bySlug('owner');
        if (!$ownerRole) return;

        User::whereDoesntHave('organizationMemberships')->chunkById(200, function ($users) use ($ownerRole) {
            foreach ($users as $user) {
                // firstOrCreate keeps the seeder safe after a partial run.
                $org = Organization::firstOrCreate(
                    ['owner_id' => $user->id, 'type' => 'personal'],
                    [
                        'name' => ($user->full_name ?? $user->email) . ' (Personal)',
                        'billing_email' => $user->email,
                        'billing_name' => $user->full_name,
                        'country' => $user->country ?? 'ID',
                    ]
                );
                OrganizationMember::firstOrCreate(
                    ['organization_id' => $org->id, 'user_id' => $user->id],
                    ['role_id' => $ownerRole->id, 'joined_at' => now()]
                );
                $org->quota()->firstOrCreate([], [
                    'max_vps' => null,
                    'max_members' => $user->is_admin ? null : 5,
                    'max_api_keys' => 10,
                ]);
                $user->forceFill(['current_organization_id' => $org->id])->save();
            }
        });
    }

    protected function backfillOrganizationIds(): void
    {
        // Orders, VPS, subscriptions, invoices → set organization_id dari personal
        // org milik customer_id kalau masih null.
        DB::table('orders')
            ->whereNull('organization_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $orgId = $this->findPersonalOrgId((int) $r->customer_id);
                    if ($orgId) {
                        DB::table('orders')->where('id', $r->id)->update(['organization_id' => $orgId]);
                    }
                }
            });

        DB::table('vps_instances')
            ->whereNull('organization_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $orgId = $this->findPersonalOrgId((int) $r->customer_id);
                    if ($orgId) {
                        DB::table('vps_instances')->where('id', $r->id)->update(['organization_id' => $orgId]);
                    }
                }
            });

        DB::table('subscriptions')
            ->whereNull('organization_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $orgId = $this->findPersonalOrgId((int) $r->customer_id);
                    if ($orgId) {
                        DB::table('subscriptions')->where('id', $r->id)->update(['organization_id' => $orgId]);
                    }
                }
            });

        // Invoice → follow order's organization_id.
        DB::table('invoices')
            ->whereNull('organization_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $orgId = DB::table('orders')->where('id', $r->order_id)->value('organization_id');
                    if ($orgId) {
                        DB::table('invoices')->where('id', $r->id)->update(['organization_id' => $orgId]);
                    }
                }
            });
    }

    protected array $userOrgCache = [];

    protected function findPersonalOrgId(int $userId): ?int
    {
        if (isset($this->userOrgCache[$userId])) return $this->userOrgCache[$userId];
        $orgId = DB::table('organizations')
            ->where('owner_id', $userId)
            ->where('type', 'personal')
            ->value('id');
        return $this->userOrgCache[$userId] = $orgId;
    }
}
