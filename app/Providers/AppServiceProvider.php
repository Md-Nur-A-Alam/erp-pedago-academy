<?php

namespace App\Providers;

use App\Actions\DistributeLeads;
use App\Actions\StaffPasswordCipher;
use App\Actions\StaffPasswordHasher;
use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\LeadBatchItem;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use App\Models\Payment;
use App\Models\SystemSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $phoneNumber = Str::of($request->input('phone_number', ''))
                ->lower()
                ->trim()
                ->toString();

            return Limit::perMinute(5)->by($phoneNumber.'|'.$request->ip());
        });

        if (filled(config('app.staff_password_key'))) {
            Hash::extend('pedago', fn (): StaffPasswordHasher => new StaffPasswordHasher(
                new StaffPasswordCipher((string) config('app.staff_password_key')),
            ));
            config(['hashing.driver' => 'pedago']);
        }

        ViewFactory::composer('layouts.app', function (View $view): void {
            $employee = auth()->user()?->employee;

            $view->with('navCounts', $employee instanceof Employee ? $this->navigationCounts($employee) : []);
            $view->with('engineEnabled', $employee instanceof Employee && $employee->isAdmin()
                ? SystemSetting::enabled('distribution_engine_enabled')
                : null);
        });
    }

    /**
     * Counts shown as badges beside the sidebar links the employee is allowed to open.
     *
     * @return array<string, int>
     */
    private function navigationCounts(Employee $employee): array
    {
        $counts = [];

        if ($employee->isAdmin()) {
            $counts['queue'] = app(DistributeLeads::class)->queueQuery()->count();
            $counts['duplicates'] = DuplicateLeadReview::query()->where('status', 'Pending Review')->count();
            $counts['transfers'] = LeadTransfer::query()->where('status', 'Pending')->count();
        }

        if ($employee->role === 'Team Leader') {
            $counts['transfers'] = LeadTransfer::query()->where('status', 'Pending')->where(
                fn ($scope) => $scope
                    ->whereHas('fromTeam', fn ($team) => $team->where('team_leader_id', $employee->id))
                    ->orWhereHas('toTeam', fn ($team) => $team->where('team_leader_id', $employee->id)),
            )->count();
        }

        if (in_array($employee->role, ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive'], true)) {
            $reach = $employee->managedExecutiveIds();
            $counts['overdue'] = LeadFollowup::query()
                ->when($reach !== null, fn ($followUps) => $followUps->whereIn('employee_id', $reach))
                ->where('is_done', false)
                ->where('followup_date_time', '<', now())
                ->count();
        }

        if ($employee->role === 'Sales Executive') {
            $counts['tasks'] = LeadBatchItem::query()
                ->where('is_completed', false)
                ->whereHas('batch', fn ($batch) => $batch->where('employee_id', $employee->id)->where('status', 'Active'))
                ->count();
        }

        if (in_array($employee->role, ['Admin', 'Accounts'], true)) {
            $counts['payments'] = Payment::query()->where('status', 'Pending')->count();
        }

        $reviewableRoles = $employee->lowerAuthorityRoles();
        if ($reviewableRoles !== []) {
            $counts['approvals'] = EmployeeChangeRequest::query()
                ->where('status', 'Pending')
                ->whereHas('employee', fn ($query) => $query->whereIn('role', $reviewableRoles))
                ->count();
        }

        return $counts;
    }
}
