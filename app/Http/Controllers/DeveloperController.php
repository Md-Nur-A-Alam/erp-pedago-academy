<?php

namespace App\Http\Controllers;

use App\Actions\DecideLeadTransfer;
use App\Actions\DistributeLeads;
use App\Actions\StaffPasswordHasher;
use App\Models\DeveloperActivityLog;
use App\Models\Employee;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    /**
     * Session key holding the developer's own user id while they view the system as someone else.
     */
    public const SESSION_KEY = 'developer.impersonator_id';

    private const TABLES = [
        'employees', 'teams', 'leads', 'leads_source', 'courses', 'lead_batches', 'lead_batch_items',
        'lead_feedbacks', 'lead_followups', 'lead_transfers', 'duplicate_lead_reviews', 'enrollments',
        'installment_schedules', 'payments',
    ];

    private const TOOLS = [
        'distribute' => 'Run one distribution cycle',
        'auto-approve' => 'Approve overdue transfers',
        'clear-views' => 'Clear compiled views',
    ];

    public function index(Request $request): View
    {
        $developer = $this->developer($request);
        $search = trim((string) $request->query('search', ''));

        $employees = Employee::query()
            ->where('role', '!=', 'Developer')
            ->whereHas('user')
            ->with(['user:id,phone_number,must_change_password', 'details', 'team:id,name', 'teamsLed:id,name,team_leader_id'])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('office_id', 'like', "%{$search}%")))
            ->orderByRaw("CASE role WHEN 'Admin' THEN 1 WHEN 'Supervisor' THEN 2 WHEN 'Team Leader' THEN 3 WHEN 'Sales Executive' THEN 4 WHEN 'MarketingExecutive' THEN 5 ELSE 6 END")
            ->orderBy('name')
            ->get();

        return view('developer.index', [
            'developer' => $developer,
            'search' => $search,
            'byRole' => $employees->groupBy('role'),
            'environment' => $this->environment(),
            'schedule' => collect(app(Schedule::class)->events())->map(fn ($event): array => [
                'command' => trim(preg_replace('/^.*artisan["\']?\s*/', '', (string) $event->command)),
                'expression' => $event->expression,
                'next' => $event->nextRunDate()->format('M j, g:i A'),
            ]),
            'counts' => collect(self::TABLES)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]),
            'tools' => self::TOOLS,
            'logs' => DeveloperActivityLog::query()->with('target:id,name,role')->latest('id')->limit(12)->get(),
        ]);
    }

    public function guide(Request $request): View
    {
        $this->developer($request);

        return view('developer.guide', [
            'settings' => SystemSetting::DEFINITIONS,
            'schedule' => collect(app(Schedule::class)->events())->map(fn ($event): string => trim(preg_replace('/^.*artisan["\']?\s*/', '', (string) $event->command)).' · '.$event->expression),
        ]);
    }

    /**
     * Sign in as another employee without their password, keeping the way back to the developer account.
     */
    public function viewAs(Request $request, Employee $employee): RedirectResponse
    {
        $developer = $this->developer($request);

        abort_if($employee->role === 'Developer', 403, 'You cannot view the system as another developer.');
        abort_if($employee->status !== 'Active', 422, 'Only active employees can be opened.');
        abort_if($employee->user === null, 422, 'This employee has no login account.');

        if (! $request->session()->has(self::SESSION_KEY)) {
            $request->session()->put(self::SESSION_KEY, $developer->user_id);
        }

        Auth::login($employee->user);
        $this->log($request, $developer, 'Viewed as', $employee, $employee->role);

        return redirect()->route('dashboard')->with('status', "You are now viewing the system as {$employee->name} ({$employee->role}).");
    }

    public function return(Request $request): RedirectResponse
    {
        $developerUserId = $request->session()->pull(self::SESSION_KEY);
        abort_if($developerUserId === null, 403);

        $developerUser = User::query()->findOrFail($developerUserId);
        abort_unless($developerUser->employee?->role === 'Developer', 403);

        Auth::login($developerUser);

        return redirect()->route('developer.index')->with('status', 'You are back in your developer account.');
    }

    public function tool(Request $request, string $tool): RedirectResponse
    {
        $developer = $this->developer($request);
        abort_unless(array_key_exists($tool, self::TOOLS), 404);

        $message = match ($tool) {
            'distribute' => (function (): string {
                $result = app(DistributeLeads::class)->run();

                return "Distribution cycle finished: {$result['leads']} leads in {$result['batches']} batches.";
            })(),
            'auto-approve' => 'Approved '.app(DecideLeadTransfer::class)->approveOverdue().' transfers that passed the waiting period.',
            'clear-views' => (function (): string {
                Artisan::call('view:clear');

                return 'Compiled views cleared.';
            })(),
        };

        $this->log($request, $developer, self::TOOLS[$tool], null, $message);

        return back()->with('status', $message);
    }

    /**
     * The developer acting now: the signed-in developer, or the developer behind the current impersonation.
     */
    private function developer(Request $request): Employee
    {
        abort_unless((bool) config('app.developer_panel'), 404);

        $userId = $request->session()->get(self::SESSION_KEY) ?? $request->user()?->id;
        $employee = $userId === null ? null : User::query()->find($userId)?->employee;

        abort_unless($employee instanceof Employee && $employee->role === 'Developer' && $employee->status === 'Active', 403);

        return $employee;
    }

    private function log(Request $request, Employee $developer, string $action, ?Employee $target, ?string $details): void
    {
        DeveloperActivityLog::query()->create([
            'developer_employee_id' => $developer->id,
            'action' => $action,
            'target_employee_id' => $target?->id,
            'details' => $details === null ? null : mb_substr($details, 0, 255),
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        $connection = DB::connection();

        return [
            'Environment' => (string) app()->environment(),
            'Debug mode' => config('app.debug') ? 'On' : 'Off',
            'URL' => (string) config('app.url'),
            'Time zone' => (string) config('app.timezone').' · now '.now()->format('M j, g:i A'),
            'PHP' => PHP_VERSION,
            'Laravel' => Application::VERSION,
            'Database' => $connection->getDriverName().' · '.$connection->getDatabaseName(),
            'Queue' => (string) config('queue.default'),
            'Cache' => (string) config('cache.default'),
            'Session' => (string) config('session.driver'),
            'Password storage' => Hash::driver() instanceof StaffPasswordHasher ? 'Readable (staff key set)' : 'One-way hash (no staff key)',
            'Distribution engine' => SystemSetting::enabled('distribution_engine_enabled') ? 'On' : 'Off',
        ];
    }

    /**
     * Employees a developer can switch to, grouped by role, for the switcher in the top banner.
     *
     * @return Collection<string, Collection<int, Employee>>
     */
    public static function switchTargets(): Collection
    {
        return Employee::query()
            ->where('role', '!=', 'Developer')
            ->where('status', 'Active')
            ->whereHas('user')
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->groupBy('role')
            ->map(fn ($group) => $group->take(15));
    }
}
