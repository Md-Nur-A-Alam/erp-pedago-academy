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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    /**
     * Session key holding the developer's own user id while they view the system as someone else.
     */
    public const SESSION_KEY = 'developer.impersonator_id';

    /**
     * Module of each table, in the order they are drawn.
     *
     * @var array<string, list<string>>
     */
    private const MODULES = [
        'People' => ['users', 'employees', 'employee_details', 'employee_change_requests', 'employee_rosters', 'shifts', 'teams', 'supervisor_teams', 'leave_requests'],
        'Catalogue' => ['subjects', 'course_topics', 'courses', 'course_batch', 'leads_source'],
        'Leads and distribution' => ['leads', 'lead_batches', 'lead_batch_items', 'lead_feedbacks', 'lead_followups', 'lead_transfers', 'duplicate_lead_reviews', 'lead_phones', 'lead_inquiries', 'lead_events'],
        'Enrollment and finance' => ['enrollments', 'installment_schedules', 'payments'],
        'System' => ['system_settings', 'password_reveal_logs', 'developer_activity_logs'],
        'Framework' => ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'migrations'],
    ];

    /**
     * What each table is for, in plain words.
     *
     * @var array<string, string>
     */
    private const PURPOSES = [
        'users' => 'Sign-in accounts: phone number, password and the must-change flag.',
        'employees' => 'One row per employee: role, status, office ID, team and default shift.',
        'employee_details' => 'Personal and emergency details that complete an employee profile.',
        'employee_change_requests' => 'Requests to change essential employee fields, with review outcome.',
        'employee_rosters' => 'Per-date duty overrides: shift, on duty or off, and a manual status.',
        'shifts' => 'Working hours that teams, employees and rosters point to.',
        'teams' => 'Sales teams with a leader and a shift.',
        'supervisor_teams' => 'Which supervisors oversee which teams.',
        'leave_requests' => 'Leave applications and their decisions.',
        'subjects' => 'Top level of the course catalogue.',
        'course_topics' => 'Topics inside a subject.',
        'courses' => 'Courses that can be sold, with base fee and topic.',
        'course_batch' => 'Intakes of a course with capacity and start date.',
        'leads_source' => 'Campaigns: channel, course and Auto or Manual distribution.',
        'leads' => 'Every inquiry: contact details, status, owner and team.',
        'lead_batches' => 'The set of leads an executive holds at one time.',
        'lead_batch_items' => 'Leads inside a batch, with the action that closed each slot.',
        'lead_feedbacks' => 'Call logs: result, interest, note and next action.',
        'lead_followups' => 'Promised call-backs and whether they were done.',
        'lead_transfers' => 'Requests to move a lead between teams, with approvals.',
        'duplicate_lead_reviews' => 'Leads held because the phone number already exists.',
        'lead_phones' => 'Extra numbers of a lead, such as a parent phone. Any of them finds the lead.',
        'lead_inquiries' => 'Every time a customer asked: the first inquiry and each repeat, with the outcome.',
        'lead_events' => 'Lead history that is not a call: assigned, transferred, number added, WhatsApp, merged.',
        'enrollments' => 'A lead that became a student: course, batch, fee and plan.',
        'installment_schedules' => 'The payment schedule of an enrollment.',
        'payments' => 'Payments submitted and verified by Accounts.',
        'system_settings' => 'Every setting an administrator can change.',
        'password_reveal_logs' => 'Who showed or reset a staff password, and why.',
        'developer_activity_logs' => 'Panel switches and tool runs by developers.',
        'sessions' => 'Browser sessions (framework).',
        'cache' => 'Cache entries (framework).',
        'cache_locks' => 'Cache locks (framework).',
        'jobs' => 'Queued jobs (framework).',
        'job_batches' => 'Batches of queued jobs (framework).',
        'failed_jobs' => 'Queued jobs that failed (framework).',
        'password_reset_tokens' => 'Reset tokens (framework).',
        'migrations' => 'Which migrations have run (framework).',
    ];

    private const TABLES = [
        'employees', 'teams', 'leads', 'leads_source', 'courses', 'lead_batches', 'lead_batch_items',
        'lead_feedbacks', 'lead_followups', 'lead_transfers', 'duplicate_lead_reviews', 'enrollments',
        'installment_schedules', 'payments',
    ];

    private const TOOLS = [
        'distribute' => 'Run one distribution cycle',
        'auto-approve' => 'Approve overdue transfers',
        'schedule-run' => 'Run the scheduler once',
        'clear-views' => 'Clear compiled views',
        'clear-cache' => 'Clear every cache (config, routes, views, application)',
        'engine-on' => 'Switch the distribution engine on',
        'engine-off' => 'Switch the distribution engine off',
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
            'logErrors' => $this->recentErrors(),
            'logs' => DeveloperActivityLog::query()->with('target:id,name,role')->latest('id')->limit(12)->get(),
        ]);
    }

    public function database(Request $request): View
    {
        $this->developer($request);

        return view('developer.database', ['schema' => $this->schema()]);
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
            'clear-cache' => (function (): string {
                Artisan::call('optimize:clear');

                return 'Config, route, view and application caches cleared.';
            })(),
            'schedule-run' => (function (): string {
                Artisan::call('schedule:run');

                return 'Scheduler ran once: '.(trim(Artisan::output()) ?: 'nothing was due.');
            })(),
            'engine-on', 'engine-off' => (function () use ($tool): string {
                SystemSetting::put('distribution_engine_enabled', $tool === 'engine-on' ? '1' : '0');

                return $tool === 'engine-on' ? 'Distribution engine is on.' : 'Distribution engine is off.';
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
     * Headlines of the latest errors in the application log, newest first.
     *
     * @return list<string>
     */
    private function recentErrors(): array
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $size = filesize($path);
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, $size - 200_000));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d{2}-\d{2} [\d:]+)\] \w+\.(?:ERROR|CRITICAL|EMERGENCY|ALERT): (.{0,220})/m', $tail, $matches, PREG_SET_ORDER);

        return collect($matches)->reverse()->take(8)->map(fn (array $match): string => $match[1].' — '.$match[2])->values()->all();
    }

    /**
     * The live database structure with each table's model, relations and the code that uses it.
     *
     * @return array{generated: string, driver: string, database: string, modules: list<string>, tables: list<array<string, mixed>>}
     */
    private function schema(): array
    {
        $connection = DB::connection();
        $moduleOf = [];
        foreach (self::MODULES as $module => $names) {
            foreach ($names as $name) {
                $moduleOf[$name] = $module;
            }
        }

        $models = $this->modelMap();
        $sources = $this->sourceFiles();
        $tables = [];

        foreach (Schema::getTables() as $table) {
            $name = (string) $table['name'];
            if (str_starts_with($name, 'pma__') || (isset($table['schema']) && $table['schema'] !== null && $connection->getDriverName() === 'mysql' && strcasecmp((string) $table['schema'], (string) $connection->getDatabaseName()) !== 0)) {
                continue;
            }

            $foreignKeys = collect(Schema::getForeignKeys($name))->map(fn (array $key): array => [
                'name' => $key['name'],
                'columns' => $key['columns'],
                'table' => $key['foreign_table'],
                'foreign_columns' => $key['foreign_columns'],
                'on_update' => $key['on_update'],
                'on_delete' => $key['on_delete'],
            ])->values()->all();
            $indexes = collect(Schema::getIndexes($name))->map(fn (array $index): array => [
                'name' => $index['name'],
                'columns' => $index['columns'],
                'unique' => (bool) $index['unique'],
                'primary' => (bool) $index['primary'],
            ])->values()->all();
            $primary = collect($indexes)->firstWhere('primary', true)['columns'] ?? [];
            $foreignColumns = collect($foreignKeys)->pluck('columns')->flatten()->all();
            $uniqueColumns = collect($indexes)->where('unique', true)->where('primary', false)->filter(fn (array $index): bool => count($index['columns']) === 1)->pluck('columns')->flatten()->all();

            $columns = collect(Schema::getColumns($name))->map(fn (array $column): array => [
                'name' => $column['name'],
                'type' => $column['type'],
                'nullable' => (bool) $column['nullable'],
                'default' => $column['default'],
                'auto' => (bool) $column['auto_increment'],
                'pk' => in_array($column['name'], $primary, true),
                'fk' => in_array($column['name'], $foreignColumns, true),
                'unique' => in_array($column['name'], $uniqueColumns, true),
            ])->values()->all();

            try {
                $rows = DB::table($name)->count();
            } catch (\Throwable) {
                $rows = null;
            }

            $model = $models[$name] ?? null;
            $tables[] = [
                'name' => $name,
                'module' => $moduleOf[$name] ?? 'Other',
                'purpose' => self::PURPOSES[$name] ?? '',
                'rows' => $rows,
                'size' => isset($table['size']) ? (int) $table['size'] : null,
                'columns' => $columns,
                'indexes' => $indexes,
                'foreign_keys' => $foreignKeys,
                'model' => $model === null ? null : ['name' => $model['name'], 'file' => $model['file'], 'relations' => $model['relations']],
                'used_by' => $model === null ? [] : collect($sources)
                    ->filter(fn (string $code): bool => (bool) preg_match('/use App\\\\Models\\\\'.$model['name'].';|\b'.$model['name'].'::/', $code))
                    ->keys()
                    ->values()
                    ->all(),
            ];
        }

        $names = array_column($tables, 'name');
        foreach ($tables as $index => $table) {
            $tables[$index]['referenced_by'] = collect($tables)
                ->flatMap(fn (array $other): array => collect($other['foreign_keys'])
                    ->where('table', $table['name'])
                    ->map(fn (array $key): array => ['table' => $other['name'], 'columns' => $key['columns'], 'foreign_columns' => $key['foreign_columns']])
                    ->all())
                ->values()
                ->all();
            $tables[$index]['foreign_keys'] = collect($table['foreign_keys'])->filter(fn (array $key): bool => in_array($key['table'], $names, true))->values()->all();
        }

        return [
            'generated' => now()->format('M j, Y g:i A'),
            'driver' => $connection->getDriverName(),
            'database' => (string) $connection->getDatabaseName(),
            'modules' => array_merge(array_keys(self::MODULES), collect($tables)->contains('module', 'Other') ? ['Other'] : []),
            'tables' => $tables,
        ];
    }

    /**
     * Eloquent models by table, with the relations each one declares.
     *
     * @return array<string, array{name: string, file: string, relations: list<array{name: string, type: string, related: string}>}>
     */
    private function modelMap(): array
    {
        $map = [];

        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $name = basename($file, '.php');
            $class = 'App\\Models\\'.$name;

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $code = (string) file_get_contents($file);
            preg_match_all('/public function (\w+)\([^)]*\)\s*:\s*\w+\s*\{\s*return \$this->(belongsToMany|belongsTo|hasManyThrough|hasMany|hasOneThrough|hasOne)\((\w+)::class/s', $code, $found, PREG_SET_ORDER);

            $map[(new $class)->getTable()] = [
                'name' => $name,
                'file' => 'app/Models/'.$name.'.php',
                'relations' => array_map(fn (array $match): array => ['name' => $match[1], 'type' => $match[2], 'related' => $match[3]], $found),
            ];
        }

        return $map;
    }

    /**
     * Source of the controllers and actions, keyed by a readable name, to find which code touches a model.
     *
     * @return array<string, string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['Http/Controllers', 'Http/Controllers/Auth', 'Actions'] as $folder) {
            foreach (glob(app_path($folder.'/*.php')) ?: [] as $file) {
                $files[basename($file, '.php')] = (string) file_get_contents($file);
            }
        }

        $files['Console schedule'] = (string) file_get_contents(base_path('routes/console.php'));

        return $files;
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
