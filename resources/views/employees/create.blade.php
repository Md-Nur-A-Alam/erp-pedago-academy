@extends('layouts.app', ['title' => 'Create employee'])

@section('page-title', 'Add employee')
@section('page-description', 'Create an employee account with essential office details and first-login credentials.')
@section('page-actions')
    <a class="rounded-md border border-pedago-line px-3 py-2 text-sm font-semibold text-pedago-blue-dark transition hover:bg-pedago-tint" href="{{ route('employees.index') }}">Employees</a>
@endsection

@section('content')
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-7">
            <p class="mb-5 text-sm leading-6 text-pedago-muted">The employee signs in with their phone number and temporary password. They must change the password and complete their profile after signing in.</p>
            <form class="grid gap-5 sm:grid-cols-2" method="POST" action="{{ route('employees.store') }}">
                @csrf
                <label class="flex flex-col gap-2 text-sm font-semibold">Full name
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="name" value="{{ old('name') }}" autocomplete="name" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold">Office ID
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="office_id" value="{{ old('office_id') }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold">Designation
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="designation" value="{{ old('designation') }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold">Joining date
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="joining_date" type="date" value="{{ old('joining_date') }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold">Role
                    <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="role" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(old('role', 'Sales Executive') === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold">Login phone number
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="phone_number" type="tel" value="{{ old('phone_number') }}" autocomplete="tel" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold">Temporary password
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="password" type="password" minlength="6" maxlength="12" autocomplete="new-password" required>
                    <span class="font-normal text-pedago-muted">Use 6 to 12 characters. Share it securely.</span>
                </label>
                <label class="flex flex-col gap-2 text-sm font-semibold">Confirm temporary password
                    <input class="rounded-md border border-pedago-line px-3 py-2.5 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="password_confirmation" type="password" minlength="6" maxlength="12" autocomplete="new-password" required>
                </label>
                <div class="flex flex-wrap gap-3 border-t border-pedago-line pt-5 sm:col-span-2">
                    <button class="rounded-md bg-pedago-blue px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">Create account</button>
                    <a class="rounded-md border border-pedago-line px-5 py-2.5 text-sm font-semibold transition hover:bg-pedago-canvas" href="{{ route('employees.index') }}">Cancel</a>
                </div>
            </form>
        </section>
    </div>
@endsection
