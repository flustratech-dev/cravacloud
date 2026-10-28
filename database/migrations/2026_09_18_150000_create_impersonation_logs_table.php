<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 7 - Catatan sesi impersonation.
 *
 * Setiap sesi "masuk sebagai pelanggan" tercatat lengkap dengan admin pelakunya,
 * pelanggan yang diwakili, waktu mulai, waktu selesai, dan alasan berakhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('impersonated_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            // left | logout | expired | invalid
            $table->string('end_reason', 20)->nullable();
            $table->unsignedInteger('blocked_actions')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['admin_user_id', 'started_at']);
            $table->index(['impersonated_user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');
    }
};
