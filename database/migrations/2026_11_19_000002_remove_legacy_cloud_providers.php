<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY provider VARCHAR(30) NOT NULL DEFAULT 'cravacloud'");
        }

        DB::table('vps_specs')->update([
            'allowed_providers' => json_encode(['cravacloud']),
            'default_provider' => 'cravacloud',
            'updated_at' => now(),
        ]);

        DB::table('orders')
            ->whereIn('provider', ['tencent', 'cloudeka'])
            ->update(['provider' => 'cravacloud']);

        DB::table('vps_instances')
            ->whereIn('provider', ['tencent', 'cloudeka'])
            ->update(['provider' => 'cravacloud']);
    }

    public function down(): void
    {
        // Tidak mengembalikan vendor provider eksternal yang sudah dinonaktifkan.
    }
};
