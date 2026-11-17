@props(['status' => null, 'tone' => null])

@php
    $tones = [
        'green' => 'bg-pedago-green-tint text-pedago-green',
        'blue' => 'bg-pedago-tint text-pedago-blue-dark',
        'red' => 'bg-pedago-red-tint text-pedago-red',
        'amber' => 'bg-pedago-amber-tint text-pedago-amber',
        'slate' => 'bg-pedago-slate-tint text-pedago-muted',
    ];
    $byStatus = [
        'Available' => 'green', 'Verified' => 'green', 'Approved' => 'green', 'Paid' => 'green', 'Converted' => 'green', 'Enrolled' => 'green', 'Auto' => 'green', 'On' => 'green',
        'Cool' => 'blue', 'Busy' => 'blue', 'Assigned' => 'blue', 'Contacted' => 'blue', 'Follow-up' => 'blue', 'Interested' => 'blue', 'New' => 'blue',
        'Hot' => 'red', 'Lazy' => 'red', 'Overdue' => 'red', 'Rejected' => 'red', 'Resigned' => 'red', 'Lost' => 'red',
        'Warm' => 'amber', 'Unavailable' => 'amber', 'Cut Off' => 'amber', 'Pending' => 'amber', 'Under Review' => 'amber', 'Payment Under Review' => 'amber', 'Enrolled - Pending Payment' => 'amber', 'Pending Payment' => 'amber', 'Due soon' => 'amber', 'Manual' => 'amber',
    ];
    $resolved = $tones[$tone ?? ($byStatus[$status] ?? 'slate')];
@endphp

<span {{ $attributes->class(['inline-flex h-[22px] items-center gap-1.5 whitespace-nowrap rounded-full px-2 text-xs font-semibold', $resolved]) }}>
    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $slot->isEmpty() ? $status : $slot }}
</span>
