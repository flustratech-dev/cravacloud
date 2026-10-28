<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('db_engine', 50)->nullable()->after('control_panel');
            $table->string('db_manager', 50)->nullable()->default('cloudbeaver')->after('db_engine');
            $table->string('db_name', 100)->nullable()->default('cravadb_production')->after('db_manager');
            $table->string('db_user', 100)->nullable()->default('admin_crava')->after('db_name');
            $table->string('db_password', 255)->nullable()->after('db_user');
            $table->integer('db_port')->nullable()->after('db_password');
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->string('db_engine', 50)->nullable()->after('control_panel');
            $table->string('db_manager', 50)->nullable()->default('cloudbeaver')->after('db_engine');
            $table->string('db_name', 100)->nullable()->default('cravadb_production')->after('db_manager');
            $table->string('db_user', 100)->nullable()->default('admin_crava')->after('db_name');
            $table->string('db_password', 255)->nullable()->after('db_user');
            $table->integer('db_port')->nullable()->after('db_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vps_instances', function (Blueprint $table) {
            $table->dropColumn(['db_engine', 'db_manager', 'db_name', 'db_user', 'db_password', 'db_port']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['db_engine', 'db_manager', 'db_name', 'db_user', 'db_password', 'db_port']);
        });
    }
};
