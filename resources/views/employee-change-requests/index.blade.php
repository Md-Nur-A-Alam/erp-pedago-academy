@extends('layouts.app', ['title' => 'Employee change approvals'])

@section('page-title', 'Employee approvals')
@section('page-description', 'Review essential profile changes submitted by employees below your authority level.')

@section('content')
    @forelse ($changeRequests as $changeRequest)
        <article class="flex flex-col gap-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold">{{ $changeRequest->employee->name }}</h2>
                    <p class="text-sm text-slate-600">{{ $changeRequest->employee->office_id }} · {{ $changeRequest->employee->role }}</p>
                </div>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-900">Pending</span>
            </div>

            <dl class="grid gap-3 sm:grid-cols-2">
                @foreach ($changeRequest->requested_changes as $field => $value)
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ str($field)->replace('_', ' ')->title() }}</dt>
                        <dd class="mt-1 break-words text-sm">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($changeRequest->reason)
                <p class="text-sm"><span class="font-semibold">Reason:</span> {{ $changeRequest->reason }}</p>
            @endif

            <form class="flex flex-col gap-4 border-t border-slate-200 pt-4 sm:flex-row sm:items-end" method="POST" action="{{ route('employee-change-requests.update', $changeRequest) }}">
                @csrf
                @method('PUT')
                <label class="flex flex-1 flex-col gap-2 text-sm font-medium">Review note
                    <textarea class="rounded-lg border border-slate-300 px-3 py-2 font-normal" name="review_note" rows="2"></textarea>
                </label>
                <div class="flex gap-3">
                    <button class="rounded-lg bg-emerald-700 px-4 py-2.5 font-semibold text-white hover:bg-emerald-800" name="decision" value="Approved" type="submit">Approve</button>
                    <button class="rounded-lg bg-rose-700 px-4 py-2.5 font-semibold text-white hover:bg-rose-800" name="decision" value="Rejected" type="submit">Reject</button>
                </div>
            </form>
        </article>
    @empty
        <section class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">No pending employee changes are available for your review.</section>
    @endforelse
@endsection
