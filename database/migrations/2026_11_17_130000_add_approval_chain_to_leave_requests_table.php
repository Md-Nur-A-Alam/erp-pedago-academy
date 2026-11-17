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
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->enum('type', ['Casual', 'Sick', 'Annual', 'Maternity', 'Unpaid', 'Other'])->change();
            $table->unsignedTinyInteger('required_approvals')->default(1)->after('status');
            $table->unsignedTinyInteger('approvals_done')->default(0)->after('required_approvals');
            $table->boolean('has_certificate')->default(false)->after('reason');
            $table->foreignId('first_reviewed_by_employee_id')->nullable()->after('approvals_done')->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->string('first_review_note', 500)->nullable()->after('first_reviewed_by_employee_id');
            $table->timestamp('first_reviewed_at')->nullable()->after('first_review_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('first_reviewed_by_employee_id');
            $table->dropColumn(['required_approvals', 'approvals_done', 'has_certificate', 'first_review_note', 'first_reviewed_at']);
        });
    }
};
