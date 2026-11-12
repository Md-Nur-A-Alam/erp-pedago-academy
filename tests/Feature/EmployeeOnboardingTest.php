<?php

use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createOnboardedEmployee(string $role = 'Sales Executive', array $attributes = []): array
{
    $user = User::factory()->create([
        'phone_number' => fake()->unique()->numerify('+8801#########'),
        'password' => 'Temp.1234',
        'must_change_password' => false,
    ]);

    $employee = Employee::factory()->create(array_merge([
        'user_id' => $user->id,
        'role' => $role,
    ], $attributes));

    $employee->details()->create([
        'dob' => '1990-01-01',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_number' => '+8801700000000',
        'blood_group' => 'A+',
        'present_address' => 'Present address',
        'permanent_address' => 'Permanent address',
    ]);

    return [$user, $employee];
}

it('requires a first-login password change and profile completion before opening the dashboard', function () {
    $user = User::factory()->create([
        'phone_number' => '+8801700000000',
        'password' => 'Temp.1234',
        'must_change_password' => true,
    ]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'role' => 'Sales Executive',
    ]);

    $this->post(route('login.store'), [
        'phone_number' => '+880 1700-000-000',
        'password' => 'Temp.1234',
    ])->assertRedirect(route('password.change.edit'));

    $this->get(route('dashboard'))->assertRedirect(route('password.change.edit'));

    $this->put(route('password.change.update'), [
        'current_password' => 'Temp.1234',
        'password' => 'NewPass123',
        'password_confirmation' => 'NewPass123',
    ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

    expect($user->refresh()->must_change_password)->toBeFalse();
    expect(Hash::check('NewPass123', $user->password))->toBeTrue();

    $this->get(route('dashboard'))->assertRedirect(route('profile.edit'));

    $this->put(route('profile.update'), [
        'name' => $employee->name,
        'office_id' => $employee->office_id,
        'designation' => $employee->designation,
        'joining_date' => $employee->joining_date->format('Y-m-d'),
        'phone_number' => $user->phone_number,
        'dob' => '1990-01-01',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_number' => '+8801700000000',
        'blood_group' => 'A+',
        'present_address' => 'Present address',
        'permanent_address' => 'Permanent address',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->get(route('dashboard'))->assertOk();
    expect($employee->fresh()->isProfileComplete())->toBeTrue();
});

it('accepts new passwords at both length boundaries', function (string $password) {
    [$user] = createOnboardedEmployee();
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'Temp.1234',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    expect(Hash::check($password, $user->refresh()->password))->toBeTrue();
})->with([
    'minimum length' => 'A12345',
    'maximum length' => 'Abcdef123456',
]);

it('rejects new passwords outside the allowed length without changing the existing password', function (string $password) {
    [$user] = createOnboardedEmployee();
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'Temp.1234',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect()
        ->assertSessionHasErrors('password');

    expect(Hash::check('Temp.1234', $user->refresh()->password))->toBeTrue();
})->with([
    'below the minimum' => '12345',
    'above the maximum' => '1234567890123',
]);

it('lets only a higher authority approve essential employee changes', function () {
    [$requesterUser, $requester] = createOnboardedEmployee('Sales Executive');
    [$teamLeaderUser] = createOnboardedEmployee('Team Leader');

    $this->actingAs($requesterUser)->put(route('profile.update'), [
        'office_id' => 'EMP-NEW-42',
        'phone_number' => '+8801712345678',
        'reason' => 'My employment record needs to be corrected.',
    ])->assertRedirect();

    $changeRequest = EmployeeChangeRequest::query()->sole();

    expect($changeRequest->requested_changes)->toBe([
        'office_id' => 'EMP-NEW-42',
        'phone_number' => '+8801712345678',
    ]);

    $this->actingAs($requesterUser)
        ->put(route('employee-change-requests.update', $changeRequest), [
            'decision' => 'Approved',
        ])
        ->assertForbidden();

    $this->actingAs($teamLeaderUser)
        ->put(route('employee-change-requests.update', $changeRequest), [
            'decision' => 'Approved',
            'review_note' => 'Verified.',
        ])
        ->assertRedirect();

    expect($changeRequest->fresh()->status)->toBe('Approved');
    expect($requester->fresh()->office_id)->toBe('EMP-NEW-42');
    expect($requester->user->fresh()->phone_number)->toBe('+8801712345678');
});

it('does not apply an invalid phone number from a corrupted change request', function () {
    [$requesterUser, $requester] = createOnboardedEmployee('Sales Executive');
    [$approverUser] = createOnboardedEmployee('Team Leader');
    $changeRequest = $requester->changeRequests()->create([
        'requested_by_employee_id' => $requester->id,
        'requested_changes' => ['phone_number' => '+1234567890123456'],
    ]);

    $this->actingAs($approverUser)->put(route('employee-change-requests.update', $changeRequest), [
        'decision' => 'Approved',
    ])->assertSessionHasErrors('phone_number');

    expect($requesterUser->fresh()->phone_number)->not->toBe('+1234567890123456');
    expect($changeRequest->fresh()->status)->toBe('Pending');
});

it('lets an admin edit essential information directly without an approval request', function () {
    [$adminUser, $admin] = createOnboardedEmployee('Admin');

    $this->actingAs($adminUser)->put(route('profile.update'), [
        'name' => 'Updated Admin',
        'office_id' => 'EMP-ADMIN-UPDATED',
        'designation' => 'Operations Admin',
        'joining_date' => $admin->joining_date->format('Y-m-d'),
        'phone_number' => '+8801712345679',
        'role' => 'Admin',
        'status' => 'Active',
        'reason' => 'Admin-approved profile correction.',
    ])->assertRedirect();

    expect($admin->fresh()->name)->toBe('Updated Admin');
    expect($admin->user->fresh()->phone_number)->toBe('+8801712345679');
    expect(EmployeeChangeRequest::query()->count())->toBe(0);
});

it('normalizes international secondary and emergency contact numbers on profile updates', function () {
    [$user, $employee] = createOnboardedEmployee();

    $this->actingAs($user)->put(route('profile.update'), [
        'secondary_contact_number' => '+1 (415) 555-2671',
        'emergency_contact_number' => '0044 20 7946 0958',
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($employee->details->fresh()->secondary_contact_number)->toBe('+14155552671');
    expect($employee->details->fresh()->emergency_contact_number)->toBe('+442079460958');
});

it('rejects invalid secondary and emergency phone numbers on profile updates', function (string $phoneField) {
    [$user] = createOnboardedEmployee();

    $this->actingAs($user)->put(route('profile.update'), [
        $phoneField => '+1234567890123456',
    ])->assertRedirect()
        ->assertSessionHasErrors($phoneField);
})->with([
    'secondary contact' => 'secondary_contact_number',
    'emergency contact' => 'emergency_contact_number',
]);

it('lets an admin edit another employee and close any superseded pending request', function () {
    [$adminUser, $admin] = createOnboardedEmployee('Admin');
    [$employeeUser, $employee] = createOnboardedEmployee('Sales Executive');
    $changeRequest = $employee->changeRequests()->create([
        'requested_by_employee_id' => $employee->id,
        'requested_changes' => ['designation' => 'Senior Sales Executive'],
    ]);

    $this->actingAs($adminUser)->put(route('employees.update', $employee), [
        'name' => $employee->name,
        'office_id' => $employee->office_id,
        'designation' => 'Sales Consultant',
        'joining_date' => $employee->joining_date->format('Y-m-d'),
        'phone_number' => $employeeUser->phone_number,
        'role' => 'Sales Executive',
        'status' => 'Active',
    ])->assertRedirect();

    expect($employee->fresh()->designation)->toBe('Sales Consultant');
    expect($changeRequest->fresh()->status)->toBe('Rejected');
    expect($changeRequest->fresh()->reviewed_by_employee_id)->toBe($admin->id);
});

it('allows an admin to create a MarketingExecutive account with a forced password change', function () {
    [$adminUser] = createOnboardedEmployee('Admin');

    $this->actingAs($adminUser)->from(route('employees.create'))->post(route('employees.store'), [
        'name' => 'New Employee',
        'office_id' => 'EMP-NEW-100',
        'designation' => 'Sales Executive',
        'joining_date' => '2026-11-04',
        'role' => 'MarketingExecutive',
        'phone_number' => '+880 1712-345-680',
        'password' => 'A12345',
        'password_confirmation' => 'A12345',
    ])->assertRedirect(route('employees.index'));

    $newUser = User::query()->where('phone_number', '+8801712345680')->sole();

    expect($newUser->must_change_password)->toBeTrue();
    expect($newUser->employee->office_id)->toBe('EMP-NEW-100');
    expect($newUser->employee->role)->toBe('MarketingExecutive');
    expect(Hash::check('A12345', $newUser->password))->toBeTrue();
});

it('normalizes international employee phone numbers when an admin creates an account', function () {
    [$adminUser] = createOnboardedEmployee('Admin');

    $this->actingAs($adminUser)->from(route('employees.create'))->post(route('employees.store'), [
        'name' => 'International Employee',
        'office_id' => 'EMP-INTL-101',
        'designation' => 'Sales Executive',
        'joining_date' => '2026-11-04',
        'role' => 'Sales Executive',
        'phone_number' => '+44 (20) 7946-0958',
        'password' => 'A12345',
        'password_confirmation' => 'A12345',
    ])->assertRedirect(route('employees.index'))
        ->assertSessionHasNoErrors();

    expect(User::query()->where('phone_number', '+442079460958')->exists())->toBeTrue();
});

it('rejects employee phone numbers exceeding the international digit limit', function () {
    [$adminUser] = createOnboardedEmployee('Admin');

    $this->actingAs($adminUser)->from(route('employees.create'))->post(route('employees.store'), [
        'name' => 'Invalid Phone Employee',
        'office_id' => 'EMP-INVALID-102',
        'designation' => 'Sales Executive',
        'joining_date' => '2026-11-04',
        'role' => 'Sales Executive',
        'phone_number' => '+1234567890123456',
        'password' => 'A12345',
        'password_confirmation' => 'A12345',
    ])->assertRedirect()
        ->assertSessionHasErrors('phone_number');

    expect(User::query()->where('phone_number', '+1234567890123456')->exists())->toBeFalse();
});

it('rejects employee temporary passwords outside the allowed length', function (string $password) {
    [$adminUser] = createOnboardedEmployee('Admin');

    $this->actingAs($adminUser)->post(route('employees.store'), [
        'name' => 'New Employee',
        'office_id' => 'EMP-INVALID-PASSWORD',
        'designation' => 'Sales Executive',
        'joining_date' => '2026-11-04',
        'role' => 'MarketingExecutive',
        'phone_number' => '+8801712345681',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect()
        ->assertSessionHasErrors('password');

    $this->assertDatabaseMissing('users', ['phone_number' => '+8801712345681']);
})->with([
    'below the minimum' => '12345',
    'above the maximum' => '1234567890123',
]);

it('bootstraps the first admin only from explicit configuration', function () {
    config()->set('app.initial_admin', [
        'name' => 'Bootstrap Admin',
        'phone_number' => '+8801700000123',
        'password' => 'Temp.1234',
        'office_id' => 'ADMIN-BOOTSTRAP',
        'designation' => 'Administrator',
        'joining_date' => '2026-11-04',
    ]);

    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('phone_number', '+8801700000123')->sole();

    expect($user->fresh()->must_change_password)->toBeTrue();
    expect(Hash::check('Temp.1234', $user->password))->toBeTrue();
    expect($user->employee->role)->toBe('Admin');
    expect($user->employee->office_id)->toBe('ADMIN-BOOTSTRAP');
});

it('rejects initial admin passwords outside the allowed length', function (string $password) {
    config()->set('app.initial_admin', [
        'phone_number' => '+8801700000123',
        'password' => $password,
    ]);

    expect(fn () => $this->seed(DatabaseSeeder::class))
        ->toThrow(RuntimeException::class, 'INITIAL_ADMIN_PASSWORD must contain between 6 and 12 characters.');
})->with([
    'below the minimum' => '12345',
    'above the maximum' => '1234567890123',
]);

it('does not promote an existing non-admin account during admin bootstrap', function () {
    [$user, $employee] = createOnboardedEmployee('Sales Executive');

    config()->set('app.initial_admin', [
        'phone_number' => $user->phone_number,
        'password' => 'Temp.1234',
    ]);

    expect(fn () => $this->seed(DatabaseSeeder::class))
        ->toThrow(RuntimeException::class, 'already assigned to a non-admin account');

    expect($employee->fresh()->role)->toBe('Sales Executive');
});

it('resets an existing admin to the configured temporary password', function () {
    [$user, $employee] = createOnboardedEmployee('Admin');

    config()->set('app.initial_admin', [
        'phone_number' => $user->phone_number,
        'password' => 'Temp.1234',
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(Hash::check('Temp.1234', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->must_change_password)->toBeTrue();
    expect($employee->fresh()->role)->toBe('Admin');
});

it('does not let a non-admin create employee accounts', function () {
    [$salesUser] = createOnboardedEmployee('Sales Executive');

    $this->actingAs($salesUser)
        ->get(route('employees.create'))
        ->assertForbidden();
});

it('auto-increments office id sequentially in PED-001 format', function () {
    // Starting with no employees, next should be PED-001
    expect(Employee::nextOfficeId())->toBe('PED-001');

    [$adminUser, $admin] = createOnboardedEmployee('Admin', ['office_id' => 'PED-001']);

    expect(Employee::nextOfficeId())->toBe('PED-002');

    // Create an employee with office_id = PED-045
    createOnboardedEmployee('Sales Executive', ['office_id' => 'PED-045']);
    expect(Employee::nextOfficeId())->toBe('PED-046');

    // Create an employee through the controller without specifying office_id
    $this->actingAs($adminUser)->post(route('employees.store'), [
        'name' => 'Auto Sequenced Staff',
        'designation' => 'Sales Executive',
        'joining_date' => '2026-11-11',
        'role' => 'Sales Executive',
        'phone_number' => '+880 1712-999-001',
        'password' => 'Pass123',
        'password_confirmation' => 'Pass123',
    ])->assertRedirect(route('employees.index'));

    $created = Employee::where('name', 'Auto Sequenced Staff')->firstOrFail();
    expect($created->office_id)->toBe('PED-046');

    // The create form should also show the next auto-incremented office_id
    $this->actingAs($adminUser)->get(route('employees.create'))
        ->assertOk()
        ->assertSee('PED-047');
});
