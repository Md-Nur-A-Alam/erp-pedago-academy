<?php

namespace App\Http\Controllers;

use App\Actions\RecordLeadFeedback;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadBatchItem;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesWorkspaceController extends Controller
{
    private const CLOSED_STATUSES = ['Enrolled - Pending Payment', 'Payment Under Review', 'Converted'];

    public function tasks(Request $request): View
    {
        $executive = $this->employeeWithRole($request, ['Sales Executive']);

        $items = LeadBatchItem::query()
            ->whereHas('batch', fn ($batch) => $batch->where('employee_id', $executive->id)->where('status', 'Active'))
            ->with(['lead.source:source_id,channel,campaign_name', 'lead.interestedCourse:id,title'])
            ->orderBy('is_completed')
            ->orderBy('id')
            ->get();

        $followUps = LeadFollowup::query()
            ->where('employee_id', $executive->id)
            ->where('is_done', false)
            ->where('followup_date_time', '<=', now()->endOfDay())
            ->with('lead:id,student_name,contact_number,status')
            ->orderBy('followup_date_time')
            ->get();

        $callNext = collect();
        foreach ($followUps as $followUp) {
            $due = $followUp->followup_date_time->isPast();
            $callNext->push([
                'lead_id' => $followUp->lead_id,
                'lead' => $followUp->lead,
                'rank' => $due ? ($followUp->lead?->status === 'Interested' ? 1 : 2) : 20,
                'order' => $followUp->followup_date_time->getTimestamp(),
                'label' => $due ? 'Promised call-back' : 'Later today',
                'tone' => $due ? 'red' : 'slate',
                'detail' => $due
                    ? 'Overdue by '.$followUp->followup_date_time->diffForHumans(null, true)
                    : 'Due at '.$followUp->followup_date_time->format('g:i A'),
            ]);
        }
        foreach ($items->where('is_completed', false) as $item) {
            if ($item->lead === null || $callNext->contains('lead_id', $item->lead_id)) {
                continue;
            }
            $temperature = $item->lead->temperature();
            $callNext->push([
                'lead_id' => $item->lead_id,
                'lead' => $item->lead,
                'rank' => $temperature === 'Hot' ? 0 : 3 + $item->lead->priorityRank(),
                'order' => -$item->lead->created_at->getTimestamp(),
                'label' => $temperature.' lead',
                'tone' => ['Hot' => 'red', 'Warm' => 'amber', 'Cool' => 'blue', 'Cold' => 'slate'][$temperature],
                'detail' => 'Received '.$item->lead->created_at->diffForHumans(),
            ]);
        }

        return view('sales.tasks', [
            'executive' => $executive,
            'callNext' => $callNext->sortBy(fn (array $entry): array => [$entry['rank'], $entry['order']])->values(),
            'items' => $items,
            'followUps' => $followUps,
            'awaitingPayment' => $executive->ownedLeads()
                ->whereIn('status', ['Enrolled - Pending Payment', 'Payment Under Review'])
                ->orderByDesc('last_action_at')
                ->get(['id', 'student_name', 'contact_number', 'status']),
            'availability' => $executive->effectiveAvailabilityStatus(),
        ]);
    }

    public function show(Request $request, Lead $lead): View
    {
        $viewer = $this->employeeWithRole($request, ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive']);
        abort_unless($viewer->visibleLeads()->whereKey($lead->id)->exists(), 403);

        $lead->load([
            'source', 'interestedCourse', 'currentOwner:id,name,office_id', 'team:id,name',
            'feedbacks' => fn ($feedbacks) => $feedbacks->with('employee:id,name')->latest('id'),
            'followups' => fn ($followUps) => $followUps->with('employee:id,name')->latest('followup_date_time'),
            'transfers' => fn ($transfers) => $transfers->with(['fromEmployee:id,name', 'toEmployee:id,name', 'toTeam:id,name'])->latest('id'),
        ]);

        $canWork = $viewer->role === 'Sales Executive' && $lead->current_owner_id === $viewer->id;

        return view('sales.lead', [
            'lead' => $lead,
            'canWork' => $canWork,
            'isClosed' => in_array($lead->status, self::CLOSED_STATUSES, true),
            'pendingTransfer' => $lead->transfers->firstWhere('status', 'Pending'),
            'callStatuses' => RecordLeadFeedback::CALL_STATUSES,
            'interests' => RecordLeadFeedback::INTERESTS,
            'nextActions' => array_keys(RecordLeadFeedback::NEXT_ACTIONS),
            'colleagues' => $canWork
                ? Employee::query()->where('role', 'Sales Executive')->where('status', 'Active')
                    ->where('id', '!=', $viewer->id)->whereNotNull('team_id')->with('team:id,name')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function feedback(Request $request, Lead $lead, RecordLeadFeedback $record): RedirectResponse
    {
        $executive = $this->ownerOf($request, $lead);
        abort_if(in_array($lead->status, self::CLOSED_STATUSES, true), 422, 'This lead is already in enrollment, so feedback is closed.');

        $validated = $request->validate([
            'call_status' => ['required', Rule::in(RecordLeadFeedback::CALL_STATUSES)],
            'customer_interest' => ['required', Rule::in(RecordLeadFeedback::INTERESTS)],
            'customer_concern' => ['nullable', 'string', 'max:1000'],
            'sales_note' => ['required', 'string', 'max:2000'],
            'next_action' => ['required', Rule::in(array_keys(RecordLeadFeedback::NEXT_ACTIONS))],
            'followup_date_time' => ['nullable', 'required_if:next_action,'.implode(',', RecordLeadFeedback::NEEDS_FOLLOWUP), 'date', 'after:now'],
        ], [
            'followup_date_time.required_if' => 'Choose when you will call this lead again.',
            'followup_date_time.after' => 'The call-back time must be in the future.',
            'sales_note.required' => 'Write a short note about the call.',
        ]);

        $record->handle($lead, $executive, $validated);

        return redirect()->route('sales.tasks')->with('status', "Feedback saved for {$lead->student_name}. Status is now ".RecordLeadFeedback::NEXT_ACTIONS[$validated['next_action']].'.');
    }

    public function requestTransfer(Request $request, Lead $lead): RedirectResponse
    {
        $executive = $this->ownerOf($request, $lead);
        $validated = $request->validate([
            'to_employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('role', 'Sales Executive')->where('status', 'Active'), Rule::notIn([$executive->id])],
            'reason' => ['required', 'string', 'max:500'],
        ], ['to_employee_id.required' => 'Choose the executive who should take this lead.', 'reason.required' => 'Explain why this lead should move.']);

        $target = Employee::query()->findOrFail($validated['to_employee_id']);
        $fromTeamId = $lead->team_id ?? $executive->team_id;

        if ($fromTeamId === null || $target->team_id === null) {
            throw ValidationException::withMessages(['to_employee_id' => 'Both executives must belong to a team before a lead can be transferred.']);
        }
        if (LeadTransfer::query()->where('lead_id', $lead->id)->where('status', 'Pending')->exists()) {
            throw ValidationException::withMessages(['reason' => 'A transfer for this lead is already waiting for approval.']);
        }

        LeadTransfer::query()->create([
            'lead_id' => $lead->id,
            'from_employee_id' => $executive->id,
            'to_employee_id' => $target->id,
            'from_team_id' => $fromTeamId,
            'to_team_id' => $target->team_id,
            'reason' => $validated['reason'],
            'status' => 'Pending',
        ]);

        return back()->with('status', "Transfer to {$target->name} requested. Team leaders will approve it, or it is approved automatically after the waiting period.");
    }

    private function ownerOf(Request $request, Lead $lead): Employee
    {
        $executive = $this->employeeWithRole($request, ['Sales Executive']);
        abort_unless($lead->current_owner_id === $executive->id, 403);

        return $executive;
    }
}
