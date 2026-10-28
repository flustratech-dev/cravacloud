<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk Poin 5 (Security) & Poin 6 (Multi-tenant).
 *
 * POIN 5 — Security:
 *   - users.two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at
 *   - users.password_changed_at, users.mfa_required
 *   - login_activities   : catatan login sukses/gagal (IP, UA, device, geo)
 *   - admin_audit_logs   : semua aksi admin yang mengubah state
 *   - ssh_keys           : SSH public keys pelanggan
 *   - api_keys           : API key dengan prefix + hashed secret + expiry
 *   - roles              : owner, admin, billing, operator, viewer
 *   - permissions        : granular (vps.read, vps.manage, billing.manage, dst.)
 *   - permission_role    : pivot
 *
 * POIN 6 — Multi-tenant:
 *   - organizations         : personal|company account
 *   - organization_members  : pivot user↔org dengan role
 *   - organization_invitations : email invite dengan token expiry
 *   - organization_quotas   : quota per organization (max VPS, max members, dst.)
 *   - users.current_organization_id
 *   - orders/vps_instances/subscriptions/invoices : + organization_id nullable
 *   - Data migration: bikin Personal Org untuk semua user existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // POIN 5 — Security columns on users
        // ============================================================
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->boolean('mfa_required')->default(false)->after('two_factor_confirmed_at');
            $table->timestamp('password_changed_at')->nullable()->after('mfa_required');
            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });

        // ============================================================
        // POIN 5 — Login activity log
        // ============================================================
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email')->nullable();       // untuk failed attempts (user tidak match)
            $table->enum('outcome', ['success', 'failed', 'blocked', 'two_factor_required', 'two_factor_failed']);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_label')->nullable(); // "Chrome on Windows", parsed
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('reason')->nullable();       // untuk failed
            $table->string('session_id')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['email', 'outcome']);
        });

        // ============================================================
        // POIN 5 — Admin audit log
        // ============================================================
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);              // order.mark_paid, vps.terminate, user.impersonate
            $table->string('subject_type', 100)->nullable(); // App\Models\Order
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('changes')->nullable();       // diff: {before,after}
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['admin_user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });

        // ============================================================
        // POIN 5 — SSH keys (pengganti / pelengkap root password)
        // ============================================================
        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 40);                // ssh-rsa, ssh-ed25519
            $table->text('public_key');
            $table->string('fingerprint', 128)->unique(); // SHA256 fingerprint
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // ============================================================
        // POIN 5 — API keys (untuk API programmatic access)
        // ============================================================
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable();  // FK ditambah setelah organizations dibuat
            $table->string('name', 100);
            // Format: cv_live_<prefix>.<secret>. Yang disimpan: prefix (visible) + hash(secret).
            $table->string('prefix', 20)->unique();
            $table->string('secret_hash', 191);
            $table->json('scopes')->nullable();        // ['vps.read', 'billing.read']
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });

        // ============================================================
        // POIN 5 — RBAC (roles + permissions)
        // ============================================================
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();       // owner, admin, billing, operator, viewer
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false); // seeded role, tidak bisa dihapus
            $table->unsignedTinyInteger('priority')->default(0); // urutan hierarki
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();       // vps.read, vps.manage, billing.manage
            $table->string('name', 120);
            $table->string('category', 40)->default('general'); // vps, billing, org, security
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // ============================================================
        // POIN 6 — Organizations
        // ============================================================
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name');
            $table->enum('type', ['personal', 'company'])->default('personal');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('billing_email')->nullable();
            $table->string('billing_name')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('tax_id', 40)->nullable();   // NPWP
            $table->string('country', 2)->default('ID');
            $table->string('currency', 8)->default('IDR');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index('owner_id');
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles');
            $table->timestamp('joined_at')->useCurrent();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'role_id']);
        });

        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('role_id')->constrained('roles');
            $table->string('token', 80)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'email']);
        });

        Schema::create('organization_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->unique();
            $table->unsignedInteger('max_vps')->nullable();
            $table->unsignedInteger('max_members')->default(5);
            $table->unsignedInteger('max_api_keys')->default(10);
            $table->json('per_datacenter')->nullable();  // {"ID-CGK01": 10, "SG-SIN01": 5}
            $table->timestamps();
        });

        // ============================================================
        // POIN 6 — Users: current_organization_id
        // ============================================================
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')->nullable()->after('last_login_ip')
                ->constrained('organizations')->nullOnDelete();
            $table->string('country', 2)->default('ID')->after('address');
        });

        // FK yang ditunda: api_keys.organization_id → organizations.id
        Schema::table('api_keys', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        // ============================================================
        // POIN 6 — organization_id di resource tables
        // ============================================================
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('customer_id')
                ->constrained('organizations')->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('customer_id')
                ->constrained('organizations')->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('customer_id')
                ->constrained('organizations')->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('subscription_id')
                ->constrained('organizations')->nullOnDelete();
            $table->index('organization_id');
        });

        // ============================================================
        // Data migration: bikin Personal Org untuk existing users +
        // backfill organization_id ke orders/vps/subs/invoices.
        // Owner role belum ada (seeder jalan setelah migration), jadi role_id
        // sementara dikosongkan. Seeder default role akan mengisi kembali.
        // ============================================================
        // (Personal org creation dijalankan di seeder terpisah supaya
        // role sudah terseed — lebih aman.)
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
        Schema::table('vps_instances', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_organization_id']);
            $table->dropColumn(['current_organization_id', 'country']);
        });

        Schema::dropIfExists('organization_quotas');
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('ssh_keys');
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('login_activities');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'mfa_required', 'password_changed_at', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
