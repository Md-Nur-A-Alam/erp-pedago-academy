<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Employee::factory(10)->create();

        $this->createInitialAdmin();

        $this->call(SalesLeadManagementSeeder::class);
    }

    private function createInitialAdmin(): void
    {
        $phoneNumber = config('app.initial_admin.phone_number');
        $password = config('app.initial_admin.password');

        if (blank($phoneNumber) && blank($password)) {
            return;
        }

        if (blank($phoneNumber) || blank($password)) {
            throw new RuntimeException('Set both INITIAL_ADMIN_PHONE and INITIAL_ADMIN_PASSWORD to bootstrap the first admin.');
        }

        if (Str::length($password) < 6 || Str::length($password) > 12) {
            throw new RuntimeException('INITIAL_ADMIN_PASSWORD must contain between 6 and 12 characters.');
        }

        $phoneNumber = User::normalizePhoneNumber($phoneNumber);

        if (! preg_match('/^\+?[0-9][0-9]{6,23}$/', $phoneNumber)) {
            throw new RuntimeException('INITIAL_ADMIN_PHONE must be a valid phone number with 7 to 24 digits and an optional leading +.');
        }

        DB::transaction(function () use ($phoneNumber, $password): void {
            $user = User::firstOrCreate(
                ['phone_number' => $phoneNumber],
                ['password' => $password, 'must_change_password' => true],
            );

            if (! $user->wasRecentlyCreated) {
                $employee = $user->employee;

                if ($employee === null || ! $employee->isAdmin()) {
                    throw new RuntimeException('The configured initial admin phone number is already assigned to a non-admin account.');
                }

                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => true,
                ])->save();

                $employee->details()->firstOrCreate([]);

                return;
            }

            $employee = Employee::create([
                'user_id' => $user->id,
                'name' => config('app.initial_admin.name') ?: 'Initial Administrator',
                'office_id' => config('app.initial_admin.office_id') ?: 'ADMIN-001',
                'designation' => config('app.initial_admin.designation') ?: 'Administrator',
                'joining_date' => config('app.initial_admin.joining_date') ?: today()->toDateString(),
                'role' => 'Admin',
                'status' => 'Active',
            ]);

            $employee->details()->create([]);
        });
    }
}
