@extends('layouts.app', ['title' => 'Duplicate review'])

@section('page-title', 'Duplicate review')
@section('page-description', 'Compare incoming leads against existing records before deciding what to keep.')
@section('page-actions')
    <span class="rounded-full border border-pedago-amber/20 bg-pedago-amber-tint px-3 py-1.5 text-xs font-semibold text-pedago-amber">{{ number_format($reviews->total()) }} {{ $filters['status'] === 'Pending Review' ? 'pending' : 'reviews' }}</span>
@endsection

@section('content')
    <section class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm" aria-labelledby="review-filter-heading">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="text-base font-bold" id="review-filter-heading">Find reviews</h2>
            <a class="text-sm font-semibold text-pedago-blue-dark underline underline-offset-2" href="{{ route('duplicate-lead-reviews.index') }}">Clear filters</a>
        </div>
        <form class="grid gap-3 sm:grid-cols-[minmax(14rem,1fr)_14rem_auto]" method="GET" action="{{ route('duplicate-lead-reviews.index') }}">
            <label class="sr-only" for="review-search">Search by student name or phone number</label>
            <input class="rounded-md border border-pedago-line px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="review-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search name or phone number">
            <label class="sr-only" for="review-status">Review status</label>
            <select class="rounded-md border border-pedago-line bg-white px-3 py-2.5 text-sm transition focus:border-pedago-blue focus:outline-none focus:ring-2 focus:ring-pedago-blue/20" id="review-status" name="status">
                @foreach (['Pending Review', 'Approved New', 'Merged', 'Discarded', 'all'] as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status === 'all' ? 'All statuses' : $status }}</option>
                @endforeach
            </select>
            <button class="rounded-md border border-pedago-line px-4 py-2.5 text-sm font-semibold transition hover:border-pedago-blue hover:bg-pedago-tint" type="submit">Apply filters</button>
        </form>
    </section>

    <section class="flex flex-col gap-4" aria-label="Duplicate lead reviews">
        @forelse ($reviews as $review)
            @php($incoming = $review->incoming_lead_data)
            <article class="rounded-xl border border-pedago-line bg-white p-5 shadow-sm transition-shadow hover:shadow-md sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-pedago-line pb-4">
                    <div class="flex flex-col gap-1">
                        <h2 class="text-base font-bold">Possible match · {{ $review->match_reason }}</h2>
                        <p class="text-xs text-pedago-muted">Submitted {{ $review->created_at?->format('M j, Y · g:i A') ?? '—' }}</p>
                    </div>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-semibold',
                        'bg-pedago-amber-tint text-pedago-amber' => $review->status === 'Pending Review',
                        'bg-pedago-green-tint text-pedago-green' => in_array($review->status, ['Approved New', 'Merged'], true),
                        'bg-pedago-canvas text-pedago-muted' => $review->status === 'Discarded',
                    ])>{{ $review->status }}</span>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <section class="rounded-lg border border-pedago-blue/15 bg-pedago-tint/40 p-4" aria-label="Incoming lead">
                        <p class="text-xs font-bold uppercase tracking-wide text-pedago-blue-dark">Incoming lead</p>
                        <p class="mt-2 text-base font-semibold">{{ $incoming['student_name'] ?? 'Name not provided' }}</p>
                        <p class="mt-1 text-sm font-medium">{{ $incoming['contact_number'] ?? 'Phone not provided' }}</p>
                        <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs text-pedago-muted">Guardian</dt><dd>{{ $incoming['guardian_name'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-pedago-muted">WhatsApp</dt><dd>{{ $incoming['whatsapp_number'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-pedago-muted">Email</dt><dd class="break-all">{{ $incoming['email'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-pedago-muted">Location</dt><dd>{{ $incoming['location'] ?? '—' }}</dd></div>
                        </dl>
                    </section>
                    <section class="rounded-lg border border-pedago-line bg-pedago-canvas/70 p-4" aria-label="Existing matching lead">
                        <p class="text-xs font-bold uppercase tracking-wide text-pedago-muted">Existing matching lead</p>
                        @if ($review->matchedLead)
                            <p class="mt-2 text-base font-semibold">{{ $review->matchedLead->student_name }}</p>
                            <p class="mt-1 text-sm font-medium">{{ $review->matchedLead->contact_number }}</p>
                            <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                                <div><dt class="text-xs text-pedago-muted">Guardian</dt><dd>{{ $review->matchedLead->guardian_name ?: '—' }}</dd></div>
                                <div><dt class="text-xs text-pedago-muted">WhatsApp</dt><dd>{{ $review->matchedLead->whatsapp_number ?: '—' }}</dd></div>
                                <div><dt class="text-xs text-pedago-muted">Status</dt><dd>{{ $review->matchedLead->status }}</dd></div>
                                <div><dt class="text-xs text-pedago-muted">Current owner</dt><dd>{{ $review->matchedLead->currentOwner?->name ?? 'Unassigned' }}</dd></div>
                            </dl>
                        @else
                            <p class="mt-3 text-sm text-pedago-muted">The matched lead is no longer available.</p>
                        @endif
                    </section>
                </div>

                @if ($review->status === 'Pending Review')
                    <div class="mt-5 flex flex-col gap-3 border-t border-pedago-line pt-4">
                        <p class="text-xs leading-5 text-pedago-muted">Merge copies incoming details only into blank fields on the existing lead. It never replaces the existing name, phone, owner, status, or source.</p>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('duplicate-lead-reviews.update', $review) }}" onsubmit="return confirm('Create this as a separate new lead?')">
                                @csrf
                                @method('PUT')
                                <button class="rounded-md bg-pedago-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-pedago-blue-dark" name="decision" value="approve_new" type="submit">Add as new lead</button>
                            </form>
                            @if ($review->matchedLead)
                                <form method="POST" action="{{ route('duplicate-lead-reviews.update', $review) }}" onsubmit="return confirm('Fill blank details on the existing lead from this incoming record?')">
                                    @csrf
                                    @method('PUT')
                                    <button class="rounded-md border border-pedago-line px-4 py-2 text-sm font-semibold transition hover:border-pedago-blue hover:bg-pedago-tint" name="decision" value="merge" type="submit">Merge blank details</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('duplicate-lead-reviews.update', $review) }}" onsubmit="return confirm('Discard this incoming duplicate record?')">
                                @csrf
                                @method('PUT')
                                <button class="rounded-md border border-pedago-red/20 px-4 py-2 text-sm font-semibold text-pedago-red transition hover:bg-pedago-red-tint" name="decision" value="discard" type="submit">Discard incoming lead</button>
                            </form>
                        </div>
                    </div>
                @else
                    <p class="mt-4 border-t border-pedago-line pt-4 text-xs text-pedago-muted">Reviewed {{ $review->reviewed_at?->format('M j, Y · g:i A') ?? '—' }} by {{ $review->reviewedBy?->name ?? 'an administrator' }}.</p>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-pedago-line bg-white px-5 py-12 text-center">
                <p class="font-semibold text-pedago-ink">No duplicate reviews found</p>
                <p class="mt-1 text-sm text-pedago-muted">Possible matches from lead uploads will appear here.</p>
            </div>
        @endforelse
    </section>

    @if ($reviews->hasPages())
        <div>{{ $reviews->links() }}</div>
    @endif
@endsection
