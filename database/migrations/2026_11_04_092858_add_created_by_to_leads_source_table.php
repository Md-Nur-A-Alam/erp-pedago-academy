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
        Schema::table('leads_source', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('created_at')
                ->constrained('employees')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads_source', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
