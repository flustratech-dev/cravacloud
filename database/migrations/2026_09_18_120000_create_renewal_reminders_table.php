<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 3 - Renewal Reminder dan Siklus Hidup Layanan.
 *
 * Tabel yang dibuat:
 *   - renewal_reminders : pencatat tahap pengingat yang sudah terkirim per instance.
 *
 * Indeks unik pada (vps_instance_id, stage) adalah pengaman utama terhadap
 * surel ganda ketika scheduler berjalan dua kali atau server dinyalakan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vps_instance_id')->constrained('vps_instances')->cascadeOnDelete();
            // h_minus_3 | h_minus_1 | h_zero | grace_ended
            $table->string('stage', 20);
            // Siklus keberapa, agar pengingat bisa dikirim ulang setelah perpanjangan.
            // dateTime: menghindari batasan default implisit kolom timestamp di MySQL.
            $table->dateTime('period_expires_at');
            $table->dateTime('sent_at');
            $table->timestamps();

            // Pengaman idempotensi: satu tahap hanya sekali per periode.
            $table->unique(['vps_instance_id', 'stage', 'period_expires_at'], 'renewal_reminders_unique_stage');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_reminders');
    }
};
