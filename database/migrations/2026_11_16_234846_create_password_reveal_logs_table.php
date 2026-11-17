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
        Schema::create('password_reveal_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_employee_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('target_employee_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('action', ['Revealed', 'Reset']);
            $table->string('reason', 255);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_employee_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reveal_logs');
    }
};
