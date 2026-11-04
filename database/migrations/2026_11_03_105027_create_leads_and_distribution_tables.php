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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('leads_source', 'source_id')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('student_name', 150);
            $table->string('contact_number', 25)->index();
            $table->string('alternative_contact_number', 25)->nullable()->index();
            $table->string('email', 120)->nullable();
            $table->string('location', 255)->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('class', 50)->nullable();
            $table->string('student_parent_type', 30)->nullable();
            $table->text('student_background')->nullable();
            $table->string('ad_set_id', 100)->nullable();
            $table->foreignId('interested_course_id')->nullable()->constrained('courses')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('status', ['New', 'Assigned', 'Contacted', 'Follow-up', 'Interested', 'Enrolled - Pending Payment', 'Payment Under Review', 'Converted', 'Not Interested', 'Unreachable', 'Lost'])->default('New')->index();
            $table->foreignId('current_owner_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('previous_owner_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('assigned_by_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('last_action_at')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('lead_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('assigned_by_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('batch_size')->default(3);
            $table->enum('status', ['Active', 'Completed'])->default('Active');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('lead_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('lead_batches')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->boolean('is_completed')->default(false);
            $table->string('action_taken', 50)->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::create('lead_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('call_status', 50);
            $table->string('customer_interest', 50);
            $table->text('customer_concern')->nullable();
            $table->text('sales_note');
            $table->string('next_action', 50);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->cascadeOnUpdate();
            $table->dateTime('followup_date_time')->index();
            $table->text('note')->nullable();
            $table->boolean('is_done')->default(false);
            $table->boolean('overdue_alert_sent')->default(false);
            $table->timestamps();
        });

        Schema::create('lead_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('from_employee_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('to_employee_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('from_team_id')->constrained('teams')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('to_team_id')->constrained('teams')->restrictOnDelete()->cascadeOnUpdate();
            $table->text('reason');
            $table->foreignId('sender_tl_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('sender_tl_approved_at')->nullable();
            $table->foreignId('receiver_tl_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('receiver_tl_approved_at')->nullable();
            $table->boolean('is_auto_transferred')->default(false);
            $table->boolean('is_admin_override')->default(false);
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('duplicate_lead_reviews', function (Blueprint $table) {
            $table->id();
            $table->json('incoming_lead_data');
            $table->foreignId('matched_lead_id')->constrained('leads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('match_reason', 255);
            $table->enum('status', ['Pending Review', 'Approved New', 'Merged', 'Discarded'])->default('Pending Review');
            $table->foreignId('reviewed_by_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_lead_reviews');
        Schema::dropIfExists('lead_transfers');
        Schema::dropIfExists('lead_followups');
        Schema::dropIfExists('lead_feedbacks');
        Schema::dropIfExists('lead_batch_items');
        Schema::dropIfExists('lead_batches');
        Schema::dropIfExists('leads');
    }
};
