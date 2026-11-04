<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'Accounts', 'MarketingExecutive') NOT NULL DEFAULT 'Sales Executive'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('employees')->where('role', 'MarketingExecutive')->exists()) {
            throw new RuntimeException('Reassign MarketingExecutive employees before rolling back this migration.');
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'Accounts') NOT NULL DEFAULT 'Sales Executive'");
    }
};
