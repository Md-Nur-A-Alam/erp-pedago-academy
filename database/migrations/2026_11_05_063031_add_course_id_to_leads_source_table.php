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
        Schema::table('leads_source', function (Blueprint $table): void {
            $table->foreignId('course_id')
                ->nullable()
                ->after('campaign_name')
                ->constrained('courses')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads_source', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_id');
        });
    }
};
