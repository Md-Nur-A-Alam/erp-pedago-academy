<?php

use App\Models\Course;
use App\Models\Employee;
use App\Models\EmployeeRoster;
use App\Models\Lead;
use App\Models\LeadFeedback;
use App\Models\LeadSource;
use App\Models\LeadTransfer;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SalesLeadManagementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('stores user passwords as one-way hashes', function () {
    $user = User::factory()->create([
        'password' => 'temporary-secret',
    ]);

    expect(Hash::check('temporary-secret', $user->password))->toBeTrue();
    expect($user->toArray())->not->toHaveKey('password');
});

it('connects each employee to a separate login account and profile', function () {
    $employee = Employee::factory()->create();
    $details = $employee->details()->create([
        'dob' => '1990-01-01',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_number' => '+8801700000000',
        'blood_group' => 'A+',
        'present_address' => 'Present address',
        'permanent_address' => 'Permanent address',
    ]);

    expect($employee->user)->toBeInstanceOf(User::class);
    expect($employee->isProfileComplete())->toBeTrue();
    expect($details->employee->is($employee))->toBeTrue();
});

it('places MarketingExecutive at the operational employee approval level', function () {
    $marketingEmployee = Employee::factory()->create([
        'role' => 'MarketingExecutive',
    ]);
    $teamLeader = Employee::factory()->create([
        'role' => 'Team Leader',
    ]);
    $salesExecutive = Employee::factory()->create([
        'role' => 'Sales Executive',
    ]);

    expect($marketingEmployee->canBeReviewedBy($teamLeader))->toBeTrue();
    expect($marketingEmployee->canBeReviewedBy($salesExecutive))->toBeFalse();
});

it('resolves an employee default shift', function () {
    $shift = Shift::create([
        'shift_start_time' => '10:00:00',
        'shift_end_time' => '18:00:00',
        'working_hours' => 8,
    ]);

    $employee = Employee::factory()->create([
        'shift_id' => $shift->id,
    ]);

    expect($employee->defaultShift->is($shift))->toBeTrue();
});

it('defaults new dated roster entries to off duty', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::create([
        'shift_start_time' => '10:00:00',
        'shift_end_time' => '18:00:00',
        'working_hours' => 8,
    ]);

    $roster = EmployeeRoster::create([
        'employee_id' => $employee->id,
        'shift_id' => $shift->id,
        'roster_date' => '2026-11-03',
    ])->refresh();

    expect($roster->availability_status)->toBe('Off Duty');
    expect($roster->is_on_duty)->toBeFalse();
});

it('creates employee rosters through model factories', function () {
    $roster = EmployeeRoster::factory()->create();

    expect($roster->employee)->toBeInstanceOf(Employee::class);
    expect($roster->shift)->toBeInstanceOf(Shift::class);
});

it('resolves a lead source and owner for merged statuses', function (string $status) {
    $employee = Employee::factory()->create();
    $source = LeadSource::create([
        'channel' => 'website',
    ]);

    $lead = Lead::create([
        'source_id' => $source->source_id,
        'student_name' => 'Sample Student',
        'contact_number' => '+8801700000000',
        'status' => $status,
        'current_owner_id' => $employee->id,
    ]);

    expect($lead->source->is($source))->toBeTrue();
    expect($lead->currentOwner->is($employee))->toBeTrue();
})->with([
    'interested status' => 'Interested',
    'unreachable status' => 'Unreachable',
]);

it('stores optional lead guardian and WhatsApp contact details', function () {
    $lead = Lead::factory()->create([
        'guardian_name' => 'Samira Rahman',
        'whatsapp_number' => '+8801712345678',
    ]);

    $leadWithoutGuardianContacts = Lead::factory()->create([
        'guardian_name' => null,
        'whatsapp_number' => null,
    ]);

    expect($lead->fresh()->guardian_name)->toBe('Samira Rahman');
    expect($lead->fresh()->whatsapp_number)->toBe('+8801712345678');
    expect($leadWithoutGuardianContacts->fresh()->guardian_name)->toBeNull();
    expect($leadWithoutGuardianContacts->fresh()->whatsapp_number)->toBeNull();
});

it('stores lead action and payment verification dates as datetimes', function () {
    $actionDate = '2026-11-02 09:15:00';
    $paymentDate = '2026-11-03 14:30:00';
    $verifiedDate = '2026-11-04 10:45:00';

    $feedback = LeadFeedback::factory()->create([
        'action_date' => $actionDate,
    ]);
    $payment = Payment::factory()->create([
        'payment_date' => $paymentDate,
        'verified_date' => $verifiedDate,
    ]);

    expect($feedback->fresh()->action_date->format('Y-m-d H:i:s'))->toBe($actionDate);
    expect($payment->fresh()->payment_date->format('Y-m-d H:i:s'))->toBe($paymentDate);
    expect($payment->fresh()->verified_date->format('Y-m-d H:i:s'))->toBe($verifiedDate);
});

it('creates transfer and payment relationship graphs through factories', function () {
    $transfer = LeadTransfer::factory()->create();
    $payment = Payment::factory()->create();

    expect($transfer->lead)->toBeInstanceOf(Lead::class);
    expect($payment->enrollment->course)->toBeInstanceOf(Course::class);
});

it('seeds the configured lead batch and cutoff defaults', function () {
    $this->seed(SalesLeadManagementSeeder::class);

    expect(SystemSetting::query()->where('key', 'lead_batch_size')->value('value'))->toBe('3');
    expect(SystemSetting::query()->where('key', 'assignment_cutoff_minutes')->value('value'))->toBe('30');
});
