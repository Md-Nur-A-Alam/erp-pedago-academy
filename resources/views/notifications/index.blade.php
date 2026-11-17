@extends('layouts.app', ['title' => 'Notifications'])

@section('page-title', 'Notifications')
@section('page-description', 'Reminders and updates for your role. Open one to go straight to the work.')

@section('page-actions')
    @if ($unread > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" icon="check-square">Mark all read</x-ui.button>
        </form>
    @endif
@endsection

@section('content')
    <x-ui.card flush>
        <nav class="flex gap-4 border-b border-pedago-line px-4 sm:px-5" aria-label="Filter notifications">
            @foreach (['all' => 'All', 'unread' => 'Unread ('.$unread.')'] as $key => $label)
                <a @class(['-mb-px border-b-2 py-3 text-sm font-semibold', 'border-pedago-blue text-pedago-blue' => $show === $key, 'border-transparent text-pedago-muted hover:text-pedago-ink' => $show !== $key]) href="{{ route('notifications.index', ['show' => $key]) }}" @if ($show === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($notifications->isEmpty())
            <x-ui.empty :title="$show === 'unread' ? 'You are all caught up' : 'No notifications yet'" icon="bell">
                Follow-up reminders, new leads, approvals and other updates for your role appear here.
            </x-ui.empty>
        @else
            <ul class="divide-y divide-pedago-line">
                @foreach ($notifications as $note)
                    @php $tone = $note->data['tone'] ?? 'blue'; @endphp
                    <li>
                        <a class="flex gap-3 px-4 py-3.5 hover:bg-pedago-row sm:px-5 {{ $note->read_at === null ? 'bg-pedago-tint/40' : '' }}" href="{{ route('notifications.open', $note->id) }}">
                            <span @class(['mt-1.5 size-2.5 shrink-0 rounded-full', 'bg-pedago-red' => $tone === 'red', 'bg-pedago-amber' => $tone === 'amber', 'bg-pedago-green' => $tone === 'green', 'bg-pedago-blue' => ! in_array($tone, ['red', 'amber', 'green'], true)]) aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="text-sm {{ $note->read_at === null ? 'font-semibold' : 'font-medium' }}">{{ $note->data['title'] ?? 'Notification' }}</span>
                                    @if ($note->read_at === null)<x-ui.pill tone="blue">New</x-ui.pill>@endif
                                </span>
                                <span class="mt-0.5 block text-[13px] leading-5 text-pedago-muted">{{ $note->data['body'] ?? '' }}</span>
                                <span class="mt-1 block text-xs text-pedago-muted">{{ $note->created_at->diffForHumans() }} · {{ $note->created_at->format('M j, g:i A') }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($notifications->hasPages())
                <div class="border-t border-pedago-line px-4 py-3 sm:px-5">{{ $notifications->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection
