<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix enum columns: add missing status values used by controllers and views.
     *
     * vps_instances.status: add 'suspended', 'terminated', 'rebooting', 'reinstalling'
     * orders.status: add 'terminated'
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Expand vps_instances.status enum
            DB::statement("ALTER TABLE `vps_instances` MODIFY COLUMN `status` ENUM('running','stopped','provisioning','error','suspended','terminated','rebooting','reinstalling') NOT NULL DEFAULT 'provisioning'");

            // Expand orders.status enum
            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','provisioning','active','cancelled','expired','terminated') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Revert to original enum values.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `vps_instances` MODIFY COLUMN `status` ENUM('running','stopped','provisioning','error') NOT NULL DEFAULT 'provisioning'");

            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','provisioning','active','cancelled','expired') NOT NULL DEFAULT 'pending'");
        }
    }
};
