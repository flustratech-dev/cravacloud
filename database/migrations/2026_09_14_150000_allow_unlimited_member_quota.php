<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_quotas', function (Blueprint $table) {
            // NULL is the explicit value for an unlimited quota (system admin).
            $table->unsignedInteger('max_members')->nullable()->default(5)->change();
        });
    }

    public function down(): void
    {
        Schema::table('organization_quotas', function (Blueprint $table) {
            $table->unsignedInteger('max_members')->default(5)->change();
        });
    }
};
