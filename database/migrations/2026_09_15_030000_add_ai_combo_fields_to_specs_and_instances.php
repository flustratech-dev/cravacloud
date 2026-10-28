<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vps_specs', function (Blueprint $table) {
            $table->string('category', 50)->default('vps')->after('name');
            $table->string('tagline')->nullable()->after('bandwidth');
            $table->string('target_audience')->nullable()->after('tagline');
            $table->json('features')->nullable()->after('target_audience');
            $table->text('solution')->nullable()->after('features');
            $table->string('badge', 50)->nullable()->after('solution');
            $table->string('default_stack', 50)->nullable()->after('badge');
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->string('app_url')->nullable()->after('uptime_percent');
            $table->string('app_name', 100)->nullable()->after('app_url');
            $table->text('app_guide')->nullable()->after('app_name');
        });
    }

    public function down(): void
    {
        Schema::table('vps_instances', function (Blueprint $table) {
            $table->dropColumn(['app_url', 'app_name', 'app_guide']);
        });

        Schema::table('vps_specs', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'tagline',
                'target_audience',
                'features',
                'solution',
                'badge',
                'default_stack',
            ]);
        });
    }
};
