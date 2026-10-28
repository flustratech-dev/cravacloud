<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 1 - Pengaturan Sistem, Branding, dan Maintenance.
 *
 * Tabel yang dibuat:
 *   - settings            : key-value bertipe untuk branding, profil perusahaan, dan maintenance
 *   - maintenance_windows : jadwal maintenance terencana beserta cakupan terdampak
 *   - system_components   : komponen yang statusnya tampil di halaman status publik
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------
        // settings : penyimpanan key-value bertipe.
        // Dibaca lewat App\Services\SettingsService yang meng-cache hasilnya.
        // ------------------------------------------------------------
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->longText('value')->nullable();
            // string|boolean|integer|json|file — menentukan cara value di-cast saat dibaca.
            $table->string('type', 20)->default('string');
            // Grup dipakai untuk memisahkan tab di halaman pengaturan admin.
            $table->string('group', 50)->default('general');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            // Nilai public boleh dibaca halaman publik. Kredensial TIDAK pernah public.
            $table->boolean('is_public')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'sort_order']);
        });

        // ------------------------------------------------------------
        // maintenance_windows : jadwal maintenance (Tingkat 3).
        // ------------------------------------------------------------
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // Daftar cakupan terdampak, mis. ["checkout","provisioning"].
            $table->json('scopes')->nullable();
            // Pembatas opsional: hanya paket / region tertentu yang ditutup.
            $table->json('spec_ids')->nullable();
            $table->json('region_ids')->nullable();
            // dateTime dipakai alih-alih timestamp: MySQL strict mode menolak
            // dua kolom timestamp non-nullable tanpa default eksplisit.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])
                ->default('scheduled');
            // Spanduk mulai tampil H-x sebelum jadwal.
            $table->unsignedTinyInteger('notice_days')->default(3);
            $table->boolean('notify_customers')->default(true);
            $table->dateTime('customers_notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index('ends_at');
        });

        // ------------------------------------------------------------
        // system_components : status per komponen untuk halaman status.
        // ------------------------------------------------------------
        Schema::create('system_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['operational', 'degraded', 'maintenance', 'outage'])
                ->default('operational');
            // Catatan singkat yang tampil di halaman status saat status bukan operational.
            $table->string('status_note')->nullable();
            $table->decimal('uptime_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_components');
        Schema::dropIfExists('maintenance_windows');
        Schema::dropIfExists('settings');
    }
};
