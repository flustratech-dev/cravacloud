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
        Schema::table('vps_specs', function (Blueprint $table) {
            $table->boolean('is_renewable')->default(true)->after('is_active');
            $table->foreignId('replacement_spec_id')->nullable()->after('is_renewable')->constrained('vps_specs')->nullOnDelete();
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->decimal('custom_renewal_price', 12, 2)->nullable()->after('auto_renew');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type', 20)->default('new')->after('billing_cycle');
            $table->foreignId('vps_instance_id')->nullable()->after('order_type')->constrained('vps_instances')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['vps_instance_id']);
            }
            $table->dropColumn(['order_type', 'vps_instance_id']);
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->dropColumn('custom_renewal_price');
        });

        Schema::table('vps_specs', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['replacement_spec_id']);
            }
            $table->dropColumn(['is_renewable', 'replacement_spec_id']);
        });
    }
};
