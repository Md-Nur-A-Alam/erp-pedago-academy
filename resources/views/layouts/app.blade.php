<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Pedago Academy ERP' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&amp;family=IBM+Plex+Sans:wght@400;500;600;700&amp;family=Noto+Sans+Bengali:wght@400;600&amp;display=swap" rel="stylesheet">
    @if (($title ?? null) === 'Sign in')
        <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,600;0,700;1,400&amp;display=swap" rel="stylesheet">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-pedago-canvas font-sans text-pedago-ink antialiased">
    @auth
        @php
            $currentEmployee = auth()->user()->employee;
            $role = $currentEmployee?->role;
            $isAdmin = (bool) $currentEmployee?->isAdmin();
            $seesLeads = $isAdmin || in_array($role, ['MarketingExecutive', 'Supervisor', 'Team Leader', 'Sales Executive'], true);
            $managesIntake = $isAdmin || $role === 'MarketingExecutive';
            $seesFinance = $isAdmin || $role === 'Accounts';
            $navCounts ??= [];

            // label, route, active route patterns, icon, visible, count key, count is urgent
            $navigation = [
                'Overview' => [
                    ['Dashboard', 'dashboard', ['dashboard'], 'grid', true, null, false],
                    ['Reports', 'reports.index', ['reports.*'], 'bar-chart', $isAdmin, null, false],
                ],
                'Sales' => [
                    ['Central queue', 'central-queue.index', ['central-queue.*'], 'inbox', $isAdmin, 'queue', false],
                    ['My tasks', 'sales.tasks', ['sales.tasks'], 'check-square', $role === 'Sales Executive', 'tasks', false],
                    [$role === 'Sales Executive' ? 'My leads' : 'All leads', 'leads.index', ['leads.index', 'leads.workspace'], 'list', $seesLeads, null, false],
                    ['Upload leads', 'leads.upload', ['leads.upload'], 'upload', $managesIntake, null, false],
                    ['Campaigns', 'campaigns.index', ['campaigns.*'], 'flag', $managesIntake, null, false],
                    ['Duplicate review', 'duplicate-lead-reviews.index', ['duplicate-lead-reviews.*'], 'copy', $isAdmin, 'duplicates', false],
                    [$role === 'Sales Executive' ? 'My follow-ups' : 'Follow-ups', 'follow-ups.index', ['follow-ups.*'], 'clock', $isAdmin || in_array($role, ['Supervisor', 'Team Leader', 'Sales Executive'], true), 'overdue', true],
                    ['Transfers', 'lead-transfers.index', ['lead-transfers.*'], 'repeat', $isAdmin || $role === 'Team Leader', 'transfers', false],
                ],
                'Enrollment & Finance' => [
                    ['Payments review', 'payments.index', ['payments.*'], 'credit-card', $seesFinance, 'payments', false],
                    ['Installments', 'installments.index', ['installments.*'], 'calendar', $seesFinance, null, false],
                ],
                'People' => [
                    ['Teams & roster', 'teams.index', ['teams.*'], 'users', $isAdmin || in_array($role, ['Supervisor', 'Team Leader'], true), null, false],
                    ['Employees', 'employees.index', ['employees.*'], 'user', $isAdmin, null, false],
                    ['Approvals', 'employee-change-requests.index', ['employee-change-requests.*'], 'check-square', $currentEmployee?->lowerAuthorityRoles() !== [], 'approvals', false],
                ],
                'Catalogue' => [
                    ['Courses', 'courses.index', ['courses.*'], 'book-open', $isAdmin, null, false],
                    ['Subjects', 'subjects.index', ['subjects.*', 'course-topics.*'], 'tag', $isAdmin, null, false],
                    ['Course batches', 'course-batches.index', ['course-batches.*'], 'layers', $isAdmin, null, false],
                ],
                'Help' => [
                    [$isAdmin ? 'Documentation' : 'My guide', 'guide.index', ['guide.*'], 'help', true, null, false],
                ],
                'System' => [
                    ['Settings', 'settings.index', ['settings.*'], 'sliders', $isAdmin, null, false],
                    ['Staff passwords', 'staff-passwords.index', ['staff-passwords.*'], 'lock', $isAdmin, null, false],
                ],
            ];
            $initials = collect(explode(' ', trim((string) $currentEmployee?->name)))
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('');
        @endphp

        <a class="sr-only z-50 rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white focus:not-sr-only focus:fixed focus:left-3 focus:top-3" href="#main-content">Skip to content</a>

        <div class="min-h-screen lg:flex">
            <div class="fixed inset-0 z-30 hidden bg-pedago-ink/40 data-[open=true]:block" id="app-sidebar-backdrop" aria-hidden="true"></div>

            <aside class="fixed inset-y-0 left-0 z-40 flex w-64 max-w-[85vw] -translate-x-full flex-col border-r border-pedago-line bg-white shadow-xl transition-transform duration-200 ease-out data-[open=true]:translate-x-0" id="app-sidebar" aria-label="Primary navigation">
                <div class="flex items-center justify-between gap-2 px-4 pb-2 pt-4">
                    <a class="block rounded-md" href="{{ route('dashboard') }}" aria-label="Pedago Academy dashboard">
                        <img class="h-auto w-full max-w-44" src="{{ asset('user_defined_assets/img/horizontal%20logo%201%20on%20whtie%20bg.png') }}" alt="Pedago Academy" width="176" height="48">
                    </a>
                    <button class="inline-flex size-11 items-center justify-center rounded-md text-pedago-muted transition-colors duration-150 hover:bg-pedago-canvas hover:text-pedago-ink" id="app-sidebar-close" type="button" aria-label="Close navigation">
                        <x-ui.icon name="x" class="size-5" />
                    </button>
                </div>

                <nav class="flex flex-1 flex-col gap-4 overflow-y-auto px-3 py-3">
                    @foreach ($navigation as $group => $items)
                        @php $visibleItems = array_filter($items, fn (array $item): bool => (bool) $item[4]); @endphp
                        @continue($visibleItems === [])
                        <div class="flex flex-col gap-0.5">
                            <p class="mb-1 px-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-pedago-muted">{{ $group }}</p>
                            @foreach ($visibleItems as [$label, $routeName, $patterns, $icon, $visible, $countKey, $urgent])
                                @php $active = request()->routeIs(...$patterns); @endphp
                                @php $count = $countKey !== null ? ($navCounts[$countKey] ?? 0) : 0; @endphp
                                <a @class([
                                    'flex min-h-10 items-center gap-2.5 rounded-md px-2.5 text-sm transition-colors duration-150 lg:min-h-9',
                                    'bg-pedago-tint font-semibold text-pedago-blue-dark' => $active,
                                    'font-medium text-pedago-ink hover:bg-pedago-canvas' => ! $active,
                                ]) href="{{ route($routeName) }}" @if ($active) aria-current="page" @endif>
                                    <x-ui.icon :name="$icon" @class(['text-pedago-blue' => $active, 'text-pedago-muted' => ! $active]) />
                                    <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                                    @if ($count > 0)
                                        <span @class([
                                            'num rounded-full px-1.5 text-xs font-medium',
                                            'bg-pedago-red-tint text-pedago-red' => $urgent,
                                            'bg-white text-pedago-blue-dark' => ! $urgent && $active,
                                            'bg-pedago-canvas text-pedago-ink' => ! $urgent && ! $active,
                                        ])>{{ number_format($count) }}@if ($urgent)<span class="font-sans"> overdue</span>@endif</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>

                <div class="flex items-center gap-2 border-t border-pedago-line px-3 py-3">
                    <a class="flex min-w-0 flex-1 items-center gap-2.5 rounded-md p-1.5 transition-colors duration-150 hover:bg-pedago-canvas" href="{{ route('profile.edit') }}" aria-label="My profile" title="My profile">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-pedago-tint text-xs font-bold text-pedago-blue-dark" aria-hidden="true">{{ $initials }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-pedago-ink">{{ $currentEmployee?->name }}</span>
                            <span class="block truncate text-xs text-pedago-muted">{{ $currentEmployee?->designation }} · {{ $currentEmployee?->role }}</span>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="inline-flex size-10 items-center justify-center rounded-md text-pedago-red transition-colors duration-150 hover:bg-pedago-red-tint" type="submit" aria-label="Log out" title="Log out">
                            <x-ui.icon name="log-out" />
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-pedago-line bg-white px-4 py-2.5 sm:px-6 lg:min-h-16">
                    <button class="inline-flex size-11 shrink-0 items-center justify-center rounded-md border border-pedago-line text-pedago-ink transition-colors duration-150 hover:bg-pedago-canvas" id="app-sidebar-toggle" type="button" aria-controls="app-sidebar" aria-expanded="false" aria-label="Toggle navigation">
                        <x-ui.icon name="menu" class="size-5" />
                    </button>
                    <div class="mr-auto min-w-0">
                        <h1 class="truncate text-lg font-bold tracking-tight text-pedago-ink">@yield('page-title', $title ?? 'Pedago Academy ERP')</h1>
                        @hasSection('page-description')
                            <p class="truncate text-xs text-pedago-muted">@yield('page-description')</p>
                        @endif
                    </div>

                    @if ($seesLeads)
                        <form class="order-last w-full sm:order-none sm:w-60" method="GET" action="{{ route('leads.index') }}" role="search">
                            <label class="sr-only" for="global-search">Search leads by phone, name or lead code</label>
                            <span class="relative block">
                                <x-ui.icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-pedago-muted" />
                                <input class="h-9 w-full rounded-md border border-pedago-line bg-pedago-canvas pl-8 pr-3 text-sm text-pedago-ink placeholder:text-pedago-muted focus:border-pedago-blue focus:bg-white" id="global-search" name="search" type="search" placeholder="Search phone, name or lead code" value="{{ request()->routeIs('leads.index') ? request('search') : '' }}">
                            </span>
                        </form>
                    @endif

                    @if ($isAdmin && $engineEnabled !== null)
                        <form method="POST" action="{{ route('settings.engine') }}" data-confirm="{{ $engineEnabled ? 'Turn automatic distribution off? Leads will wait in the queue until you run a cycle or assign them yourself.' : 'Turn automatic distribution on? Queue leads will be assigned to available executives on every scheduled cycle.' }}">
                            @csrf
                            <input type="hidden" name="enabled" value="{{ $engineEnabled ? 0 : 1 }}">
                            <button @class([
                                'inline-flex h-9 items-center gap-2 whitespace-nowrap rounded-md border border-pedago-line bg-white px-3 text-sm font-semibold transition-colors duration-150 hover:bg-pedago-canvas',
                                'text-pedago-green' => $engineEnabled,
                                'text-pedago-muted' => ! $engineEnabled,
                            ]) type="submit" role="switch" aria-checked="{{ $engineEnabled ? 'true' : 'false' }}">
                                <span @class(['size-2 rounded-full', 'bg-pedago-green' => $engineEnabled, 'bg-pedago-muted/50' => ! $engineEnabled]) aria-hidden="true"></span>
                                Distribution engine: {{ $engineEnabled ? 'ON' : 'OFF' }}
                            </button>
                        </form>
                    @endif

                    <div class="flex max-w-full flex-wrap items-center justify-end gap-2 empty:hidden">@yield('page-actions')@if (request()->routeIs('dashboard') && $managesIntake)<x-ui.button :href="route('leads.upload')" icon="upload">Upload leads</x-ui.button>@endif</div>
                </header>

                <main class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-4 px-4 py-5 focus:outline-none sm:px-6" id="main-content" tabindex="-1">
                    @if (session('status'))
                        <div class="flex items-start gap-2.5 rounded-lg border border-pedago-green/30 bg-pedago-green-tint px-4 py-3 text-sm font-medium text-pedago-green" role="status">
                            <x-ui.icon name="check-circle" class="mt-0.5 size-4" />
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="flex items-start gap-2.5 rounded-lg border border-pedago-red-line bg-pedago-red-tint px-4 py-3 text-sm text-pedago-red" role="alert">
                            <x-ui.icon name="alert" class="mt-0.5 size-4" />
                            <ul class="space-y-1 font-medium">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>

        <script>
            (() => {
                const sidebar = document.getElementById('app-sidebar');
                const backdrop = document.getElementById('app-sidebar-backdrop');
                const toggle = document.getElementById('app-sidebar-toggle');

                const setOpen = (open) => {
                    sidebar.dataset.open = String(open);
                    backdrop.dataset.open = String(open);
                    toggle.setAttribute('aria-expanded', String(open));
                    sidebar.inert = !open;
                    document.body.classList.toggle('overflow-hidden', open);
                };

                setOpen(false);
                toggle.addEventListener('click', () => {
                    if (sidebar.dataset.open === 'true') {
                        setOpen(false);
                        return;
                    }
                    setOpen(true);
                    sidebar.querySelector('nav a')?.focus();
                });
                const close = () => {
                    setOpen(false);
                    toggle.focus();
                };
                document.getElementById('app-sidebar-close').addEventListener('click', close);
                backdrop.addEventListener('click', close);
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && sidebar.dataset.open === 'true') {
                        close();
                    }
                });

                document.addEventListener('submit', (event) => {
                    const message = event.target.dataset ? event.target.dataset.confirm : null;
                    if (message && !window.confirm(message)) {
                        event.preventDefault();
                    }
                });

                document.addEventListener('click', (event) => {
                    const opener = event.target.closest('[data-dialog-open]');
                    const dialog = opener ? document.getElementById(opener.dataset.dialogOpen) : null;
                    if (dialog) {
                        if (opener.dataset.dialogAction) {
                            dialog.querySelector('form[method="POST"]').action = opener.dataset.dialogAction;
                        }
                        dialog.querySelectorAll('[data-dialog-subject]').forEach((node) => {
                            node.textContent = opener.dataset.dialogSubject || '';
                        });
                        dialog.showModal();
                    }
                    const closer = event.target.closest('[data-dialog-close]');
                    if (closer) {
                        closer.closest('dialog')?.close();
                    }
                });
            })();
        </script>
    @else
        <main class="flex min-h-screen w-full items-start justify-center bg-[#F8F8F8] px-4 py-4 min-[861px]:items-center min-[861px]:px-0 min-[861px]:py-0">
            @yield('content')
        </main>
    @endauth
    @stack('scripts')
</body>
</html>
