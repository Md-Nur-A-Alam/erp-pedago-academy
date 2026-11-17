<?php

namespace App\Http\Controllers;

use App\Models\InstallmentSchedule;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstallmentController extends Controller
{
    private const TABS = [
        'due_soon' => 'Due soon',
        'overdue' => 'Overdue',
        'upcoming' => 'Upcoming',
        'under_review' => 'Under review',
        'paid' => 'Paid',
    ];

    public function index(Request $request): View
    {
        $this->employeeWithRole($request, ['Admin', 'Accounts']);
        $filters = $request->validate(['tab' => ['nullable', Rule::in(array_keys(self::TABS))]]);
        $tab = $filters['tab'] ?? 'due_soon';
        $reminderDays = SystemSetting::integer('installment_reminder_days');

        $summary = [];
        foreach (array_keys(self::TABS) as $key) {
            $scoped = $this->scoped($key, $reminderDays);
            $summary[$key] = ['count' => (clone $scoped)->count(), 'amount' => (float) (clone $scoped)->sum('due_amount')];
        }

        $installments = $this->scoped($tab, $reminderDays)
            ->with([
                'enrollment:id,lead_id,course_id,sales_executive_id,student_name,student_phone,total_installments,status',
                'enrollment.course:id,title',
                'enrollment.salesExecutive:id,name',
            ])
            ->orderBy($tab === 'paid' ? 'paid_at' : 'due_date', $tab === 'paid' ? 'desc' : 'asc')
            ->paginate(25)
            ->withQueryString();

        return view('installments.index', [
            'tabs' => self::TABS,
            'tab' => $tab,
            'summary' => $summary,
            'installments' => $installments,
            'reminderDays' => $reminderDays,
        ]);
    }

    /**
     * Installments of live enrollments for one tab. Overdue is worked out from the due date, so it is
     * correct even when no scheduled job has updated the stored status.
     *
     * @return Builder<InstallmentSchedule>
     */
    public static function scoped(string $tab, int $reminderDays): Builder
    {
        $query = InstallmentSchedule::query()
            ->whereHas('enrollment', fn (Builder $enrollment) => $enrollment->where('status', '!=', 'Cancelled'));
        $today = today()->toDateString();
        $reminderEnd = today()->addDays($reminderDays)->toDateString();
        $unpaid = ['Pending', 'Overdue'];

        return match ($tab) {
            'overdue' => $query->whereIn('status', $unpaid)->whereDate('due_date', '<', $today),
            'upcoming' => $query->whereIn('status', $unpaid)->whereDate('due_date', '>', $reminderEnd),
            'under_review' => $query->where('status', 'Under Review'),
            'paid' => $query->where('status', 'Paid'),
            default => $query->whereIn('status', $unpaid)
                ->whereDate('due_date', '>=', $today)
                ->whereDate('due_date', '<=', $reminderEnd),
        };
    }
}
