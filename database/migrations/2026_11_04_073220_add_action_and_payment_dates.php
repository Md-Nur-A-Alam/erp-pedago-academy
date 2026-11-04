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
        Schema::table('lead_feedbacks', function (Blueprint $table): void {
            $table->timestamp('action_date')->nullable()->after('next_action');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('payment_date')->nullable()->after('amount');
            $table->renameColumn('verified_at', 'verified_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->renameColumn('verified_date', 'verified_at');
            $table->dropColumn('payment_date');
        });

        Schema::table('lead_feedbacks', function (Blueprint $table): void {
            $table->dropColumn('action_date');
        });
    }
};
