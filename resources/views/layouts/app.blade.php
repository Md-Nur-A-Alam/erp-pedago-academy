<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Pedago Academy ERP' }}</title>
    @if (($title ?? null) === 'Sign in')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,600;0,700;1,400&amp;display=swap" rel="stylesheet">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-pedago-canvas font-sans text-pedago-ink antialiased">
    @auth
        @php($currentEmployee = auth()->user()->employee)
        <div class="min-h-screen lg:flex">
            <aside class="hidden w-60 shrink-0 flex-col gap-5 border-r border-pedago-line bg-white px-4 py-6 lg:sticky lg:top-0 lg:flex lg:h-screen" aria-label="Primary navigation">
                <a class="flex items-center justify-center rounded-md bg-white p-2" href="{{ route('dashboard') }}" aria-label="Pedago Academy dashboard">
                    <img class="h-auto w-full max-w-48" src="{{ asset('user_defined_assets/img/horizontal%20logo%201%20on%20whtie%20bg.png') }}" alt="Pedago Academy">
                </a>

                <div class="flex flex-col gap-1 border-b border-pedago-line px-2 pb-5">
                    <p class="truncate text-sm font-bold text-pedago-ink" title="{{ $currentEmployee?->name }}">{{ $currentEmployee?->name }}</p>
                    <p class="truncate text-xs text-pedago-muted" title="{{ $currentEmployee?->designation }} · {{ $currentEmployee?->role }}">{{ $currentEmployee?->designation }} · {{ $currentEmployee?->role }}</p>
                </div>

                <nav class="flex flex-col gap-7">
                    <div class="flex flex-col gap-1">
                        <p class="px-2 text-xs font-semibold uppercase tracking-wider text-pedago-muted">Overview</p>
                        <a @class([
                            'rounded-md px-3 py-2 text-sm font-semibold',
                            'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('dashboard'),
                            'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('dashboard'),
                        ]) href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                            Dashboard
                        </a>
                    </div>

                    @if ($currentEmployee?->isAdmin() || $currentEmployee?->role === 'MarketingExecutive')
                        <div class="flex flex-col gap-1">
                            <p class="px-2 text-xs font-semibold uppercase tracking-wider text-pedago-muted">Sales</p>
                            <a @class([
                                'rounded-md px-3 py-2 text-sm font-semibold',
                                'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('leads.*'),
                                'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('leads.*'),
                            ]) href="{{ route('leads.upload') }}" @if (request()->routeIs('leads.*')) aria-current="page" @endif>
                                Upload leads
                            </a>
                            <a @class([
                                'rounded-md px-3 py-2 text-sm font-semibold',
                                'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('campaigns.*'),
                                'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('campaigns.*'),
                            ]) href="{{ route('campaigns.index') }}" @if (request()->routeIs('campaigns.*')) aria-current="page" @endif>
                                Campaigns
                            </a>
                            @if ($currentEmployee?->isAdmin())
                                <a @class([
                                    'rounded-md px-3 py-2 text-sm font-semibold transition-colors',
                                    'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('duplicate-lead-reviews.*'),
                                    'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('duplicate-lead-reviews.*'),
                                ]) href="{{ route('duplicate-lead-reviews.index') }}" @if (request()->routeIs('duplicate-lead-reviews.*')) aria-current="page" @endif>
                                    Duplicate review
                                </a>
                            @endif
                        </div>
                    @endif

                    @if ($currentEmployee?->isAdmin() || $currentEmployee?->lowerAuthorityRoles() !== [])
                        <div class="flex flex-col gap-1">
                            <p class="px-2 text-xs font-semibold uppercase tracking-wider text-pedago-muted">People</p>
                            @if ($currentEmployee?->isAdmin() || in_array($currentEmployee?->role, ['Supervisor', 'Team Leader'], true))
                                <a @class([
                                    'rounded-md px-3 py-2 text-sm font-semibold transition-colors',
                                    'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('teams.*'),
                                    'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('teams.*'),
                                ]) href="{{ route('teams.index') }}" @if (request()->routeIs('teams.*')) aria-current="page" @endif>
                                    Teams
                                </a>
                            @endif
                            @if ($currentEmployee?->isAdmin())
                                <a @class([
                                    'rounded-md px-3 py-2 text-sm font-semibold transition-colors',
                                    'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('employees.*'),
                                    'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('employees.*'),
                                ]) href="{{ route('employees.index') }}" @if (request()->routeIs('employees.*')) aria-current="page" @endif>
                                    Employees
                                </a>
                                <a @class([
                                    'rounded-md px-3 py-2 text-sm font-semibold transition-colors',
                                    'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('courses.*'),
                                    'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('courses.*'),
                                ]) href="{{ route('courses.index') }}" @if (request()->routeIs('courses.*')) aria-current="page" @endif>
                                    Courses
                                </a>
                                <a @class([
                                    'rounded-md px-3 py-2 text-sm font-semibold transition-colors',
                                    'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('subjects.*', 'course-topics.*'),
                                    'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('subjects.*', 'course-topics.*'),
                                ]) href="{{ route('subjects.index') }}" @if (request()->routeIs('subjects.*', 'course-topics.*')) aria-current="page" @endif>
                                    Subjects
                                </a>
                                <a @class([
                                    'rounded-md px-3 py-2 text-sm font-semibold transition-colors',
                                    'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('course-batches.*'),
                                    'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('course-batches.*'),
                                ]) href="{{ route('course-batches.index') }}" @if (request()->routeIs('course-batches.*')) aria-current="page" @endif>
                                    Course batches
                                </a>
                                <a @class([
                                    'rounded-md px-3 py-2 text-sm font-semibold transition-colors',
                                    'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('settings.*'),
                                    'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('settings.*'),
                                ]) href="{{ route('settings.index') }}" @if (request()->routeIs('settings.*')) aria-current="page" @endif>
                                    Settings
                                </a>
                            @endif
                            @if ($currentEmployee?->lowerAuthorityRoles() !== [])
                                <a @class([
                                    'rounded-md px-3 py-2 text-sm font-semibold',
                                    'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('employee-change-requests.*'),
                                    'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('employee-change-requests.*'),
                                ]) href="{{ route('employee-change-requests.index') }}" @if (request()->routeIs('employee-change-requests.*')) aria-current="page" @endif>
                                    Approvals
                                </a>
                            @endif
                        </div>
                    @endif
                </nav>

                <div class="mt-auto flex items-center justify-between gap-3 border-t border-pedago-line pt-4">
                    <a class="inline-flex size-10 items-center justify-center rounded-lg border border-pedago-line text-pedago-blue-dark transition hover:border-pedago-blue hover:bg-pedago-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" href="{{ route('profile.edit') }}" aria-label="My profile" title="My profile">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0v.75h-15v-.75Z" />
                        </svg>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-md px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50" type="submit">Log out</button>
                    </form>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
                <header class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-b border-pedago-line bg-white px-4 py-3 sm:px-6 lg:min-h-16 lg:px-8">
                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-bold tracking-tight text-pedago-ink sm:text-xl">@yield('page-title', $title ?? 'Pedago Academy ERP')</h1>
                        @hasSection('page-description')
                            <p class="mt-0.5 text-xs text-pedago-muted sm:text-sm">@yield('page-description')</p>
                        @endif
                    </div>
                    <div class="flex max-w-full flex-wrap items-center justify-end gap-2">
                        @yield('page-actions')
                        @if (request()->routeIs('dashboard') && ($currentEmployee?->isAdmin() || $currentEmployee?->role === 'MarketingExecutive'))
                            <a class="inline-flex items-center justify-center rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" href="{{ route('leads.upload') }}">Upload leads</a>
                        @endif
                        <form class="lg:hidden" method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-md px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50" type="submit">Log out</button>
                        </form>
                    </div>
                </header>

                <nav class="flex gap-2 overflow-x-auto border-b border-pedago-line bg-white px-4 py-2 lg:hidden" aria-label="Mobile navigation">
                    <a @class([
                        'shrink-0 rounded-md px-3 py-2 text-sm font-semibold transition-colors',
                        'bg-pedago-tint text-pedago-blue-dark' => request()->routeIs('dashboard'),
                        'text-pedago-ink hover:bg-pedago-canvas' => ! request()->routeIs('dashboard'),
                    ]) href="{{ route('dashboard') }}">Dashboard</a>
                    @if ($currentEmployee?->isAdmin() || $currentEmployee?->role === 'MarketingExecutive')
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('leads.upload') }}">Upload leads</a>
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('campaigns.index') }}">Campaigns</a>
                    @endif
                    @if ($currentEmployee?->isAdmin() || in_array($currentEmployee?->role, ['Supervisor', 'Team Leader'], true))
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('teams.index') }}">Teams</a>
                    @endif
                    @if ($currentEmployee?->isAdmin())
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('courses.index') }}">Courses</a>
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('subjects.index') }}">Subjects</a>
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('course-batches.index') }}">Batches</a>
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('duplicate-lead-reviews.index') }}">Duplicate review</a>
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('employees.index') }}">Employees</a>
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('settings.index') }}">Settings</a>
                    @endif
                    @if ($currentEmployee?->lowerAuthorityRoles() !== [])
                        <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('employee-change-requests.index') }}">Approvals</a>
                    @endif
                    <a class="shrink-0 rounded-md px-3 py-2 text-sm font-semibold text-pedago-ink transition-colors hover:bg-pedago-canvas" href="{{ route('profile.edit') }}">My profile</a>
                </nav>

                <main class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    @if (session('status'))
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                            <ul class="list-inside list-disc space-y-1">
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
    @else
        <main class="flex min-h-screen w-full items-start justify-center bg-[#F8F8F8] px-4 py-4 min-[861px]:items-center min-[861px]:px-0 min-[861px]:py-0">
            @yield('content')
        </main>
    @endauth
    @stack('scripts')
</body>
</html>
