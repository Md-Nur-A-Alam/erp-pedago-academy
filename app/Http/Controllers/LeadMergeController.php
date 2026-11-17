<?php

namespace App\Http\Controllers;

use App\Actions\MergeLeads;
use App\Actions\PhoneNumbers;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadMergeController extends Controller
{
    public function index(Request $request, PhoneNumbers $phones): View
    {
        $this->employeeWithRole($request);

        $groups = Lead::query()
            ->with('currentOwner:id,name')
            ->orderBy('id')
            ->get(['id', 'student_name', 'contact_number', 'status', 'current_owner_id', 'created_at'])
            ->groupBy(fn (Lead $lead): string => $phones->normalize($lead->contact_number) ?? trim((string) $lead->contact_number))
            ->filter(fn ($leads): bool => $leads->count() > 1)
            ->values();

        return view('leads.merge', ['groups' => $groups]);
    }

    public function store(Request $request, MergeLeads $merge): RedirectResponse
    {
        $admin = $this->employeeWithRole($request);
        $validated = $request->validate([
            'keep_id' => ['required', 'integer', 'exists:leads,id'],
            'duplicate_id' => ['required', 'integer', 'different:keep_id', 'exists:leads,id'],
        ]);

        $kept = $merge->handle(Lead::query()->findOrFail($validated['keep_id']), Lead::query()->findOrFail($validated['duplicate_id']), $admin);

        return redirect()->route('leads.merge')->with('status', "Merged into L-{$kept->id}. Its calls, follow-ups and numbers are all there.");
    }
}
