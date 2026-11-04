<?php

use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDuplicateReviewEmployee(string $role): array
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

    return [$user, $employee];
}

it('merges incoming details into blank fields without replacing existing lead data', function () {
    [$admin, $adminEmployee] = createDuplicateReviewEmployee('Admin');
    [, $owner] = createDuplicateReviewEmployee('Sales Executive');
    $matchedLead = Lead::factory()->create([
        'student_name' => 'Existing Student',
        'guardian_name' => null,
        'location' => 'Dhaka',
        'status' => 'Contacted',
        'current_owner_id' => $owner->id,
    ]);
    $review = DuplicateLeadReview::factory()->create([
        'matched_lead_id' => $matchedLead->id,
        'incoming_lead_data' => [
            'source_id' => $matchedLead->source_id,
            'student_name' => 'Incoming Student',
            'contact_number' => $matchedLead->contact_number,
            'guardian_name' => 'Incoming Guardian',
            'location' => 'Chattogram',
        ],
    ]);

    $this->actingAs($admin)->put(route('duplicate-lead-reviews.update', $review), [
        'decision' => 'merge',
    ])->assertRedirect(route('duplicate-lead-reviews.index'));

    expect($review->fresh()->status)->toBe('Merged');
    expect($review->fresh()->reviewed_by_employee_id)->toBe($adminEmployee->id);
    expect($matchedLead->fresh()->guardian_name)->toBe('Incoming Guardian');
    expect($matchedLead->fresh()->student_name)->toBe('Existing Student');
    expect($matchedLead->fresh()->location)->toBe('Dhaka');
    expect($matchedLead->fresh()->status)->toBe('Contacted');
    expect($matchedLead->fresh()->current_owner_id)->toBe($owner->id);
});

it('creates an approved incoming record as a new lead with safe default ownership and status', function () {
    [$admin, $adminEmployee] = createDuplicateReviewEmployee('Admin');
    $matchedLead = Lead::factory()->create();
    $review = DuplicateLeadReview::factory()->create([
        'matched_lead_id' => $matchedLead->id,
        'incoming_lead_data' => [
            'source_id' => $matchedLead->source_id,
            'student_name' => 'Approved New Student',
            'contact_number' => '+8801712345678',
            'guardian_name' => 'Parent',
            'current_owner_id' => $matchedLead->current_owner_id,
            'status' => 'Converted',
        ],
    ]);

    $this->actingAs($admin)->put(route('duplicate-lead-reviews.update', $review), [
        'decision' => 'approve_new',
    ])->assertRedirect(route('duplicate-lead-reviews.index'));

    $newLead = Lead::query()->where('student_name', 'Approved New Student')->sole();
    expect($newLead->status)->toBe('New');
    expect($newLead->current_owner_id)->toBeNull();
    expect($newLead->source_id)->toBe($matchedLead->source_id);
    expect($review->fresh()->status)->toBe('Approved New');
    expect($review->fresh()->reviewed_by_employee_id)->toBe($adminEmployee->id);
});

it('rejects duplicate reviews with an invalid international phone number', function () {
    [$admin] = createDuplicateReviewEmployee('Admin');
    $matchedLead = Lead::factory()->create();
    $review = DuplicateLeadReview::factory()->create([
        'matched_lead_id' => $matchedLead->id,
        'incoming_lead_data' => [
            'source_id' => $matchedLead->source_id,
            'student_name' => 'Invalid Incoming Student',
            'contact_number' => '+1234567890123456',
        ],
    ]);

    $this->actingAs($admin)->put(route('duplicate-lead-reviews.update', $review), [
        'decision' => 'approve_new',
    ])->assertSessionHasErrors('contact_number');

    expect(Lead::query()->count())->toBe(1);
    expect($review->fresh()->status)->toBe('Pending Review');
});

it('discards an incoming duplicate without creating another lead', function () {
    [$admin, $adminEmployee] = createDuplicateReviewEmployee('Admin');
    $matchedLead = Lead::factory()->create();
    $review = DuplicateLeadReview::factory()->create(['matched_lead_id' => $matchedLead->id]);

    $this->actingAs($admin)->put(route('duplicate-lead-reviews.update', $review), [
        'decision' => 'discard',
    ])->assertRedirect(route('duplicate-lead-reviews.index'));

    expect($review->fresh()->status)->toBe('Discarded');
    expect($review->fresh()->reviewed_by_employee_id)->toBe($adminEmployee->id);
    expect(Lead::query()->count())->toBe(1);
});

it('allows administrators to search duplicate reviews and forbids marketing executives', function () {
    [$admin] = createDuplicateReviewEmployee('Admin');
    [$marketingExecutive] = createDuplicateReviewEmployee('MarketingExecutive');
    $matchedLead = Lead::factory()->create();
    $review = DuplicateLeadReview::factory()->create([
        'matched_lead_id' => $matchedLead->id,
        'incoming_lead_data' => [
            'student_name' => 'Searchable Incoming',
            'contact_number' => $matchedLead->contact_number,
        ],
    ]);

    $this->actingAs($admin)->get(route('duplicate-lead-reviews.index', ['search' => 'Searchable Incoming']))
        ->assertOk()
        ->assertSee('Searchable Incoming');

    $this->actingAs($marketingExecutive)->get(route('duplicate-lead-reviews.index'))->assertForbidden();
    $this->actingAs($marketingExecutive)->put(route('duplicate-lead-reviews.update', $review), [
        'decision' => 'discard',
    ])->assertForbidden();
});

it('does not process a duplicate review more than once', function () {
    [$admin] = createDuplicateReviewEmployee('Admin');
    $review = DuplicateLeadReview::factory()->create(['status' => 'Discarded']);

    $this->actingAs($admin)->put(route('duplicate-lead-reviews.update', $review), [
        'decision' => 'approve_new',
    ])->assertSessionHasErrors('review');

    expect(Lead::query()->count())->toBe(1);
});
