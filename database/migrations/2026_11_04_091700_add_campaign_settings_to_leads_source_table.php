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
            $table->enum('distribution_mode', ['Auto', 'Manual'])->default('Auto');
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads_source', function (Blueprint $table) {
            $table->dropColumn(['distribution_mode', 'is_active']);
        });
    }
};
