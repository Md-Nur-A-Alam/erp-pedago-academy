<!DOCTYPE html>
<html class="group/app" lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-sidebar="closed">
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
    <script>
        (() => {
            const desktop = window.matchMedia('(min-width: 1024px)').matches;
            let saved = null;
            try {
                saved = window.localStorage.getItem('pedago.sidebar');
            } catch (error) {
                saved = null;
            }
            document.documentElement.dataset.sidebar = desktop && saved !== 'closed' ? 'open' : 'closed';
        })();
    </script>
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
            $isDeveloper = $role === 'Developer';
            $isSalesPerson = (bool) $currentEmployee?->isSalesPerson();
            $reviewsLeave = $isAdmin || in_array($role, ['Supervisor', 'Team Leader'], true);

            // label, route, active route patterns, icon, visible, count key, count is urgent
            $navigation = [
                'Overview' => [
                    ['Dashboard', 'dashboard', ['dashboard'], 'grid', true, null, false],
                    ['Notifications', 'notifications.index', ['notifications.*'], 'bell', true, 'notifications', true],
                    ['Performance', 'performance.index', ['performance.*'], 'trending-up', true, null, false],
                    ['Reports', 'reports.index', ['reports.*'], 'bar-chart', $isAdmin, null, false],
                ],
                'Sales' => [
                    ['Central queue', 'central-queue.index', ['central-queue.*'], 'inbox', $isAdmin, 'queue', false],
                    ['Assignment controls', 'assignment-controls.index', ['assignment-controls.*'], 'sliders', $isAdmin, null, false],
                    ['My tasks', 'sales.tasks', ['sales.tasks'], 'check-square', $isSalesPerson, 'tasks', false],
                    [$role === 'Sales Executive' ? 'My leads' : 'All leads', 'leads.index', ['leads.index', 'leads.workspace'], 'list', $seesLeads, null, false],
                    ['Number check', 'number-check.index', ['number-check.*'], 'search', $seesLeads, null, false],
                    ['Upload leads', 'leads.upload', ['leads.upload'], 'upload', $managesIntake, null, false],
                    ['Paste leads', 'leads.paste', ['leads.paste*'], 'copy', $managesIntake, null, false],
                    ['Merge duplicates', 'leads.merge', ['leads.merge*'], 'repeat', $isAdmin, null, false],
                    ['Campaigns', 'campaigns.index', ['campaigns.*'], 'flag', $managesIntake, null, false],
                    ['Duplicate review', 'duplicate-lead-reviews.index', ['duplicate-lead-reviews.*'], 'copy', $isAdmin, 'duplicates', false],
                    ['My follow-ups', 'follow-ups.mine', ['follow-ups.mine'], 'clock', $role === 'Team Leader', 'myoverdue', true],
                    [$role === 'Sales Executive' ? 'My follow-ups' : ($role === 'Team Leader' ? 'Team follow-ups' : 'Follow-ups'), 'follow-ups.index', $role === 'Sales Executive' ? ['follow-ups.*'] : ['follow-ups.index'], 'clock', $isAdmin || in_array($role, ['Supervisor', 'Team Leader', 'Sales Executive'], true), 'overdue', true],
                    ['Transfers', 'lead-transfers.index', ['lead-transfers.*'], 'repeat', $isAdmin || $role === 'Team Leader', 'transfers', false],
                ],
                'Enrollment & Finance' => [
                    ['Payments review', 'payments.index', ['payments.*'], 'credit-card', $seesFinance, 'payments', false],
                    ['Installments', 'installments.index', ['installments.*'], 'calendar', $seesFinance, null, false],
                ],
                'People' => [
                    ['Teams & roster', 'teams.index', ['teams.*'], 'users', $isAdmin || in_array($role, ['Supervisor', 'Team Leader'], true), null, false],
                    ['Employees', 'employees.index', ['employees.*'], 'user', $isAdmin, null, false],
                    ['Leave', 'leave.index', ['leave.index'], 'sun', ! $isDeveloper, null, false],
                    ['Leave requests', 'leave.requests', ['leave.requests'], 'check-square', $reviewsLeave, 'leave', false],
                    ['Approvals', 'employee-change-requests.index', ['employee-change-requests.*'], 'check-square', $currentEmployee?->lowerAuthorityRoles() !== [], 'approvals', false],
                ],
                'Catalogue' => [
                    ['Courses', 'courses.index', ['courses.*'], 'book-open', $isAdmin, null, false],
                    ['Subjects', 'subjects.index', ['subjects.*', 'course-topics.*'], 'tag', $isAdmin, null, false],
                    ['Course batches', 'course-batches.index', ['course-batches.*'], 'layers', $isAdmin, null, false],
                ],
                'Developer' => [
                    ['Developer panel', 'developer.index', ['developer.index'], 'terminal', $isDeveloper, null, false],
                    ['Database', 'developer.database', ['developer.database'], 'database', $isDeveloper, null, false],
                    ['Developer guide', 'developer.guide', ['developer.guide'], 'code', $isDeveloper, null, false],
                ],
                'Help' => [
                    [$isAdmin ? 'Documentation' : 'My guide', 'guide.index', ['guide.*'], 'help', true, null, false],
                ],
                'System' => [
                    ['Settings', 'settings.index', ['settings.*'], 'sliders', $isAdmin, null, false],
                    ['Data health', 'data-health.index', ['data-health.*'], 'check-square', $isAdmin, null, false],
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

        <div class="min-h-screen">
            <div class="fixed inset-0 z-30 hidden bg-pedago-ink/40 group-data-[sidebar=open]/app:block lg:group-data-[sidebar=open]/app:hidden" id="app-sidebar-backdrop" aria-hidden="true"></div>

            <aside class="fixed inset-y-0 left-0 z-40 flex w-16 flex-col border-r border-pedago-line bg-white transition-[width] duration-200 ease-out group-data-[sidebar=open]/app:w-64 max-lg:group-data-[sidebar=open]/app:shadow-xl" id="app-sidebar" aria-label="Primary navigation">
                <div class="flex h-16 shrink-0 items-center border-b border-pedago-line px-3 group-data-[sidebar=closed]/app:justify-center group-data-[sidebar=closed]/app:px-0">
                    <a class="block rounded-md" href="{{ route('dashboard') }}" aria-label="Pedago Academy home">
                        <img class="h-auto w-full max-w-44 group-data-[sidebar=closed]/app:hidden" src="{{ asset('user_defined_assets/img/horizontal%20logo%201%20on%20whtie%20bg.png') }}" alt="Pedago Academy" width="176" height="48">
                        <img class="hidden size-9 group-data-[sidebar=closed]/app:block" src="{{ asset('user_defined_assets/img/only%20logo%20icon.png') }}" alt="" width="36" height="36">
                    </a>
                </div>

                <button class="absolute -right-3 top-5 z-10 inline-flex size-6 items-center justify-center rounded-full border border-pedago-line bg-white text-pedago-muted transition-colors duration-150 hover:border-pedago-blue hover:text-pedago-blue-dark" id="app-sidebar-toggle" type="button" aria-controls="app-sidebar" aria-expanded="false" aria-label="Collapse navigation to icons">
                    <x-ui.icon name="chevron-left" class="size-4 transition-transform duration-200 group-data-[sidebar=closed]/app:rotate-180" />
                </button>

                <nav class="flex flex-1 flex-col gap-4 overflow-y-auto overflow-x-hidden px-3 py-3 group-data-[sidebar=closed]/app:gap-2 group-data-[sidebar=closed]/app:px-2">
                    @foreach ($navigation as $group => $items)
                        @php $visibleItems = array_filter($items, fn (array $item): bool => (bool) $item[4]); @endphp
                        @continue($visibleItems === [])
                        <div class="flex flex-col gap-0.5 group-data-[sidebar=closed]/app:border-t group-data-[sidebar=closed]/app:border-pedago-line group-data-[sidebar=closed]/app:pt-2 group-data-[sidebar=closed]/app:first:border-t-0 group-data-[sidebar=closed]/app:first:pt-0">
                            <p class="mb-1 px-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-pedago-muted group-data-[sidebar=closed]/app:sr-only">{{ $group }}</p>
                            @foreach ($visibleItems as [$label, $routeName, $patterns, $icon, $visible, $countKey, $urgent])
                                @php $active = request()->routeIs(...$patterns); @endphp
                                @php $count = $countKey !== null ? ($navCounts[$countKey] ?? 0) : 0; @endphp
                                <a @class([
                                    'relative flex min-h-10 items-center gap-2.5 rounded-md px-2.5 text-sm transition-colors duration-150 lg:min-h-9 group-data-[sidebar=closed]/app:justify-center group-data-[sidebar=closed]/app:px-0',
                                    'bg-pedago-tint font-semibold text-pedago-blue-dark' => $active,
                                    'font-medium text-pedago-ink hover:bg-pedago-canvas' => ! $active,
                                ]) href="{{ route($routeName) }}" title="{{ $label }}{{ $count > 0 ? ' ('.number_format($count).')' : '' }}" @if ($active) aria-current="page" @endif>
                                    <x-ui.icon :name="$icon" @class(['text-pedago-blue' => $active, 'text-pedago-muted' => ! $active]) />
                                    <span class="min-w-0 flex-1 truncate group-data-[sidebar=closed]/app:sr-only">{{ $label }}</span>
                                    @if ($count > 0)
                                        <span @class([
                                            'absolute right-2 top-1.5 hidden size-2 rounded-full group-data-[sidebar=closed]/app:block',
                                            'bg-pedago-red' => $urgent,
                                            'bg-pedago-blue' => ! $urgent,
                                        ]) aria-hidden="true"></span>
                                        <span @class([
                                            'num rounded-full px-1.5 text-xs font-medium group-data-[sidebar=closed]/app:sr-only',
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

                <div class="flex items-center gap-2 border-t border-pedago-line px-3 py-3 group-data-[sidebar=closed]/app:flex-col group-data-[sidebar=closed]/app:px-2">
                    <a class="flex min-w-0 flex-1 items-center gap-2.5 rounded-md p-1.5 transition-colors duration-150 hover:bg-pedago-canvas group-data-[sidebar=closed]/app:flex-none" href="{{ route('profile.edit') }}" aria-label="My profile" title="My profile">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-pedago-tint text-xs font-bold text-pedago-blue-dark" aria-hidden="true">{{ $initials }}</span>
                        <span class="min-w-0 group-data-[sidebar=closed]/app:sr-only">
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

            <div class="flex min-h-screen min-w-0 flex-col pl-16 transition-[padding] duration-200 ease-out lg:group-data-[sidebar=open]/app:pl-64">
                @if ($impersonation ?? null)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 bg-pedago-ink px-4 py-2 text-sm text-white sm:px-6" role="region" aria-label="Developer view">
                        <p class="mr-auto min-w-0">
                            <span class="font-semibold">Developer view</span>
                            <span class="text-white/75">— you are seeing the system as <span class="font-semibold text-white">{{ $currentEmployee?->name }}</span> ({{ $role }}). Actions are real.</span>
                        </p>
                        <form class="flex items-center gap-2" method="POST" action="" id="developer-switch-form">
                            @csrf
                            <label class="sr-only" for="developer-switch">Switch to another employee</label>
                            <select class="h-9 max-w-60 rounded-md border border-white/30 bg-pedago-ink px-2 text-sm text-white" id="developer-switch" required>
                                <option value="">Switch panel…</option>
                                @foreach ($impersonation['targets'] as $targetRole => $targets)
                                    <optgroup label="{{ $targetRole }}">
                                        @foreach ($targets as $target)
                                            <option value="{{ route('developer.view-as', $target) }}" @selected($target->id === $currentEmployee?->id)>{{ $target->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <button class="inline-flex h-9 items-center rounded-md border border-white/30 px-3 text-sm font-semibold transition-colors duration-150 hover:bg-white/10" type="submit">Switch</button>
                        </form>
                        <form method="POST" action="{{ route('developer.return') }}">
                            @csrf
                            <button class="inline-flex h-9 items-center rounded-md bg-white px-3 text-sm font-semibold text-pedago-ink transition-colors duration-150 hover:bg-pedago-tint" type="submit">Return to developer panel</button>
                        </form>
                    </div>
                @endif
                <header class="sticky top-0 z-20 flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-pedago-line bg-white px-4 py-2.5 sm:px-6 lg:min-h-16">
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

                    <details class="group/bell relative" data-bell>
                        <summary class="relative inline-flex size-9 cursor-pointer list-none items-center justify-center rounded-md border border-pedago-line bg-white text-pedago-ink transition-colors duration-150 hover:bg-pedago-canvas [&::-webkit-details-marker]:hidden" aria-label="Notifications, {{ $unreadCount }} unread">
                            <x-ui.icon name="bell" class="size-[18px]" />
                            <span class="num absolute -right-1.5 -top-1.5 min-w-[18px] rounded-full bg-pedago-red px-1 text-center text-[11px] font-semibold leading-[18px] text-white" data-bell-count @if ($unreadCount === 0) hidden @endif>{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                        </summary>
                        <div class="absolute right-0 z-30 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-lg border border-pedago-line bg-white shadow-lg">
                            <div class="flex items-center justify-between border-b border-pedago-line px-3.5 py-2.5">
                                <p class="text-sm font-semibold">Notifications</p>
                                @if ($unreadCount > 0)
                                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="text-xs font-semibold text-pedago-blue hover:underline" type="submit">Mark all read</button></form>
                                @endif
                            </div>
                            @forelse ($recentNotifications as $note)
                                <a class="flex gap-2.5 border-b border-pedago-line px-3.5 py-2.5 last:border-0 hover:bg-pedago-row {{ $note->read_at === null ? 'bg-pedago-tint/40' : '' }}" href="{{ route('notifications.open', $note->id) }}">
                                    <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-pedago-red' => ($note->data['tone'] ?? '') === 'red', 'bg-pedago-amber' => ($note->data['tone'] ?? '') === 'amber', 'bg-pedago-green' => ($note->data['tone'] ?? '') === 'green', 'bg-pedago-blue' => ! in_array($note->data['tone'] ?? '', ['red', 'amber', 'green'], true)]) aria-hidden="true"></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-[13px] {{ $note->read_at === null ? 'font-semibold' : 'font-medium' }}">{{ $note->data['title'] ?? 'Notification' }}</span>
                                        <span class="line-clamp-2 text-xs leading-5 text-pedago-muted">{{ $note->data['body'] ?? '' }}</span>
                                        <span class="text-[11px] text-pedago-muted">{{ $note->created_at->diffForHumans() }}</span>
                                    </span>
                                </a>
                            @empty
                                <p class="px-3.5 py-6 text-center text-sm text-pedago-muted">Nothing yet. Reminders and updates appear here.</p>
                            @endforelse
                            <a class="block border-t border-pedago-line px-3.5 py-2.5 text-center text-xs font-semibold text-pedago-blue hover:bg-pedago-row" href="{{ route('notifications.index') }}">See all notifications</a>
                        </div>
                    </details>
                    <script>
                        (() => {
                            const badge = document.querySelector('[data-bell-count]');
                            if (!badge) return;
                            const refresh = () => fetch(@json(route('notifications.count')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                                .then((response) => (response.ok ? response.json() : null))
                                .then((data) => {
                                    if (!data) return;
                                    badge.textContent = data.unread > 99 ? '99+' : data.unread;
                                    badge.hidden = data.unread === 0;
                                })
                                .catch(() => {});
                            setInterval(refresh, 60000);
                        })();
                    </script>

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
                const root = document.documentElement;
                const backdrop = document.getElementById('app-sidebar-backdrop');
                const toggle = document.getElementById('app-sidebar-toggle');
                const desktop = window.matchMedia('(min-width: 1024px)');
                const isOpen = () => root.dataset.sidebar === 'open';
                const remember = (open) => {
                    try {
                        window.localStorage.setItem('pedago.sidebar', open ? 'open' : 'closed');
                    } catch (error) {
                        // Storage may be blocked; the rail still works for this page.
                    }
                };
                const setOpen = (open, persist) => {
                    root.dataset.sidebar = open ? 'open' : 'closed';
                    toggle.setAttribute('aria-expanded', String(open));
                    toggle.setAttribute('aria-label', open ? 'Collapse navigation to icons' : 'Expand navigation');
                    toggle.title = open ? 'Collapse navigation' : 'Expand navigation';
                    document.body.classList.toggle('overflow-hidden', open && !desktop.matches);
                    if (persist && desktop.matches) {
                        remember(open);
                    }
                };

                setOpen(isOpen(), false);
                toggle.addEventListener('click', () => setOpen(!isOpen(), true));
                backdrop.addEventListener('click', () => setOpen(false, false));
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && isOpen() && !desktop.matches) {
                        setOpen(false, false);
                        toggle.focus();
                    }
                });
                desktop.addEventListener('change', () => {
                    let saved = null;
                    try {
                        saved = window.localStorage.getItem('pedago.sidebar');
                    } catch (error) {
                        saved = null;
                    }
                    setOpen(desktop.matches && saved !== 'closed', false);
                });

                const switcher = document.getElementById('developer-switch-form');
                if (switcher) {
                    switcher.addEventListener('submit', (event) => {
                        const target = document.getElementById('developer-switch').value;
                        if (!target) {
                            event.preventDefault();
                            return;
                        }
                        switcher.action = target;
                    });
                }

                const placeTip = (host) => {
                    const tip = host.querySelector('[role="tooltip"]');
                    const anchor = host.querySelector('button').getBoundingClientRect();
                    const left = Math.min(Math.max(8, anchor.left + anchor.width / 2 - tip.offsetWidth / 2), window.innerWidth - tip.offsetWidth - 8);
                    let top = anchor.bottom + 6;
                    if (top + tip.offsetHeight > window.innerHeight - 8) {
                        top = Math.max(8, anchor.top - tip.offsetHeight - 6);
                    }
                    tip.style.left = left + 'px';
                    tip.style.top = top + 'px';
                    host.dataset.shown = 'true';
                };
                const hideTip = (host) => {
                    delete host.dataset.shown;
                };
                document.addEventListener('mouseover', (event) => {
                    const host = event.target.closest('[data-info]');
                    if (host) {
                        placeTip(host);
                    }
                });
                document.addEventListener('mouseout', (event) => {
                    const host = event.target.closest('[data-info]');
                    if (host && !host.contains(event.relatedTarget) && !host.matches(':focus-within')) {
                        hideTip(host);
                    }
                });
                document.addEventListener('focusin', (event) => {
                    const host = event.target.closest('[data-info]');
                    if (host) {
                        placeTip(host);
                    }
                });
                document.addEventListener('focusout', (event) => {
                    const host = event.target.closest('[data-info]');
                    if (host) {
                        hideTip(host);
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        document.querySelectorAll('[data-info][data-shown]').forEach(hideTip);
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
        @yield('content')
    @endauth
    @stack('scripts')
</body>
</html>
