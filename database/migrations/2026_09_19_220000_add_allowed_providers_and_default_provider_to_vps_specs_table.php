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
            $table->json('allowed_providers')->nullable()->after('is_active');
            $table->string('default_provider', 50)->nullable()->after('allowed_providers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vps_specs', function (Blueprint $table) {
            $table->dropColumn(['allowed_providers', 'default_provider']);
        });
    }
};
