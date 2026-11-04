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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->time('shift_start_time');
            $table->time('shift_end_time');
            $table->decimal('working_hours', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('employee_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('roster_date');
            $table->boolean('is_on_duty')->default(false);
            $table->enum('availability_status', ['Available', 'Busy', 'Unavailable', 'Off Duty', 'Lazy', 'Resigned'])->default('Off Duty');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'roster_date']);
            $table->index(['roster_date', 'availability_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_rosters');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
        });

        Schema::dropIfExists('shifts');
    }
};
