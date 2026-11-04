<?php

use App\Models\Employee;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SalesLeadManagementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSystemSettingsEmployee(string $role): User
{
    $user = User::factory()->create(['must_change_password' => false]);
    $employee = Employee::factory()->for($user)->create(['role' => $role]);
    $employee->details()->create([
        'dob' => '1990-01-01',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_number' => '+8801700000000',
        'blood_group' => 'A+',
        'present_address' => 'Present address',
        'permanent_address' => 'Permanent address',
    ]);

    return $user;
}

it('shows administrators the configured settings and creates missing defaults', function () {
    $admin = createSystemSettingsEmployee('Admin');

    $this->actingAs($admin)->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Default number of leads assigned in a batch.')
        ->assertSee('value="3"', false)
        ->assertSee('value="30"', false);

    expect(SystemSetting::query()->where('key', 'lead_batch_size')->value('value'))->toBe('3');
    expect(SystemSetting::query()->where('key', 'assignment_cutoff_minutes')->value('value'))->toBe('30');
});

it('saves validated settings for administrators', function () {
    $admin = createSystemSettingsEmployee('Admin');
    $this->seed(SalesLeadManagementSeeder::class);

    $this->actingAs($admin)->put(route('settings.update'), [
        'settings' => [
            'lead_batch_size' => '5',
            'assignment_cutoff_minutes' => '45',
        ],
    ])->assertRedirect(route('settings.index'));

    expect(SystemSetting::query()->where('key', 'lead_batch_size')->value('value'))->toBe('5');
    expect(SystemSetting::query()->where('key', 'assignment_cutoff_minutes')->value('value'))->toBe('45');
});

it('rejects out-of-range settings and does not persist unknown settings', function () {
    $admin = createSystemSettingsEmployee('Admin');
    $this->seed(SalesLeadManagementSeeder::class);

    $this->actingAs($admin)->put(route('settings.update'), [
        'settings' => [
            'lead_batch_size' => '51',
            'assignment_cutoff_minutes' => '30',
            'unexpected_setting' => 'unsafe',
        ],
    ])->assertSessionHasErrors(['settings', 'settings.lead_batch_size']);

    expect(SystemSetting::query()->where('key', 'lead_batch_size')->value('value'))->toBe('3');
    expect(SystemSetting::query()->where('key', 'unexpected_setting')->exists())->toBeFalse();
});

it('forbids non-admins from viewing or changing settings', function () {
    $marketingExecutive = createSystemSettingsEmployee('MarketingExecutive');

    $this->actingAs($marketingExecutive)->get(route('settings.index'))->assertForbidden();
    $this->actingAs($marketingExecutive)->put(route('settings.update'), [
        'settings' => [
            'lead_batch_size' => '4',
            'assignment_cutoff_minutes' => '30',
        ],
    ])->assertForbidden();

    expect(SystemSetting::query()->count())->toBe(0);
});
