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
        Schema::table('lead_followups', function (Blueprint $table): void {
            $table->timestamp('reminder_sent_at')->nullable()->after('overdue_alert_sent');
            $table->timestamp('overdue_notified_at')->nullable()->after('reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_followups', function (Blueprint $table): void {
            $table->dropColumn(['reminder_sent_at', 'overdue_notified_at']);
        });
    }
};
