@extends('layouts.app', ['title' => $employee->isAdmin() ? 'Documentation' : 'My guide'])

@section('page-title', $employee->isAdmin() ? 'Documentation' : 'My guide')
@section('page-description', $employee->isAdmin() ? 'The rules of the whole system, and the training guide each role sees.' : 'How to use the system in your role: what you can do, where to click, and the rules that apply to you.')

@section('content')
    @if ($employee->isAdmin())
        <nav class="flex gap-1 overflow-x-auto border-b border-pedago-line" aria-label="Documentation sections">
            @foreach (['rules' => 'System rules'] + $roleLabels as $key => $label)
                <a @class([
                    '-mb-px inline-flex min-h-11 shrink-0 items-center border-b-2 px-3 text-sm font-semibold transition-colors duration-150',
                    'border-pedago-blue text-pedago-blue-dark' => $chosen === $key,
                    'border-transparent text-pedago-muted hover:border-pedago-line hover:text-pedago-ink' => $chosen !== $key,
                ]) href="{{ route('guide.index', ['view' => $key]) }}" @if ($chosen === $key) aria-current="page" @endif>{{ $key === 'rules' ? $label : $label.' guide' }}</a>
            @endforeach
        </nav>
    @endif

    @if ($chosen === 'rules')
        <div class="grid gap-4 lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start">
            <nav class="rounded-lg border border-pedago-line bg-white p-3 lg:sticky lg:top-20" aria-label="On this page">
                <p class="mb-1 px-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-pedago-muted">On this page</p>
                <ol class="flex flex-col">
                    <li><a class="block rounded-md px-2 py-1.5 text-[13px] font-medium transition-colors duration-150 hover:bg-pedago-canvas" href="#permissions">Who can do what</a></li>
                    @foreach ($chapters as $chapter)
                        <li><a class="block rounded-md px-2 py-1.5 text-[13px] font-medium transition-colors duration-150 hover:bg-pedago-canvas" href="#{{ $chapter['id'] }}">{{ $chapter['title'] }}</a></li>
                    @endforeach
                    <li><a class="block rounded-md px-2 py-1.5 text-[13px] font-medium transition-colors duration-150 hover:bg-pedago-canvas" href="#settings">Current settings</a></li>
                </ol>
            </nav>

            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.card title="Who can do what" id="permissions" class="scroll-mt-20" flush>
                    @php
                        $columns = ['Admin', 'Supervisor', 'Team leader', 'Sales exec.', 'Marketer', 'Accounts'];
                        $matrix = [
                            'See leads' => ['All', 'Own teams', 'Own team', 'Own leads', 'All', '—'],
                            'Receive and call leads' => ['—', '—', 'Own leads', 'Own leads', '—', '—'],
                            'Record call feedback' => ['—', '—', 'Own leads', 'Own leads', '—', '—'],
                            'Upload leads, manage campaigns' => ['Yes', '—', '—', '—', 'Yes', '—'],
                            'Central queue, run distribution' => ['Yes', '—', '—', '—', '—', '—'],
                            'Duplicate review' => ['Yes', '—', '—', '—', '—', '—'],
                            'See follow-ups' => ['All', 'Own teams', 'Own team and own', 'Own', '—', '—'],
                            'Apply for leave' => ['Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes'],
                            'Decide leave' => ['Any', 'Own teams', 'Own executives', '—', '—', '—'],
                            'See KPIs' => ['Everyone', 'Own teams', 'Own team and own', 'Own', 'Marketing', 'Accounts'],
                            'Reassign a follow-up' => ['Yes', '—', '—', '—', '—', '—'],
                            'Request a transfer' => ['—', '—', 'Own leads', 'Own leads', '—', '—'],
                            'Decide a transfer' => ['Any', '—', 'Own team’s', '—', '—', '—'],
                            'Verify or reject payments' => ['Yes', '—', '—', '—', '—', 'Yes'],
                            'Change a team' => ['Any', 'Own teams', 'Own team', '—', '—', '—'],
                            'Create or delete a team' => ['Yes', '—', '—', '—', '—', '—'],
                            'Employees, courses, settings' => ['Yes', '—', '—', '—', '—', '—'],
                            'Reports' => ['Yes', '—', '—', '—', '—', '—'],
                            'Read or reset staff passwords' => ['Yes, logged', '—', '—', '—', '—', '—'],
                        ];
                    @endphp
                    <p class="px-4 pb-3 text-xs text-pedago-muted sm:px-5">Developers hold every administrator permission, plus the developer panel, and can open any employee’s panel for testing.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] border-collapse text-left">
                            <thead>
                                <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                    <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Action</th>
                                    @foreach ($columns as $column)
                                        <th class="px-2 py-2 font-semibold" scope="col">{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="text-[13px]">
                                @foreach ($matrix as $action => $cells)
                                    <tr class="border-b border-pedago-line last:border-0">
                                        <th class="px-4 py-2 font-medium sm:pl-5" scope="row">{{ $action }}</th>
                                        @foreach ($cells as $cell)
                                            <td @class(['px-2 py-2', 'text-pedago-muted' => $cell === '—', 'font-semibold text-pedago-green' => $cell !== '—'])>{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>

                @foreach ($chapters as $chapter)
                    <x-ui.card :title="$chapter['title']" :id="$chapter['id']" class="scroll-mt-20">
                        <p class="max-w-3xl text-sm leading-6">{{ $chapter['intro'] }}</p>
                        <ul class="mt-2 flex max-w-3xl list-disc flex-col gap-1.5 pl-5 text-sm leading-6 marker:text-pedago-blue-soft">
                            @foreach ($chapter['points'] as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endforeach

                <x-ui.card title="Current settings" id="settings" class="scroll-mt-20" flush>
                    <x-slot:meta><a class="font-semibold text-pedago-blue-dark underline-offset-2 hover:underline" href="{{ route('settings.index') }}">Change settings</a></x-slot:meta>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] border-collapse text-left">
                            <thead>
                                <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                    <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Setting</th>
                                    <th class="px-2 py-2 font-semibold" scope="col">Now</th>
                                    <th class="px-4 py-2 font-semibold sm:pr-5" scope="col">What it does</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13px]">
                                @foreach ($settings as $setting)
                                    <tr class="border-b border-pedago-line align-top last:border-0">
                                        <th class="whitespace-nowrap px-4 py-2 font-medium sm:pl-5" scope="row">{{ $setting['label'] }}</th>
                                        <td class="num whitespace-nowrap px-2 py-2 font-semibold">{{ $setting['type'] === 'boolean' ? ($setting['value'] === '1' ? 'On' : 'Off') : $setting['value'].' '.($setting['unit'] ?? '') }}</td>
                                        <td class="px-4 py-2 leading-5 text-pedago-muted sm:pr-5">{{ $setting['description'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>

                <p class="text-xs text-pedago-muted">The same rules as a printable web page with a distribution calculator: <a class="font-semibold text-pedago-blue-dark underline underline-offset-2" href="{{ asset('user_defined_assets/claude analysis/web/index.html') }}" target="_blank" rel="noopener">open the rule book</a>. The full analysis pack is kept with the project files under <span class="num">public/user_defined_assets/claude analysis</span>.</p>
            </div>
        </div>
    @else
        <x-ui.card>
            <p class="text-xs font-semibold uppercase tracking-wider text-pedago-blue">{{ $roleLabels[$chosen] }} guide</p>
            <p class="mt-1 max-w-3xl text-base font-semibold leading-7">{{ $manual['summary'] }}</p>
        </x-ui.card>

        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.card title="What you can do">
                <ul class="flex flex-col gap-2 text-sm leading-6">
                    @foreach ($manual['can'] as $line)
                        <li class="flex gap-2"><x-ui.icon name="check-circle" class="mt-1 size-4 text-pedago-green" /><span>{{ $line }}</span></li>
                    @endforeach
                </ul>
            </x-ui.card>
            <x-ui.card title="What you cannot do">
                <ul class="flex flex-col gap-2 text-sm leading-6">
                    @foreach ($manual['cannot'] as $line)
                        <li class="flex gap-2"><x-ui.icon name="x" class="mt-1 size-4 text-pedago-red" /><span>{{ $line }}</span></li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>

        <x-ui.card title="A tour of your screens">
            <ol class="flex flex-col gap-3">
                @foreach ($manual['tour'] as $step => [$screen, $purpose, $routeName])
                    <li class="flex gap-3">
                        <span class="num flex size-7 shrink-0 items-center justify-center rounded-full bg-pedago-tint text-xs font-semibold text-pedago-blue-dark" aria-hidden="true">{{ $step + 1 }}</span>
                        <div class="min-w-0 text-sm leading-6">
                            <p class="font-semibold">
                                @if ($routeName !== null && $chosen === $employee->role)
                                    <a class="text-pedago-blue-dark underline underline-offset-2" href="{{ route($routeName) }}">{{ $screen }}</a>
                                @else
                                    {{ $screen }}
                                @endif
                            </p>
                            <p class="max-w-3xl text-pedago-muted">{{ $purpose }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.card title="Rules that apply to you">
                <ul class="flex list-disc flex-col gap-2 pl-5 text-sm leading-6 marker:text-pedago-blue-soft">
                    @foreach ($manual['rules'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </x-ui.card>
            <x-ui.card title="Habits that get results">
                <ul class="flex list-disc flex-col gap-2 pl-5 text-sm leading-6 marker:text-pedago-blue-soft">
                    @foreach ($manual['habits'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    @endif
@endsection
