@extends('layouts.app', ['title' => 'My employee profile'])

@section('page-title', $isAdminEdit ? 'Edit employee profile' : 'My profile')
@section('page-description', 'Manage your personal information and employment details.')
@section('page-actions')
    <span @class([
        'rounded-full px-3 py-1.5 text-xs font-semibold',
        'bg-pedago-green-tint text-pedago-green' => $isProfileComplete,
        'bg-pedago-amber-tint text-pedago-amber' => ! $isProfileComplete,
    ])>Profile {{ $isProfileComplete ? 'complete' : 'incomplete' }}</span>
    @if ($pendingRequest)
        <span class="hidden rounded-full bg-pedago-amber-tint px-3 py-1.5 text-xs font-semibold text-pedago-amber sm:inline-flex">Approval pending</span>
    @endif
@endsection

@section('content')
    @if ($pendingRequest)
        <section class="rounded-xl border border-pedago-amber/20 bg-pedago-amber-tint p-5">
            <h2 class="font-semibold text-pedago-amber">Pending employment changes</h2>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                @foreach ($pendingRequest->requested_changes as $field => $value)
                    <div class="flex flex-col gap-1">
                        <dt class="font-medium text-pedago-amber">{{ str($field)->replace('_', ' ')->title() }}</dt>
                        <dd class="text-pedago-ink">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($pendingRequest->reason)
                <p class="mt-3 text-sm text-pedago-ink">Reason: {{ $pendingRequest->reason }}</p>
            @endif
        </section>
    @endif

    <form class="flex flex-col gap-6" method="POST" action="{{ $isAdminEdit ? route('employees.update', $employee) : route('profile.update') }}">
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-pedago-line bg-white p-6 shadow-sm transition-shadow hover:shadow-md">
            <div class="mb-5 flex flex-col gap-2">
                <h2 class="text-lg font-semibold">Employment information</h2>
                <p class="text-sm text-pedago-muted">Changes to these fields require review by a higher role. Admin changes take effect immediately.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-2 text-sm font-medium">Full name
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="name" value="{{ old('name', $employee->name) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Office ID
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="office_id" value="{{ old('office_id', $employee->office_id) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Designation
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="designation" value="{{ old('designation', $employee->designation) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Joining date
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="joining_date" type="date" value="{{ old('joining_date', $employee->joining_date?->format('Y-m-d')) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Login phone number
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="phone_number" type="tel" value="{{ old('phone_number', $employee->user?->phone_number) }}" required>
                </label>
                @if ($isAdminEdit || $employee->isAdmin())
                    <label class="flex flex-col gap-2 text-sm font-medium">Role
                        <select class="rounded-md border border-pedago-line bg-white px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="role" required>
                            @foreach (\App\Models\Employee::ROLES as $role)
                                <option value="{{ $role }}" @selected(old('role', $employee->role) === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-2 text-sm font-medium">Employment status
                        <select class="rounded-md border border-pedago-line bg-white px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="status" required>
                            @foreach (['Active', 'Inactive', 'Suspended'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $employee->status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <p class="self-center text-sm text-pedago-muted">Current role: {{ $employee->role }} · {{ $employee->status }}</p>
                @endif
            </div>
            <label class="mt-4 flex flex-col gap-2 text-sm font-medium">Reason for employment changes
                <textarea class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="reason" rows="3">{{ old('reason') }}</textarea>
            </label>
        </section>

        <section class="rounded-xl border border-pedago-line bg-white p-6 shadow-sm transition-shadow hover:shadow-md">
            <div class="mb-5 flex flex-col gap-2">
                <h2 class="text-lg font-semibold">Personal details</h2>
                <p class="text-sm text-pedago-muted">Update these profile details directly. Fields marked required are needed to finish onboarding.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-2 text-sm font-medium">Personal email
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="email" type="email" value="{{ old('email', $employee->details?->email) }}">
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Date of birth <span class="text-pedago-red">Required</span>
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="dob" type="date" value="{{ old('dob', $employee->details?->dob?->format('Y-m-d')) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Secondary contact number
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="secondary_contact_number" type="tel" value="{{ old('secondary_contact_number', $employee->details?->secondary_contact_number) }}">
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Emergency contact name <span class="text-pedago-red">Required</span>
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="emergency_contact_name" value="{{ old('emergency_contact_name', $employee->details?->emergency_contact_name) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Emergency contact number <span class="text-pedago-red">Required</span>
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="emergency_contact_number" type="tel" value="{{ old('emergency_contact_number', $employee->details?->emergency_contact_number) }}" required>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium">Blood group <span class="text-pedago-red">Required</span>
                    <select class="rounded-md border border-pedago-line bg-white px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="blood_group" required>
                        <option value="">Select a blood group</option>
                        @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bloodGroup)
                            <option value="{{ $bloodGroup }}" @selected(old('blood_group', $employee->details?->blood_group) === $bloodGroup)>{{ $bloodGroup }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium sm:col-span-2">National ID number
                    <input class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="nid_number" value="{{ old('nid_number') }}" autocomplete="off">
                    <span class="font-normal text-pedago-muted">Stored encrypted. Leave empty unless adding or replacing it.</span>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium sm:col-span-2">Present address <span class="text-pedago-red">Required</span>
                    <textarea class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="present_address" rows="3" required>{{ old('present_address', $employee->details?->present_address) }}</textarea>
                </label>
                <label class="flex flex-col gap-2 text-sm font-medium sm:col-span-2">Permanent address <span class="text-pedago-red">Required</span>
                    <textarea class="rounded-md border border-pedago-line px-3 py-2 font-normal transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" name="permanent_address" rows="3" required>{{ old('permanent_address', $employee->details?->permanent_address) }}</textarea>
                </label>
            </div>
        </section>

        <button class="self-start rounded-md bg-pedago-blue px-5 py-3 font-semibold text-white transition hover:bg-pedago-blue-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pedago-blue" type="submit">Save profile</button>
    </form>
@endsection
