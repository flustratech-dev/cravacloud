<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 2 - Legal, AUP, dan Notifikasi Insiden.
 *
 * Tabel yang dibuat:
 *   - terms_acceptances      : bukti persetujuan ketentuan saat checkout (clickwrap)
 *   - notification_templates : template surel yang dapat disunting tanpa deploy
 *   - abuse_cases            : catatan kasus pelanggaran AUP
 *   - broadcast_logs         : riwayat pengiriman broadcast darurat
 *
 * Kolom tambahan:
 *   - orders.terms_version   : versi ketentuan yang disetujui saat pesanan dibuat
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------
        // terms_acceptances : bukti hukum bahwa pelanggan menyetujui AUP.
        // Tanpa terms_version, kita tidak bisa membuktikan versi mana yang disetujui.
        // ------------------------------------------------------------
        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('terms_version', 20);
            // dateTime: menghindari batasan default implisit kolom timestamp di MySQL.
            $table->dateTime('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'terms_version']);
            $table->index('order_id');
        });

        // ------------------------------------------------------------
        // notification_templates : subjek dan isi surel yang dapat disunting admin.
        // ------------------------------------------------------------
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('subject');
            $table->longText('body');
            // Daftar placeholder yang tersedia, mis. ["nama","layanan"].
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ------------------------------------------------------------
        // abuse_cases : catatan pelanggaran AUP per instance.
        // ------------------------------------------------------------
        Schema::create('abuse_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vps_instance_id')->nullable()->constrained('vps_instances')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Jenis pelanggaran mengikuti daftar larangan di AUP.
            $table->string('type', 50);
            $table->text('evidence')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'investigating', 'notified', 'resolved', 'terminated'])
                ->default('open');
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index('user_id');
        });

        // ------------------------------------------------------------
        // broadcast_logs : riwayat broadcast darurat ke pelanggan.
        // ------------------------------------------------------------
        Schema::create('broadcast_logs', function (Blueprint $table) {
            $table->id();
            $table->string('template_code', 60)->nullable();
            $table->string('subject');
            $table->longText('body');
            // all | active_customers | affected_instances
            $table->string('audience', 40)->default('all');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index('sent_at');
        });

        // ------------------------------------------------------------
        // orders.terms_version : menandai versi ketentuan saat pemesanan.
        // ------------------------------------------------------------
        Schema::table('orders', function (Blueprint $table) {
            $table->string('terms_version', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('terms_version');
        });

        Schema::dropIfExists('broadcast_logs');
        Schema::dropIfExists('abuse_cases');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('terms_acceptances');
    }
};
