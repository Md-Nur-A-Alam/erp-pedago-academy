<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_rosters', function (Blueprint $table) {
            $table->enum('availability_status', [
                'Available',
                'Busy',
                'Cut Off',
                'Holiday',
                'Unavailable',
                'Off Duty',
                'Lazy',
                'Resigned',
            ])->default('Off Duty')->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_rosters', function (Blueprint $table) {
            $table->enum('availability_status', [
                'Available',
                'Busy',
                'Unavailable',
                'Off Duty',
                'Lazy',
                'Resigned',
            ])->default('Off Duty')->change();
        });
    }
};
