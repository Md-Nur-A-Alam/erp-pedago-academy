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
        Schema::table('lead_transfers', function (Blueprint $table): void {
            $table->foreignId('decided_by_employee_id')->nullable()->after('status')
                ->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->string('decision_note', 500)->nullable()->after('decided_by_employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_transfers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('decided_by_employee_id');
            $table->dropColumn('decision_note');
        });
    }
};
