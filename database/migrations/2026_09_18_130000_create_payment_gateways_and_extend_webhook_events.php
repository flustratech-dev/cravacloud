<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 4 - Payment Gateway dan Webhook Log.
 *
 * Tabel yang dibuat:
 *   - payment_gateways : registry gateway beserta kredensial terenkripsi
 *
 * Kolom tambahan pada webhook_events:
 *   - signature_verified : hanya event bertanda tangan sah yang boleh diproses ulang
 *   - http_status        : kode balasan yang kita kirim ke gateway
 *   - attempts           : jumlah percobaan pemrosesan (webhook ulang + proses ulang admin)
 *   - last_replayed_at   : kapan terakhir diproses ulang oleh admin
 *   - replayed_by        : admin yang memproses ulang
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            // sandbox | production
            $table->string('mode', 20)->default('production');
            // Disimpan terenkripsi (cast encrypted:array). Tidak pernah ditampilkan utuh.
            $table->text('credentials')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->boolean('signature_verified')->default(false)->after('signature');
            $table->unsignedSmallInteger('http_status')->nullable()->after('processing_error');
            $table->unsignedInteger('attempts')->default(1)->after('http_status');
            $table->dateTime('last_replayed_at')->nullable()->after('processed_at');
            $table->foreignId('replayed_by')->nullable()->after('last_replayed_at')
                ->constrained('users')->nullOnDelete();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replayed_by');
            $table->dropIndex(['created_at']);
            $table->dropColumn(['signature_verified', 'http_status', 'attempts', 'last_replayed_at']);
        });

        Schema::dropIfExists('payment_gateways');
    }
};
