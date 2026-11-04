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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('team_leader_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('supervisor_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('employees')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['supervisor_id', 'team_id']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->text('conditions')->nullable();
            $table->enum('subject', ['English', 'Bangla', 'Mathematics', 'Science', 'Programming', 'General'])->default('General');
            $table->enum('version', ['Bangla', 'English'])->default('Bangla');
            $table->string('category', 100)->nullable();
            $table->decimal('base_fee', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('leads_source', function (Blueprint $table) {
            $table->id('source_id');
            $table->enum('channel', ['fb', 'wp', 'googleads', 'gptads', 'googleform', 'reference', 'website', 'phone', 'event', 'manual', 'other']);
            $table->string('campaign_name', 150)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads_source');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('supervisor_teams');
        Schema::dropIfExists('teams');
    }
};
