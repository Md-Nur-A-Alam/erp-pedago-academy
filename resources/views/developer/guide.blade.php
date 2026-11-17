@extends('layouts.app', ['title' => 'Developer guide'])

@section('page-title', 'Developer guide')
@section('page-description', 'How the code is organised, where each rule lives, and how to test and extend it safely.')

@section('page-actions')
    <x-ui.button :href="route('developer.index')" variant="secondary" icon="terminal">Developer panel</x-ui.button>
@endsection

@section('content')
    @php
        $chapters = [
            'setup' => 'Setting up',
            'access' => 'Developer access',
            'switching' => 'Switching panels',
            'architecture' => 'Architecture',
            'rules' => 'Where each rule lives',
            'settings' => 'Settings',
            'scheduler' => 'Scheduled commands',
            'ui' => 'Interface kit',
            'recipes' => 'Recipes',
            'testing' => 'Testing and conventions',
            'troubleshooting' => 'Troubleshooting',
        ];
        $code = 'num block overflow-x-auto whitespace-pre rounded-md bg-pedago-ink px-3 py-2.5 text-[12.5px] leading-6 text-white';
        $table = 'w-full min-w-[560px] border-collapse text-left';
        $th = 'border-y border-pedago-line bg-pedago-canvas px-3 py-2 text-[11.5px] font-semibold uppercase tracking-wide text-pedago-muted';
        $td = 'border-b border-pedago-line px-3 py-2 align-top';
        $list = 'flex max-w-3xl list-disc flex-col gap-1.5 pl-5 text-sm leading-6 marker:text-pedago-blue-soft';
    @endphp

    <div class="grid gap-4 lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start">
        <nav class="rounded-lg border border-pedago-line bg-white p-3 lg:sticky lg:top-20" aria-label="On this page">
            <p class="mb-1 px-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-pedago-muted">On this page</p>
            <ol class="flex flex-col">
                @foreach ($chapters as $anchor => $chapter)
                    <li><a class="block rounded-md px-2 py-1.5 text-[13px] font-medium transition-colors duration-150 hover:bg-pedago-canvas" href="#{{ $anchor }}">{{ $chapter }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div class="flex min-w-0 flex-col gap-4">
            <x-ui.card title="Setting up" id="setup">
                <p class="max-w-3xl text-sm leading-6">Laravel 13 on PHP 8.5, Blade with Tailwind CSS 4 through Vite, Pest for tests. MySQL in development, SQLite in memory for tests.</p>
                <code class="{{ $code }} mt-2">composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
composer run dev            # server, queue, logs, Vite
php artisan schedule:work   # automatic distribution and auto-approval</code>
                <div class="mt-3 overflow-x-auto">
                    <table class="{{ $table }} text-[13px]">
                        <thead><tr><th class="{{ $th }}" scope="col">Environment key</th><th class="{{ $th }}" scope="col">Purpose</th></tr></thead>
                        <tbody>
                            <tr><th class="{{ $td }} num font-medium" scope="row">INITIAL_ADMIN_PHONE, INITIAL_ADMIN_PASSWORD</th><td class="{{ $td }}">The seeder creates the first administrator from these</td></tr>
                            <tr><th class="{{ $td }} num font-medium" scope="row">APP_TIMEZONE</th><td class="{{ $td }}">Asia/Dhaka. Shifts, cut-off and lead age all depend on it</td></tr>
                            <tr><th class="{{ $td }} num font-medium" scope="row">STAFF_PASSWORD_KEY</th><td class="{{ $td }}">Key of the reversible password cipher. Empty means one-way hashing. Never change it once accounts exist</td></tr>
                            <tr><th class="{{ $td }} num font-medium" scope="row">DEVELOPER_PANEL</th><td class="{{ $td }}">Turns this panel on or off. Defaults to on everywhere except <span class="num">APP_ENV=production</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="Developer access" id="access">
                <ul class="{{ $list }}">
                    <li>A developer gives another employee the <b>Developer</b> role in Employees. Administrators cannot make developers.</li>
                    <li>A developer holds <b>every administrator permission</b>: all screens, settings, the engine switch, staff passwords, employees, teams, leads, finance and reports. On top of that the sidebar shows the developer panel and this guide.</li>
                    <li>The developer ranks above administrators for change requests, and does not count as an administrator for the “last active administrator” rule.</li>
                    <li>The <b>Database</b> page reads the live schema: an ER diagram you can drag, zoom and filter, a table list, a table-to-model-to-code mapping and every foreign key. Export it as SVG, JSON or Mermaid.</li>
                    <li>A developer is not a sales person, so has no leave or KPI pages. Open someone’s panel to see theirs.</li>
                    <li>When <span class="num">DEVELOPER_PANEL</span> is off, every developer route answers 404.</li>
                </ul>
            </x-ui.card>

            <x-ui.card title="Switching panels" id="switching">
                <ol class="flex max-w-3xl list-decimal flex-col gap-1.5 pl-5 text-sm leading-6 marker:font-semibold marker:text-pedago-blue">
                    <li>In the developer panel, press <b>Open</b> next to any active employee. You are signed in as that person and land on their dashboard.</li>
                    <li>A dark banner stays at the top of every page. Use its <b>Switch panel</b> list to jump straight to another employee, or <b>Return to developer panel</b> to become yourself again.</li>
                    <li>Your own user id is kept in the session under <span class="num">{{ \App\Http\Controllers\DeveloperController::SESSION_KEY }}</span>. Signing out ends both sessions.</li>
                </ol>
                <h3 class="mt-4 text-sm font-semibold">What changes while you view as someone</h3>
                <ul class="{{ $list }} mt-1">
                    <li>The first-sign-in password change and the profile-completion step are skipped, so you can open accounts that have never been used.</li>
                    <li>Everything else is real: role scoping, permissions, and every save. Data you change is changed for that employee.</li>
                    <li>You cannot open other developers or employees who are not active.</li>
                    <li>Reading a staff password still asks for the signed-in admin’s own password, which you do not have, so passwords stay protected.</li>
                    <li>Every switch and every tool run is written to <span class="num">developer_activity_logs</span> and shown in the panel.</li>
                </ul>
            </x-ui.card>

            <x-ui.card title="Architecture" id="architecture">
                <p class="max-w-3xl text-sm leading-6">A request goes <b>route → middleware → controller → action → model → Blade view</b>. Controllers validate input, check the role, call one action and return a view or redirect. Business rules that change data live in action classes, so the same rule serves every screen and every test.</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="{{ $table }} text-[13px]">
                        <thead><tr><th class="{{ $th }}" scope="col">Folder</th><th class="{{ $th }}" scope="col">Holds</th></tr></thead>
                        <tbody>
                            @foreach ([
                                'routes/web.php' => 'All screens, inside the auth and employee.onboarding middleware group',
                                'routes/console.php' => 'Artisan commands and the schedule',
                                'app/Http/Controllers' => 'One controller per screen; the base Controller has employeeWithRole() and reportingPeriod()',
                                'app/Http/Middleware' => 'EnsureEmployeeOnboardingComplete: account status, first-sign-in password, profile completion',
                                'app/Actions' => 'Business rules that change data (distribution, feedback, transfers, payments, passwords, imports, enrollment)',
                                'app/Models' => 'Eloquent models; Employee holds role scoping and availability',
                                'app/Providers/AppServiceProvider.php' => 'Login rate limit, password hasher, sidebar counts and developer banner data',
                                'resources/views/components/ui' => 'Shared interface components',
                                'resources/views/panels' => 'Dashboard sections for non-admin roles',
                                'tests/Feature' => 'Pest feature tests, one file per area',
                            ] as $folder => $purpose)
                                <tr><th class="{{ $td }} num font-medium" scope="row">{{ $folder }}</th><td class="{{ $td }}">{{ $purpose }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="Where each rule lives" id="rules">
                <div class="overflow-x-auto">
                    <table class="{{ $table }} text-[13px]">
                        <thead><tr><th class="{{ $th }}" scope="col">Rule</th><th class="{{ $th }}" scope="col">Code</th></tr></thead>
                        <tbody>
                            @foreach ([
                                'Which leads an employee may see' => 'Employee::visibleLeads()',
                                'Teams and executives a manager reaches' => 'Employee::managedTeamIds(), managedExecutiveIds()',
                                'Availability now (Available, Busy, Cut Off, Lazy…)' => 'Employee::effectiveAvailabilityStatus(), effectiveShift()',
                                'Lead temperature and priority' => 'Lead::temperature(), priorityRank(), HIGH_INTENT_CHANNELS',
                                'Distribution cycle, plan and batch assignment' => 'Actions\\DistributeLeads: plan(), pickForCycle(), run(), assignBatch()',
                                'A sales person’s ranked call list' => 'Actions\\CallList::for()',
                                'KPIs, targets and grades' => 'Actions\\PerformanceMetrics: forEmployees(), summarise(), grade(), targets()',
                                'Leave: who may decide, overlap, balance' => 'Models\\LeaveRequest::canBeReviewedBy(), reviewableBy(); LeaveController',
                                'On Leave in availability' => 'Employee::isOnLeave(), todayLeave(); effectiveAvailabilityStatus()',
                                'Who counts as a sales person' => 'Employee::SALES_ROLES, isSalesPerson()',
                                'Developer holds admin permissions' => 'Employee::isAdmin(); Controller::employeeWithRole()',
                                'Call feedback and follow-up scheduling' => 'Actions\\RecordLeadFeedback',
                                'Freeing a batch slot' => 'LeadBatchItem::completeOpenForLead()',
                                'Transfer approval, rejection, auto-approval' => 'Actions\\DecideLeadTransfer',
                                'Payment verification and rejection' => 'Actions\\ReviewPayment',
                                'Reversible password storage' => 'Actions\\StaffPasswordCipher, StaffPasswordHasher',
                                'Last active administrator' => 'Employee::booted() updating guard',
                                'Team permissions' => 'TeamController::employeeFor()',
                                'Every setting, default and range' => 'SystemSetting::DEFINITIONS; read with SystemSetting::integer() or enabled()',
                                'Role guides and the rule book' => 'GuideController',
                            ] as $rule => $where)
                                <tr><th class="{{ $td }} font-medium" scope="row">{{ $rule }}</th><td class="{{ $td }} num">{{ $where }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="Settings" id="settings">
                <div class="overflow-x-auto">
                    <table class="{{ $table }} text-[13px]">
                        <thead><tr><th class="{{ $th }}" scope="col">Key</th><th class="{{ $th }}" scope="col">Default</th><th class="{{ $th }}" scope="col">Range</th><th class="{{ $th }}" scope="col">Group</th></tr></thead>
                        <tbody>
                            @foreach ($settings as $key => $definition)
                                <tr>
                                    <th class="{{ $td }} num font-medium" scope="row">{{ $key }}</th>
                                    <td class="{{ $td }} num">{{ $definition['default'] }} {{ $definition['unit'] ?? '' }}</td>
                                    <td class="{{ $td }} num">{{ $definition['type'] === 'boolean' ? '0 or 1' : $definition['min'].'–'.$definition['max'] }}</td>
                                    <td class="{{ $td }}">{{ $definition['group'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="Scheduled commands" id="scheduler">
                <ul class="{{ $list }}">
                    @foreach ($schedule as $line)
                        <li class="num">{{ $line }}</li>
                    @endforeach
                    <li><span class="num">php artisan leads:distribute --force</span> runs a cycle even when the engine switch is off.</li>
                </ul>
            </x-ui.card>

            <x-ui.card title="Interface kit" id="ui">
                <p class="max-w-3xl text-sm leading-6">Use these components instead of writing new markup. Colours come from the <span class="num">pedago-*</span> tokens in <span class="num">resources/css/app.css</span>; numbers use the <span class="num">num</span> utility.</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="{{ $table }} text-[13px]">
                        <thead><tr><th class="{{ $th }}" scope="col">Component</th><th class="{{ $th }}" scope="col">Props</th></tr></thead>
                        <tbody>
                            @foreach ([
                                'x-ui.card' => 'title, meta (slot), flush, id',
                                'x-ui.stat' => 'label, value, hint, alert, href',
                                'x-ui.pill' => 'status (coloured automatically) or tone: green, blue, red, amber, slate',
                                'x-ui.tabs' => 'tabs, current, route, param, counts, query, label',
                                'x-ui.button' => 'variant: primary, secondary, danger, success; size: md, sm; href; icon',
                                'x-ui.dialog' => 'id, title. Open with data-dialog-open, data-dialog-action, data-dialog-subject',
                                'x-ui.empty' => 'title, icon; slot for the explanation',
                                'x-ui.icon' => 'name, from the list inside the component',
                            ] as $component => $props)
                                <tr><th class="{{ $td }} num font-medium" scope="row">&lt;{{ $component }}&gt;</th><td class="{{ $td }}">{{ $props }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-pedago-muted">Forms with <span class="num">data-confirm="…"</span> ask before submitting. Write inline PHP as <span class="num">@@php … @@endphp</span>; the short <span class="num">@@php(…)</span> form breaks when a block form appears later in the same view.</p>
            </x-ui.card>

            <x-ui.card title="Recipes" id="recipes">
                <h3 class="text-sm font-semibold">Add a setting</h3>
                <ol class="{{ $list }} mt-1 list-decimal">
                    <li>Add an entry to <span class="num">SystemSetting::DEFINITIONS</span> with default, type, range, label, unit, group and description.</li>
                    <li>Read it with <span class="num">SystemSetting::integer('key')</span>. The settings screen, validation, seeder and guides pick it up on their own.</li>
                </ol>
                <h3 class="mt-4 text-sm font-semibold">Add a sidebar item</h3>
                <ol class="{{ $list }} mt-1 list-decimal">
                    <li>Add a row to <span class="num">$navigation</span> in <span class="num">layouts/app.blade.php</span>: label, route, active patterns, icon, who sees it, count key, urgent.</li>
                    <li>For a badge, add the count in <span class="num">AppServiceProvider::navigationCounts()</span> under the same key.</li>
                </ol>
                <h3 class="mt-4 text-sm font-semibold">Add a screen for some roles</h3>
                <ol class="{{ $list }} mt-1 list-decimal">
                    <li>Make the controller with <span class="num">php artisan make:controller</span>; start each method with <span class="num">$this-&gt;employeeWithRole($request, [...roles])</span>.</li>
                    <li>Scope queries with <span class="num">visibleLeads()</span> or <span class="num">managedExecutiveIds()</span>, never with an unscoped <span class="num">Lead::query()</span>.</li>
                    <li>Put the rule in an action class, write a Pest test for allowed and forbidden roles, then add the sidebar item and update the role guide in <span class="num">GuideController</span>.</li>
                </ol>
                <h3 class="mt-4 text-sm font-semibold">Change the distribution rule</h3>
                <ol class="{{ $list }} mt-1 list-decimal">
                    <li>Edit <span class="num">DistributeLeads::plan()</span> (how many) or <span class="num">pickForCycle()</span> (which leads).</li>
                    <li>Update the data set in <span class="num">tests/Feature/LeadDistributionTest.php</span> first, then the rule book chapter in <span class="num">GuideController::systemRules()</span>.</li>
                </ol>
            </x-ui.card>

            <x-ui.card title="Testing and conventions" id="testing">
                <code class="{{ $code }}">php artisan test --compact                       # whole suite
php artisan test --compact --filter=Distribution   # one area
vendor/bin/pint --dirty                           # format changed PHP</code>
                <ul class="{{ $list }} mt-3">
                    <li>Tests run on SQLite in memory with <span class="num">RefreshDatabase</span>. Run against MySQL before a release as well.</li>
                    <li>Freeze time with <span class="num">$this-&gt;travelTo(...)</span> or <span class="num">Carbon::today()-&gt;setTime(...)</span>; never hard-code today’s date.</li>
                    <li>Each test file defines its own <span class="num">create…User()</span> helper that makes a user, an employee and complete details, so the onboarding middleware lets it through.</li>
                    <li><span class="num">phpunit.xml</span> sets a test-only <span class="num">STAFF_PASSWORD_KEY</span>.</li>
                    <li>Follow <span class="num">AGENTS.md</span>: no new top-level folders or packages without approval, Pint on every PHP change, a test for every rule.</li>
                    <li>Do not commit <span class="num">.env</span>, database dumps or customer data.</li>
                </ul>
            </x-ui.card>

            <x-ui.card title="Troubleshooting" id="troubleshooting">
                <div class="overflow-x-auto">
                    <table class="{{ $table }} text-[13px]">
                        <thead><tr><th class="{{ $th }}" scope="col">Symptom</th><th class="{{ $th }}" scope="col">Cause and fix</th></tr></thead>
                        <tbody>
                            @foreach ([
                                'New classes or styles do not appear' => 'Run npm run build, or keep npm run dev running',
                                '“Unable to locate file in Vite manifest”' => 'Same: build the front end',
                                'Every executive shows Off Duty' => 'No shift on the employee, their team or a date override, or the time zone is wrong',
                                'Leads never leave the queue' => 'Engine switch off and scheduler not running, campaign in Manual mode, or nobody Available',
                                'Nobody can sign in after a key change' => 'STAFF_PASSWORD_KEY was changed; restore the old key',
                                'Developer routes return 404' => 'DEVELOPER_PANEL is off, or APP_ENV is production',
                                'A view throws a parse error about endif' => 'An inline @php(...) is mixed with @php blocks in the same view; use the block form',
                            ] as $symptom => $fix)
                                <tr><th class="{{ $td }} font-medium" scope="row">{{ $symptom }}</th><td class="{{ $td }}">{{ $fix }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>
@endsection
