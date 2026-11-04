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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained('leads')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('sales_executive_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('secure_uuid', 64)->unique();
            $table->string('student_name', 150);
            $table->string('student_phone', 25);
            $table->string('student_email', 120)->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_phone', 25)->nullable();
            $table->decimal('base_course_fee', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('final_deal_amount', 10, 2);
            $table->unsignedInteger('total_installments')->default(1);
            $table->enum('status', ['Pending Payment', 'Payment Under Review', 'Enrolled', 'Cancelled'])->default('Pending Payment');
            $table->timestamps();
        });

        Schema::create('installment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('installment_number');
            $table->decimal('due_amount', 10, 2);
            $table->date('due_date')->index();
            $table->boolean('followup_generated')->default(false);
            $table->enum('status', ['Pending', 'Under Review', 'Paid', 'Overdue'])->default('Pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['enrollment_id', 'installment_number']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('installment_schedule_id')->nullable()->constrained('installment_schedules')->nullOnDelete()->cascadeOnUpdate();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['bKash', 'SSLCommerz', 'Manual Bank', 'Manual MFS']);
            $table->string('transaction_id', 100)->nullable();
            $table->string('slip_screenshot_path', 255)->nullable();
            $table->foreignId('verified_by_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('status', ['Pending', 'Verified', 'Rejected'])->default('Pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('enrollments');
    }
};
