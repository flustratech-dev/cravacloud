<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->after('id');
            $table->renameColumn('name', 'full_name');
            $table->string('phone', 20)->nullable()->after('full_name');
            $table->string('company')->nullable()->after('phone');
            $table->text('address')->nullable()->after('company');
            $table->enum('channel', ['website', 'shopee'])->default('website')->after('address');
            $table->string('shopee_order_id')->nullable()->after('channel');
            $table->boolean('is_admin')->default(false)->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'phone', 'company', 'address', 'channel', 'shopee_order_id', 'is_admin']);
        });
    }
};
