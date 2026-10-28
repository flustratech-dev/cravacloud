<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('provider', 30)->default('tencent')->after('control_panel');
            $table->string('hostname', 63)->nullable()->after('provider');
            $table->index('provider');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY os VARCHAR(80) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropColumn(['provider', 'hostname']);
        });
    }
};
