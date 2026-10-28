<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan layanan server lewat tiket.
 *
 * Server pelanggan dibeli retail dari supplier tanpa API, sehingga panel tidak
 * bisa reboot/reinstall sendiri. Aksi yang butuh akses supplier diajukan
 * pelanggan sebagai tiket bertipe khusus, lalu dikerjakan admin secara manual.
 *
 * Kolom tambahan pada support_tickets:
 *   - type         : general (tiket biasa) | reinstall | unreachable
 *   - request_data : detail permintaan, mis. {"os": "ubuntu2404", "control_panel": "coolify"}
 *
 * Tiket lama otomatis bertipe 'general' lewat nilai default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('type', 20)->default('general')->after('subject');
            $table->json('request_data')->nullable()->after('type');

            $table->index(['vps_instance_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        // MySQL bisa memakai indeks gabungan di atas untuk foreign key vps_instance_id
        // dan membuang indeks FK bawaannya. Sediakan indeks tunggal lebih dulu agar
        // indeks gabungan boleh dihapus ("needed in a foreign key constraint").
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->index('vps_instance_id', 'support_tickets_vps_instance_id_rollback_index');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['vps_instance_id', 'type', 'status']);
            $table->dropColumn(['type', 'request_data']);
        });
    }
};
