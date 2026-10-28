<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY control_panel VARCHAR(80) NOT NULL");
        DB::statement("ALTER TABLE vps_instances MODIFY control_panel VARCHAR(80) NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY control_panel ENUM('coolify','dokploy','cpanel','hermes_omniroute','openclaw_omniroute') NOT NULL");
        DB::statement("ALTER TABLE vps_instances MODIFY control_panel ENUM('coolify','dokploy','cpanel','hermes_omniroute','openclaw_omniroute') NULL");
    }
};
