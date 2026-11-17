@extends('layouts.app', ['title' => 'Paste leads'])

@section('page-title', 'Paste leads')
@section('page-description', 'Paste rows from a spreadsheet. Campaign and course are matched by name.')

@section('page-actions')
    <x-ui.button :href="route('leads.upload')" variant="secondary" icon="upload">CSV upload</x-ui.button>
@endsection

@section('content')
    @if (session('paste_result'))
        @php $result = session('paste_result'); @endphp
        <x-ui.card title="Import result">
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <x-ui.stat label="New leads" :value="$result['added']" />
                <x-ui.stat label="Repeat inquiries" :value="$result['repeats']" hint="Logged on the existing lead" />
                <x-ui.stat label="Skipped" :value="$result['skipped']" :alert="$result['skipped'] > 0" />
                <x-ui.stat label="Unknown campaign" :value="$result['unmatched_campaigns']" hint="Used the channel instead" />
                <x-ui.stat label="Unknown course" :value="$result['unmatched_courses']" hint="Left empty" />
            </dl>
            @if ($result['details'] !== [])
                <h3 class="mt-4 text-sm font-semibold">Why lines were skipped</h3>
                <ul class="mt-1 divide-y divide-pedago-line text-[13px]">
                    @foreach ($result['details'] as $detail)
                        <li class="py-1.5"><span class="num block break-all text-xs text-pedago-muted">{{ $detail['line'] }}</span><span class="font-medium text-pedago-red">{{ $detail['reason'] }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card title="Paste">
        <form class="flex flex-col gap-4" method="POST" action="{{ route('leads.paste.store') }}">
            @csrf
            <div class="sm:max-w-xs">
                <label class="mb-1 block text-sm font-semibold" for="channel">Channel <span class="text-pedago-red" aria-hidden="true">*</span></label>
                <select class="h-10 w-full rounded-md border border-pedago-line bg-white px-2.5 text-sm" id="channel" name="channel" required>
                    @foreach ($channels as $key => $label)
                        <option value="{{ $key }}" @selected(old('channel') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-pedago-muted">Used for lines that give no campaign, or a campaign that does not exist.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold" for="lines">One lead per line <span class="text-pedago-red" aria-hidden="true">*</span></label>
                <textarea class="num w-full rounded-md border border-pedago-line px-3 py-2 text-sm" id="lines" name="lines" rows="12" required placeholder="Rahim Uddin, 01712345678, Spoken Piloting, Spoken English, Dhaka, 17, Karim Uddin, wants evening batch">{{ old('lines') }}</textarea>
                @error('lines')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs leading-5 text-pedago-muted">Columns: <span class="font-semibold">name, phone, campaign, course, district, age, guardian name, remarks</span>. Only name and phone are required. The remarks may contain commas. Up to {{ $maxLines }} lines. A number that already exists, even as another number of a lead, becomes a repeat inquiry, never a second lead.</p>
            </div>
            <div><x-ui.button type="submit" icon="upload">Import</x-ui.button></div>
        </form>
    </x-ui.card>
@endsection
