<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasTable('employees')) {
            throw new RuntimeException('Expected an existing users table and no employees table before renaming.');
        }

        Schema::rename('users', 'employees');

        Schema::table('employees', function (Blueprint $table) {
            $table->string('office_id', 50)->nullable()->unique();
            $table->string('phone', 25)->nullable()->unique();
            $table->string('secondary_contact_number', 25)->nullable();
            $table->string('emergency_contact_number', 25)->nullable();
            $table->date('dob')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('nid_number', 50)->nullable()->unique();
            $table->string('blood_group', 10)->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->enum('role', ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'Accounts'])->default('Sales Executive');
            $table->enum('status', ['Active', 'Inactive', 'Suspended'])->default('Active');
            $table->renameColumn('updated_at', 'edited_at');
            $table->foreignId('edited_by')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees') || Schema::hasTable('users')) {
            throw new RuntimeException('Expected an employees table and no users table before reverting the rename.');
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['edited_by']);
            $table->dropUnique('employees_nid_number_unique');
            $table->dropUnique('employees_phone_unique');
            $table->dropUnique('employees_office_id_unique');
            $table->dropColumn([
                'office_id',
                'phone',
                'secondary_contact_number',
                'emergency_contact_number',
                'dob',
                'joining_date',
                'nid_number',
                'blood_group',
                'present_address',
                'permanent_address',
                'role',
                'status',
                'edited_by',
            ]);
            $table->renameColumn('edited_at', 'updated_at');
        });

        Schema::rename('employees', 'users');
    }
};
