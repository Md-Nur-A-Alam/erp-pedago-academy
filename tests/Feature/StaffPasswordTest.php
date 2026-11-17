<?php

use App\Actions\StaffPasswordCipher;
use App\Actions\StaffPasswordHasher;
use App\Models\Employee;
use App\Models\EmployeeDetails;
use App\Models\PasswordRevealLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createPasswordUser(string $role, string $password = 'Secret123'): User
{
    $user = User::factory()->create(['password' => $password, 'must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role]);
    EmployeeDetails::factory()->for($employee)->create();

    return $user;
}

it('matches the documented test vector and reverses any password', function (string $password) {
    $cipher = new StaffPasswordCipher('KEY');

    expect($cipher->encrypt('abc'))->toBe('ae0b1e')
        ->and($cipher->decrypt($cipher->encrypt($password)))->toBe($password);
})->with(['abc', 'Secret123', 'p@ss W0rd!', 'পাসওয়ার্ড১২', 'a']);

it('stores new passwords in readable form and still verifies them', function () {
    $user = createPasswordUser('Sales Executive', 'Secret123');
    $stored = $user->getRawOriginal('password');

    expect($stored)->toStartWith(StaffPasswordHasher::PREFIX)
        ->and($stored)->not->toContain('Secret123')
        ->and(Hash::check('Secret123', $stored))->toBeTrue()
        ->and(Hash::check('Secret124', $stored))->toBeFalse()
        ->and(Hash::driver()->reveal($stored))->toBe('Secret123');
});

it('accepts an older bcrypt password and converts it at sign-in', function () {
    $user = createPasswordUser('Sales Executive');
    DB::table('users')->where('id', $user->id)->update(['password' => password_hash('OldPass12', PASSWORD_BCRYPT, ['cost' => 4])]);

    $this->post(route('login.store'), ['phone_number' => $user->phone_number, 'password' => 'OldPass12'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect(Hash::driver()->reveal($user->fresh()->getRawOriginal('password')))->toBe('OldPass12');
});

it('shows a password to an admin only after their own password and a reason, and logs it', function () {
    $admin = createPasswordUser('Admin', 'AdminPass1');
    $target = createPasswordUser('Sales Executive', 'Target789');

    $this->actingAs($admin)->get(route('staff-passwords.index'))->assertOk()->assertSee('Readable')->assertDontSee('Target789');

    $this->actingAs($admin)->post(route('staff-passwords.reveal', $target->employee), ['current_password' => 'wrong', 'reason' => 'Customer escalation'])
        ->assertSessionHasErrors('current_password');
    $this->actingAs($admin)->post(route('staff-passwords.reveal', $target->employee), ['current_password' => 'AdminPass1'])
        ->assertSessionHasErrors('reason');
    expect(PasswordRevealLog::query()->count())->toBe(0);

    $this->actingAs($admin)->post(route('staff-passwords.reveal', $target->employee), ['current_password' => 'AdminPass1', 'reason' => 'Customer escalation'])
        ->assertOk()
        ->assertSee('Target789');

    $log = PasswordRevealLog::query()->sole();
    expect($log->admin_employee_id)->toBe($admin->employee->id)
        ->and($log->target_employee_id)->toBe($target->employee->id)
        ->and($log->action)->toBe('Revealed')
        ->and($log->reason)->toBe('Customer escalation');
});

it('resets a password to a temporary one that must be changed', function () {
    $admin = createPasswordUser('Admin', 'AdminPass1');
    $target = createPasswordUser('Team Leader', 'Target789');

    $response = $this->actingAs($admin)->post(route('staff-passwords.reset', $target->employee), ['current_password' => 'AdminPass1', 'reason' => 'Forgot password'])
        ->assertOk()
        ->assertSee('New temporary password for');

    $temporary = $response->viewData('shown')['password'];
    $target->refresh();
    expect(Hash::check($temporary, $target->password))->toBeTrue()
        ->and(Hash::check('Target789', $target->password))->toBeFalse()
        ->and($target->must_change_password)->toBeTrue()
        ->and(PasswordRevealLog::query()->where('action', 'Reset')->count())->toBe(1);
});

it('keeps the password module away from every other role', function (string $role) {
    $user = createPasswordUser($role, 'UserPass12');
    $target = createPasswordUser('Sales Executive', 'Target789');

    $this->actingAs($user)->get(route('staff-passwords.index'))->assertForbidden();
    $this->actingAs($user)->post(route('staff-passwords.reveal', $target->employee), ['current_password' => 'UserPass12', 'reason' => 'Curious'])->assertForbidden();
    $this->actingAs($user)->post(route('staff-passwords.reset', $target->employee), ['current_password' => 'UserPass12', 'reason' => 'Curious'])->assertForbidden();

    expect(PasswordRevealLog::query()->count())->toBe(0)
        ->and(Hash::check('Target789', $target->fresh()->password))->toBeTrue();
})->with(['Supervisor', 'Team Leader', 'Sales Executive', 'Accounts', 'MarketingExecutive']);

it('sends a refreshed or failed password request back to the list instead of an error page', function () {
    $admin = createPasswordUser('Admin', 'AdminPass1');
    $target = createPasswordUser('Sales Executive', 'Target789');

    $this->actingAs($admin)->get(route('staff-passwords.reveal', $target->employee))->assertRedirect(route('staff-passwords.index'));
    $this->actingAs($admin)->get(route('staff-passwords.reset', $target->employee))->assertRedirect(route('staff-passwords.index'));

    $this->actingAs($admin)->from(route('staff-passwords.reveal', $target->employee))
        ->post(route('staff-passwords.reveal', $target->employee), ['current_password' => 'wrong', 'reason' => 'Customer escalation'])
        ->assertRedirect(route('staff-passwords.index'))
        ->assertSessionHasErrors('current_password');
});
