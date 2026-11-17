<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields and tables the interim Google Sheets script used that the application did not have yet.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->enum('status', ['New', 'Assigned', 'Contacted', 'Follow-up', 'Interested', 'Enrolled - Pending Payment', 'Payment Under Review', 'Converted', 'Not Interested', 'Unreachable', 'Lost', 'Invalid/Junk'])->default('New')->change();
            $table->enum('priority', ['High', 'Medium', 'Low'])->nullable()->after('status');
            $table->text('remarks')->nullable()->after('student_background');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->enum('assign_mode', ['Auto', 'Manual'])->default('Auto');
            $table->unsignedSmallInteger('daily_lead_cap')->nullable();
            $table->date('auto_count_date')->nullable();
            $table->unsignedSmallInteger('auto_count_today')->default(0);
        });

        Schema::create('lead_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('phone_number', 25)->unique();
            $table->string('label', 50)->nullable();
            $table->foreignId('added_by_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('lead_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('interested_course_id')->nullable();
            $table->string('outcome', 60)->nullable();
            $table->foreignId('created_by_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('lead_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->string('type', 40);
            $table->string('note', 500);
            $table->timestamp('created_at')->nullable();
            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_events');
        Schema::dropIfExists('lead_inquiries');
        Schema::dropIfExists('lead_phones');

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['assign_mode', 'daily_lead_cap', 'auto_count_date', 'auto_count_today']);
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn(['priority', 'remarks']);
        });
    }
};
