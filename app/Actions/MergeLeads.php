<?php

namespace App\Actions;

use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\LeadBatchItem;
use App\Models\LeadEvent;
use App\Models\LeadFeedback;
use App\Models\LeadFollowup;
use App\Models\LeadInquiry;
use App\Models\LeadPhone;
use App\Models\LeadTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Two records for the same customer become one. Calls, follow-ups, transfers, inquiries and numbers all move
 * onto the lead that is kept, so no history is lost. The extra record is then removed.
 */
class MergeLeads
{
    public function __construct(private PhoneNumbers $phones) {}

    public function handle(Lead $keep, Lead $duplicate, Employee $by): Lead
    {
        if ($keep->id === $duplicate->id) {
            throw ValidationException::withMessages(['duplicate' => 'Choose two different leads.']);
        }

        if ($keep->enrollment()->exists() && $duplicate->enrollment()->exists()) {
            throw ValidationException::withMessages(['duplicate' => 'Both leads have an enrollment, so they cannot be merged. Cancel one enrollment first.']);
        }

        return DB::transaction(function () use ($keep, $duplicate, $by): Lead {
            // The numbers first, so a later call from any of them finds the kept lead.
            $numbers = array_filter([$duplicate->contact_number, $duplicate->whatsapp_number, $duplicate->alternative_contact_number]);
            $own = array_filter([$keep->contact_number, $keep->whatsapp_number, $keep->alternative_contact_number]);
            $moved = LeadPhone::query()->where('lead_id', $duplicate->id)->get();
            LeadPhone::query()->where('lead_id', $duplicate->id)->delete();

            foreach (array_merge($numbers, $moved->pluck('phone_number')->all()) as $number) {
                $normalized = $this->phones->normalize($number) ?? $number;

                if (! array_intersect($this->phones->variants($normalized), $own) && ! LeadPhone::query()->where('phone_number', $normalized)->exists()) {
                    LeadPhone::query()->create(['lead_id' => $keep->id, 'phone_number' => $normalized, 'label' => 'From merged lead L-'.$duplicate->id, 'added_by_employee_id' => $by->id]);
                }
            }

            foreach ([LeadFeedback::class, LeadFollowup::class, LeadBatchItem::class, LeadTransfer::class, LeadInquiry::class, LeadEvent::class, Enrollment::class] as $model) {
                if ($model === Enrollment::class && $keep->enrollment()->exists()) {
                    continue;
                }
                $model::query()->where('lead_id', $duplicate->id)->update(['lead_id' => $keep->id]);
            }
            DuplicateLeadReview::query()->where('matched_lead_id', $duplicate->id)->update(['matched_lead_id' => $keep->id]);

            // Keep the better of the two states: an owner and progress are not thrown away.
            $fill = [];
            foreach (['current_owner_id', 'team_id', 'assigned_at', 'guardian_name', 'email', 'location', 'age', 'interested_course_id', 'priority', 'whatsapp_number', 'alternative_contact_number'] as $field) {
                if (blank($keep->{$field}) && filled($duplicate->{$field})) {
                    $fill[$field] = $duplicate->{$field};
                }
            }
            if ($keep->current_owner_id === null && $duplicate->current_owner_id !== null) {
                $fill['status'] = $duplicate->status;
            }
            if (filled($duplicate->remarks) && ! str_contains((string) $keep->remarks, (string) $duplicate->remarks)) {
                $fill['remarks'] = trim($keep->remarks.' | '.$duplicate->remarks, ' |');
            }
            $keep->forceFill($fill)->save();

            $name = $duplicate->student_name;
            $duplicateId = $duplicate->id;
            $duplicate->delete();

            LeadEvent::record($keep, $by, 'Merged', "{$by->name} merged L-{$duplicateId} ({$name}) into this lead.");

            return $keep;
        });
    }
}
