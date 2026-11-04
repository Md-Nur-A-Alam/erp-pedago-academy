<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->validateLegacyPhoneNumbers();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 25)->nullable()->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('designation', 100)->nullable();
        });

        Schema::create('employee_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email')->nullable()->unique();
            $table->string('secondary_contact_number', 25)->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_number', 25)->nullable();
            $table->date('dob')->nullable();
            $table->text('nid_number')->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('requested_by_employee_id')->constrained('employees')->restrictOnDelete()->cascadeOnUpdate();
            $table->json('requested_changes');
            $table->text('reason')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending')->index();
            $table->foreignId('reviewed_by_employee_id')->nullable()->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        DB::table('employees')->orderBy('id')->chunkById(500, function ($employees): void {
            foreach ($employees as $employee) {
                DB::table('users')->insert([
                    'id' => $employee->id,
                    'phone_number' => $employee->phone === null
                        ? null
                        : Str::of($employee->phone)->replaceMatches('/[\s()-]+/', '')->toString(),
                    'password' => $employee->password,
                    'must_change_password' => true,
                    'remember_token' => $employee->remember_token,
                    'created_at' => $employee->created_at,
                    'updated_at' => $employee->edited_at,
                ]);

                DB::table('employee_details')->insert([
                    'employee_id' => $employee->id,
                    'email' => $employee->email,
                    'secondary_contact_number' => $employee->secondary_contact_number,
                    'emergency_contact_number' => $employee->emergency_contact_number,
                    'dob' => $employee->dob,
                    'nid_number' => $employee->nid_number === null ? null : Crypt::encryptString($employee->nid_number),
                    'blood_group' => $employee->blood_group,
                    'present_address' => $employee->present_address,
                    'permanent_address' => $employee->permanent_address,
                    'created_at' => $employee->created_at,
                    'updated_at' => $employee->edited_at,
                ]);
            }
        });

        DB::table('employees')->update(['user_id' => DB::raw('id')]);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_phone_unique');
            $table->dropUnique('employees_nid_number_unique');
            $table->dropUnique('users_email_unique');
            $table->dropColumn([
                'email',
                'email_verified_at',
                'password',
                'phone',
                'secondary_contact_number',
                'emergency_contact_number',
                'dob',
                'nid_number',
                'blood_group',
                'present_address',
                'permanent_address',
                'remember_token',
            ]);
        });
    }

    private function validateLegacyPhoneNumbers(): void
    {
        $employeeIdsByPhoneNumber = [];

        DB::table('employees')
            ->select(['id', 'phone'])
            ->orderBy('id')
            ->chunkById(500, function ($employees) use (&$employeeIdsByPhoneNumber): void {
                foreach ($employees as $employee) {
                    if ($employee->phone === null) {
                        continue;
                    }

                    $phoneNumber = Str::of($employee->phone)
                        ->replaceMatches('/[\s()-]+/', '')
                        ->toString();

                    if (! preg_match('/^\+?[0-9][0-9]{6,23}$/', $phoneNumber)) {
                        throw new RuntimeException("Employee {$employee->id} has a legacy phone number that is not valid for login. Correct it before rerunning this migration.");
                    }

                    if (isset($employeeIdsByPhoneNumber[$phoneNumber])) {
                        $firstEmployeeId = $employeeIdsByPhoneNumber[$phoneNumber];

                        throw new RuntimeException("Employees {$firstEmployeeId} and {$employee->id} have phone numbers that normalize to the same value. Correct them before rerunning this migration.");
                    }

                    $employeeIdsByPhoneNumber[$phoneNumber] = $employee->id;
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('phone', 25)->nullable()->unique();
            $table->string('secondary_contact_number', 25)->nullable();
            $table->string('emergency_contact_number', 25)->nullable();
            $table->date('dob')->nullable();
            $table->string('nid_number', 50)->nullable()->unique();
            $table->string('blood_group', 10)->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->rememberToken();
        });

        DB::table('employees')
            ->leftJoin('users', 'users.id', '=', 'employees.user_id')
            ->leftJoin('employee_details', 'employee_details.employee_id', '=', 'employees.id')
            ->select([
                'employees.id',
                'employees.user_id',
                'users.phone_number',
                'users.password',
                'users.remember_token',
                'users.created_at as user_created_at',
                'employee_details.email',
                'employee_details.secondary_contact_number',
                'employee_details.emergency_contact_number',
                'employee_details.dob',
                'employee_details.nid_number',
                'employee_details.blood_group',
                'employee_details.present_address',
                'employee_details.permanent_address',
            ])
            ->orderBy('employees.id')
            ->chunk(500, function ($employees): void {
                foreach ($employees as $employee) {
                    DB::table('employees')->where('id', $employee->id)->update([
                        'email' => $employee->email,
                        'password' => $employee->password,
                        'phone' => $employee->phone_number,
                        'secondary_contact_number' => $employee->secondary_contact_number,
                        'emergency_contact_number' => $employee->emergency_contact_number,
                        'dob' => $employee->dob,
                        'nid_number' => $employee->nid_number === null ? null : Crypt::decryptString($employee->nid_number),
                        'blood_group' => $employee->blood_group,
                        'present_address' => $employee->present_address,
                        'permanent_address' => $employee->permanent_address,
                        'remember_token' => $employee->remember_token,
                    ]);
                }
            });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('employees_user_id_unique');
            $table->dropColumn(['user_id', 'designation']);
        });

        Schema::dropIfExists('employee_change_requests');
        Schema::dropIfExists('employee_details');
        Schema::dropIfExists('users');
    }
};
