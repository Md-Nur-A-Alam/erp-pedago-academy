<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'Accounts', 'MarketingExecutive', 'Developer') NOT NULL DEFAULT 'Sales Executive'");
        }

        Schema::create('developer_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('developer_employee_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('action', 50);
            $table->foreignId('target_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->string('details', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['developer_employee_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('developer_activity_logs');

        if (DB::table('employees')->where('role', 'Developer')->exists()) {
            throw new RuntimeException('Change the role of Developer employees before rolling back this migration.');
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'Accounts', 'MarketingExecutive') NOT NULL DEFAULT 'Sales Executive'");
        }
    }
};
