<?php

namespace App\Http\Controllers;

use App\Actions\DecideLeadTransfer;
use App\Models\Employee;
use App\Models\LeadTransfer;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadTransferController extends Controller
{
    private const STATUSES = ['Pending', 'Approved', 'Rejected'];

    public function index(Request $request): View
    {
        $viewer = $this->employeeWithRole($request, ['Admin', 'Team Leader']);
        $filters = $request->validate(['status' => ['nullable', Rule::in(self::STATUSES)]]);
        $status = $filters['status'] ?? 'Pending';

        $transfers = $this->involving($viewer)
            ->where('status', $status)
            ->with([
                'lead:id,student_name,contact_number,status',
                'fromEmployee:id,name,office_id',
                'toEmployee:id,name,office_id',
                'fromTeam:id,name',
                'toTeam:id,name',
                'senderTeamLeader:id,name',
                'receiverTeamLeader:id,name',
                'decidedBy:id,name',
            ])
            ->orderBy('created_at', $status === 'Pending' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('lead-transfers.index', [
            'statuses' => self::STATUSES,
            'status' => $status,
            'transfers' => $transfers,
            'viewer' => $viewer,
            'counts' => $this->involving($viewer)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'autoApproveDays' => SystemSetting::integer('transfer_auto_approve_days'),
        ]);
    }

    public function update(Request $request, LeadTransfer $leadTransfer, DecideLeadTransfer $decide): RedirectResponse
    {
        $admin = $this->employeeWithRole($request, ['Admin', 'Team Leader']);
        abort_unless($this->involving($admin)->whereKey($leadTransfer->id)->exists(), 403);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'decision_note' => ['nullable', 'required_if:decision,reject', 'string', 'max:500'],
        ], [
            'decision_note.required_if' => 'Give the reason for rejecting this transfer.',
        ]);

        if ($validated['decision'] === 'approve' && ! $admin->isAdmin()) {
            $result = $decide->approveAsLeader($leadTransfer, $admin);

            return back()->with('status', $result->status === 'Approved'
                ? "Transfer approved. Lead L-{$leadTransfer->lead_id} moved to its new owner."
                : 'Your approval is recorded. The transfer completes when the other team leader approves.');
        }

        if ($validated['decision'] === 'approve') {
            $decide->approve($leadTransfer, $admin, $validated['decision_note'] ?? null);

            return back()->with('status', "Transfer approved. Lead L-{$leadTransfer->lead_id} moved to its new owner.");
        }

        $decide->reject($leadTransfer, $admin, $validated['decision_note']);

        return back()->with('status', "Transfer rejected. Lead L-{$leadTransfer->lead_id} stays with its current owner.");
    }

    /**
     * Admins see every transfer; team leaders see those that involve a team they lead.
     *
     * @return Builder<LeadTransfer>
     */
    private function involving(Employee $viewer): Builder
    {
        return LeadTransfer::query()->when(! $viewer->isAdmin(), fn (Builder $query) => $query->where(
            fn (Builder $scope) => $scope
                ->whereHas('fromTeam', fn (Builder $team) => $team->where('team_leader_id', $viewer->id))
                ->orWhereHas('toTeam', fn (Builder $team) => $team->where('team_leader_id', $viewer->id)),
        ));
    }
}
