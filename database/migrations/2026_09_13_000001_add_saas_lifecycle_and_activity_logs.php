<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vps_instances', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('customer_id')->constrained('orders')->nullOnDelete();
            $table->string('datacenter_location', 50)->nullable()->default('indonesia')->after('os');
            $table->integer('ssh_port')->default(22)->after('public_ip');
            $table->text('initial_root_password')->nullable()->after('ssh_port');
            $table->timestamp('root_password_revealed_at')->nullable()->after('initial_root_password');
            $table->string('billing_cycle', 30)->default('monthly')->after('control_panel');
            $table->timestamp('starts_at')->nullable()->after('billing_cycle');
            $table->timestamp('expires_at')->nullable()->after('starts_at');
            $table->timestamp('grace_period_ends_at')->nullable()->after('expires_at');
            $table->boolean('auto_renew')->default(true)->after('grace_period_ends_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_cycle', 30)->default('monthly')->after('os');
            $table->timestamp('starts_at')->nullable()->after('paid_at');
            $table->timestamp('expires_at')->nullable()->after('starts_at');
        });

        Schema::create('vps_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vps_instance_id')->constrained('vps_instances')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('completed');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_activity_logs');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'starts_at', 'expires_at']);
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn([
                'order_id',
                'datacenter_location',
                'ssh_port',
                'initial_root_password',
                'root_password_revealed_at',
                'billing_cycle',
                'starts_at',
                'expires_at',
                'grace_period_ends_at',
                'auto_renew',
            ]);
        });
    }
};
