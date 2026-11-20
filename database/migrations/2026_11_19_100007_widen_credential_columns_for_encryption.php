<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D10 (Phase 2): prepare credential columns for encrypted-at-rest storage.
 *
 * Encrypted cast payloads (base64 JSON with iv/value/mac/tag) can exceed 255
 * chars for longer passwords — widen to TEXT before enabling the casts.
 * Non-destructive: existing values are preserved as-is; the one-time
 * re-encryption of legacy plaintext runs via `php artisan credentials:reencrypt`
 * immediately after this migration (chunked, idempotent).
 *
 * vps_instances.initial_root_password is already TEXT + encrypted cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('root_password')->nullable()->change();
            $table->text('db_password')->nullable()->change();
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->text('db_password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Narrowing back would truncate ciphertext — intentionally irreversible
        // while encrypted data exists. Restore manually only after decrypting.
        // No-op down keeps rollbacks safe.
    }
};