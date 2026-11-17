@extends('layouts.app', ['title' => 'Staff passwords'])

@section('page-title', 'Staff passwords')
@section('page-description', 'Read or reset an employee’s password. Every use is recorded with your name and reason.')

@section('content')
    @if (! $moduleEnabled)
        <div class="flex items-start gap-2.5 rounded-lg border border-pedago-amber/30 bg-pedago-amber-tint px-4 py-3 text-sm text-pedago-amber" role="status">
            <x-ui.icon name="alert" class="mt-0.5 size-4" />
            <span>Readable passwords are switched off because <code class="num">STAFF_PASSWORD_KEY</code> is not set on the server. You can still reset passwords.</span>
        </div>
    @endif

    @if ($shown)
        <section class="rounded-lg border border-pedago-blue bg-pedago-tint px-4 py-4 sm:px-5" aria-live="polite" aria-label="Password result">
            <p class="text-sm font-semibold text-pedago-blue-dark">{{ ($shown['temporary'] ?? false) ? 'New temporary password for' : 'Password of' }} {{ $shown['employee']->name }} ({{ $shown['employee']->office_id }})</p>
            <p class="num mt-2 inline-block select-all rounded-md border border-pedago-line bg-white px-3 py-2 text-lg font-semibold tracking-wide" id="shown-password">{{ $shown['password'] }}</p>
            <p class="mt-2 text-xs leading-5 text-pedago-blue-dark">
                {{ ($shown['temporary'] ?? false) ? 'Give it to the employee privately. They must choose a new password at their next sign-in.' : 'Sign-in phone: '.$shown['employee']->user?->phone_number.'.' }}
                This is shown once: it disappears when you leave or reload this page.
            </p>
        </section>
    @endif

    <x-ui.card title="Employees" flush>
        <x-slot:meta>{{ number_format($employees->total()) }} accounts</x-slot:meta>

        <form class="flex flex-wrap items-end gap-2 border-t border-pedago-line px-4 py-3 sm:px-5" method="GET" action="{{ route('staff-passwords.index') }}" role="search">
            <div class="w-full sm:w-64">
                <label class="mb-1 block text-xs font-medium text-pedago-muted" for="staff-search">Search by name or office ID</label>
                <input class="h-9 w-full rounded-md border border-pedago-line px-2.5 text-sm" id="staff-search" name="search" type="search" value="{{ $search }}">
            </div>
            <x-ui.button type="submit" variant="secondary">Search</x-ui.button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse text-left">
                <thead>
                    <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                        <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">Employee</th>
                        <th class="px-2 py-2 font-semibold" scope="col">Role</th>
                        <th class="px-2 py-2 font-semibold" scope="col">Sign-in phone</th>
                        <th class="px-2 py-2 font-semibold" scope="col">Password</th>
                        <th class="px-4 py-2 sm:pr-5" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="text-[13px]">
                    @forelse ($employees as $employee)
                        <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                            <th class="px-4 py-2 font-medium sm:pl-5" scope="row">
                                <span class="block max-w-56 truncate">{{ $employee->name }}</span>
                                <span class="num block text-xs font-normal text-pedago-muted">{{ $employee->office_id }}{{ $employee->status !== 'Active' ? ' · '.$employee->status : '' }}</span>
                            </th>
                            <td class="whitespace-nowrap px-2 py-2">{{ $employee->role }}</td>
                            <td class="num whitespace-nowrap px-2 py-2 text-xs">{{ $employee->user?->phone_number ?? '—' }}</td>
                            <td class="px-2 py-2">
                                @if ($employee->user === null)
                                    <x-ui.pill tone="slate">No account</x-ui.pill>
                                @elseif ($employee->password_readable)
                                    <x-ui.pill tone="green">Readable</x-ui.pill>
                                @else
                                    <x-ui.pill tone="amber">After next sign-in</x-ui.pill>
                                @endif
                            </td>
                            <td class="px-4 py-2 sm:pr-5">
                                @if ($employee->user !== null)
                                    <div class="flex justify-end gap-2">
                                        @if ($employee->password_readable)
                                            <x-ui.button size="sm" data-dialog-open="password-dialog" data-dialog-action="{{ route('staff-passwords.reveal', $employee) }}" data-dialog-subject="Show the password of {{ $employee->name }} ({{ $employee->office_id }})">Show</x-ui.button>
                                        @endif
                                        <x-ui.button variant="secondary" size="sm" data-dialog-open="password-dialog" data-dialog-action="{{ route('staff-passwords.reset', $employee) }}" data-dialog-subject="Reset the password of {{ $employee->name }} ({{ $employee->office_id }}) to a new temporary one">Reset</x-ui.button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty title="No employee matches this search" icon="user" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($employees->hasPages())
            <div class="border-t border-pedago-line px-4 py-3 sm:px-5">{{ $employees->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.card title="Access log" flush>
        <x-slot:meta>Last {{ $logs->count() }} entries · cannot be edited</x-slot:meta>
        @if ($logs->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="No password has been shown or reset yet" icon="lock" />
        @else
            <ul class="divide-y divide-pedago-line border-t border-pedago-line">
                @foreach ($logs as $log)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-2.5 text-[13px] sm:px-5">
                        <p><span class="font-semibold">{{ $log->admin?->name }}</span> {{ $log->action === 'Reset' ? 'reset' : 'viewed' }} the password of <span class="font-semibold">{{ $log->target?->name }}</span> <span class="text-pedago-muted">— {{ $log->reason }}</span></p>
                        <p class="num text-xs text-pedago-muted">{{ $log->created_at?->format('M j, Y g:i A') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.dialog id="password-dialog" title="Confirm it is you">
        <form class="flex flex-col gap-4" method="POST" action="" autocomplete="off">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-semibold" for="password-reason">Reason <span class="text-pedago-red" aria-hidden="true">*</span></label>
                <input class="h-10 w-full rounded-md border border-pedago-line px-3 text-sm" id="password-reason" name="reason" type="text" minlength="5" maxlength="255" required aria-describedby="password-reason-help">
                <p class="mt-1 text-xs text-pedago-muted" id="password-reason-help">For example: “Employee is on leave and a customer needs an urgent update.” The reason is kept in the access log.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold" for="password-confirm">Your own password <span class="text-pedago-red" aria-hidden="true">*</span></label>
                <input class="h-10 w-full rounded-md border border-pedago-line px-3 text-sm" id="password-confirm" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" data-dialog-close>Cancel</x-ui.button>
                <x-ui.button type="submit">Continue</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endsection
