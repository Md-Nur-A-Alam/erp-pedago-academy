@extends('layouts.app', ['title' => 'Installments'])

@section('page-title', 'Installments')
@section('page-description', 'The payment schedule of every enrolled student.')

@section('content')
    @php
        $taka = fn (float $amount): string => '৳ '.number_format($amount);
        $hints = [
            'due_soon' => 'Due within '.$reminderDays.' '.Str::plural('day', $reminderDays),
            'overdue' => 'Past the due date, unpaid',
            'upcoming' => 'Due later',
            'under_review' => 'Payment submitted',
            'paid' => 'Verified by Accounts',
        ];
    @endphp

    <nav class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label="Installment views">
        @foreach ($tabs as $key => $name)
            @php $active = $tab === $key; @endphp
            <a @class([
                'block rounded-lg border px-4 py-3.5 transition-colors duration-150',
                'border-pedago-blue bg-pedago-tint' => $active,
                'border-pedago-red-line bg-white hover:bg-pedago-row' => ! $active && $key === 'overdue' && $summary[$key]['count'] > 0,
                'border-pedago-line bg-white hover:border-pedago-blue hover:bg-pedago-row' => ! $active && ! ($key === 'overdue' && $summary[$key]['count'] > 0),
            ]) href="{{ route('installments.index', ['tab' => $key]) }}" @if ($active) aria-current="page" @endif>
                <span class="block text-xs font-medium text-pedago-muted">{{ $name }}</span>
                <span @class(['num mt-1 block text-[26px] font-semibold leading-tight', 'text-pedago-red' => $key === 'overdue' && $summary[$key]['count'] > 0])>{{ number_format($summary[$key]['count']) }}</span>
                <span class="mt-0.5 block text-xs text-pedago-muted"><span class="num">{{ $taka($summary[$key]['amount']) }}</span> · {{ $hints[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <x-ui.card :title="$tabs[$tab]" flush>
        <x-slot:meta>{{ number_format($installments->total()) }} {{ Str::plural('installment', $installments->total()) }}</x-slot:meta>

        @if ($installments->isEmpty())
            <x-ui.empty class="border-t border-pedago-line" title="No installments in this view" icon="calendar">
                Installments are created with an enrollment and move between these views as their due dates approach and payments are verified.
            </x-ui.empty>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] border-collapse text-left">
                    <thead>
                        <tr class="border-y border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                            <th class="px-4 py-2 font-semibold sm:pl-5" scope="col">{{ $tab === 'paid' ? 'Paid on' : 'Due date' }}</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Student</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Course</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Installment</th>
                            <th class="px-2 py-2 text-right font-semibold" scope="col">Amount</th>
                            <th class="px-2 py-2 font-semibold" scope="col">Status</th>
                            <th class="px-4 py-2 font-semibold sm:pr-5" scope="col">Sales executive</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        @foreach ($installments as $installment)
                            @php $daysLeft = (int) today()->diffInDays($installment->due_date); @endphp
                            <tr class="border-b border-pedago-line transition-colors duration-150 last:border-0 hover:bg-pedago-row">
                                <td class="whitespace-nowrap px-4 py-2 sm:pl-5">
                                    <span class="num block text-xs">{{ ($tab === 'paid' ? ($installment->paid_at ?? $installment->due_date) : $installment->due_date)->format('M j, Y') }}</span>
                                    @if ($tab === 'overdue')
                                        <span class="block text-xs font-semibold text-pedago-red">{{ abs($daysLeft) }} {{ Str::plural('day', abs($daysLeft)) }} late</span>
                                    @elseif (in_array($tab, ['due_soon', 'upcoming'], true))
                                        <span class="block text-xs text-pedago-muted">{{ $daysLeft === 0 ? 'Due today' : 'In '.$daysLeft.' '.Str::plural('day', $daysLeft) }}</span>
                                    @endif
                                </td>
                                <th class="px-2 py-2 font-medium" scope="row">
                                    <span class="block max-w-48 truncate">{{ $installment->enrollment?->student_name }}</span>
                                    <span class="num block text-xs font-normal text-pedago-muted">{{ $installment->enrollment?->student_phone }}</span>
                                </th>
                                <td class="px-2 py-2"><span class="block max-w-52 truncate">{{ $installment->enrollment?->course?->title ?? '—' }}</span></td>
                                <td class="whitespace-nowrap px-2 py-2"><span class="num">{{ $installment->installment_number }}</span> of <span class="num">{{ $installment->enrollment?->total_installments }}</span></td>
                                <td class="num whitespace-nowrap px-2 py-2 text-right font-semibold">{{ $taka((float) $installment->due_amount) }}</td>
                                <td class="px-2 py-2">
                                    @if ($tab === 'overdue')
                                        <x-ui.pill status="Overdue" />
                                    @elseif ($tab === 'due_soon')
                                        <x-ui.pill tone="amber">T−{{ max(0, $daysLeft) }}</x-ui.pill>
                                    @else
                                        <x-ui.pill :status="$installment->status" />
                                    @endif
                                </td>
                                <td class="px-4 py-2 sm:pr-5"><span class="block max-w-44 truncate">{{ $installment->enrollment?->salesExecutive?->name ?? '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($installments->hasPages())
                <div class="border-t border-pedago-line px-4 py-3 sm:px-5">{{ $installments->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection
