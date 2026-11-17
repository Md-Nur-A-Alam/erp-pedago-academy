@extends('layouts.app', ['title' => 'Enroll '.$lead->student_name])

@section('page-title', 'Enroll '.$lead->student_name)
@section('page-description', 'Lead L-'.$lead->id.'. Agree the course and payment plan. The lead converts when Accounts verifies the first payment.')

@section('page-actions')
    <x-ui.button :href="route('leads.workspace', $lead)" variant="secondary">Back to lead</x-ui.button>
@endsection

@section('content')
    @php
        $field = 'h-10 w-full rounded-md border border-pedago-line bg-white px-2.5 text-sm';
    @endphp

    <form class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:items-start" method="POST" action="{{ route('enrollments.store', $lead) }}" data-enroll-form>
        @csrf
        <div class="flex flex-col gap-4">
            @if ($errors->has('lead'))
                <div class="rounded-lg border border-pedago-red-line bg-pedago-red-tint px-4 py-3 text-sm font-medium text-pedago-red" role="alert">{{ $errors->first('lead') }}</div>
            @endif

            <x-ui.card title="Course and fee">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-semibold" for="course_id">Course <span class="text-pedago-red" aria-hidden="true">*</span></label>
                        <select class="{{ $field }}" id="course_id" name="course_id" required data-course>
                            <option value="">Choose a course</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" data-fee="{{ (float) $course->base_fee }}" @selected((string) old('course_id', $lead->interested_course_id) === (string) $course->id)>{{ $course->title }} — Tk {{ number_format((float) $course->base_fee) }}</option>
                            @endforeach
                        </select>
                        @error('course_id')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                        @error('course_batch')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-pedago-muted">The student takes a seat in the first batch that still has room.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="discount_percentage">Discount (%)</label>
                        <input class="{{ $field }}" id="discount_percentage" name="discount_percentage" type="number" min="0" max="{{ $maxDiscount }}" step="0.5" value="{{ old('discount_percentage', 0) }}" data-discount>
                        @error('discount_percentage')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-pedago-muted">Up to {{ $maxDiscount }}%. More needs an administrator.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="total_installments">Installments <span class="text-pedago-red" aria-hidden="true">*</span></label>
                        <input class="{{ $field }}" id="total_installments" name="total_installments" type="number" min="1" max="{{ $maxInstallments }}" value="{{ old('total_installments', 1) }}" required data-installments>
                        @error('total_installments')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-pedago-muted">1 to {{ $maxInstallments }}, {{ $gapDays }} days apart.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="first_due_date">First installment due <span class="text-pedago-red" aria-hidden="true">*</span></label>
                        <input class="{{ $field }}" id="first_due_date" name="first_due_date" type="date" min="{{ today()->toDateString() }}" value="{{ old('first_due_date', today()->toDateString()) }}" required>
                        @error('first_due_date')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Student and guardian">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="student_phone">Student phone <span class="text-pedago-red" aria-hidden="true">*</span></label>
                        <input class="{{ $field }}" id="student_phone" name="student_phone" type="tel" maxlength="25" value="{{ old('student_phone', $lead->contact_number) }}" required>
                        @error('student_phone')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="student_email">Student email</label>
                        <input class="{{ $field }}" id="student_email" name="student_email" type="email" maxlength="120" value="{{ old('student_email', $lead->email) }}">
                        @error('student_email')<p class="mt-1 text-[13px] font-medium text-pedago-red">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="guardian_name">Guardian name</label>
                        <input class="{{ $field }}" id="guardian_name" name="guardian_name" type="text" maxlength="150" value="{{ old('guardian_name', $lead->guardian_name) }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold" for="guardian_phone">Guardian phone</label>
                        <input class="{{ $field }}" id="guardian_phone" name="guardian_phone" type="tel" maxlength="25" value="{{ old('guardian_phone', $lead->alternative_contact_number) }}">
                    </div>
                </div>
            </x-ui.card>
        </div>

        <x-ui.card title="Payment plan">
            <dl class="grid gap-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-pedago-muted">Course fee</dt><dd class="num font-semibold" data-out="fee">Tk 0</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-pedago-muted">Discount</dt><dd class="num font-semibold" data-out="discount">Tk 0</dd></div>
                <div class="flex justify-between gap-3 border-t border-pedago-line pt-2"><dt class="font-semibold">Student pays</dt><dd class="num text-base font-bold" data-out="total">Tk 0</dd></div>
            </dl>
            <ol class="mt-3 space-y-1 text-[13px]" data-out="plan"></ol>
            <x-ui.button class="mt-4 w-full" type="submit" icon="check-square">Enroll student</x-ui.button>
            <p class="mt-2 text-xs leading-5 text-pedago-muted">After this, collect the first installment and submit the payment on the lead page.</p>
        </x-ui.card>
    </form>

    <script>
        (() => {
            const form = document.querySelector('[data-enroll-form]');
            if (!form) return;
            const gap = {{ $gapDays }};
            const money = (n) => 'Tk ' + Math.round(n).toLocaleString('en-US');
            const out = (key) => form.querySelector('[data-out="' + key + '"]');
            const render = () => {
                const option = form.querySelector('[data-course]').selectedOptions[0];
                const fee = parseFloat(option && option.dataset.fee ? option.dataset.fee : '0') || 0;
                const discount = Math.min(parseFloat(form.querySelector('[data-discount]').value) || 0, 100);
                const count = Math.max(1, Math.min(parseInt(form.querySelector('[data-installments]').value, 10) || 1, 24));
                const total = Math.round(fee * (1 - discount / 100) * 100) / 100;
                const share = Math.floor(total / count * 100) / 100;
                const first = Math.round((total - share * (count - 1)) * 100) / 100;
                const start = new Date(form.querySelector('[name="first_due_date"]').value || Date.now());
                out('fee').textContent = money(fee);
                out('discount').textContent = '- ' + money(fee - total);
                out('total').textContent = money(total);
                out('plan').innerHTML = Array.from({ length: count }, (_, i) => {
                    const due = new Date(start.getTime() + i * gap * 86400000);
                    return '<li class="flex justify-between gap-3"><span class="text-pedago-muted">#' + (i + 1) + ' · ' + due.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + '</span><span class="num font-medium">' + money(i === 0 ? first : share) + '</span></li>';
                }).join('');
            };
            form.addEventListener('input', render);
            render();
        })();
    </script>
@endsection
