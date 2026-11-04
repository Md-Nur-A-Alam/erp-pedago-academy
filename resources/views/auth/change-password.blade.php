@extends('layouts.app', ['title' => 'Change password'])

@section('content')
    <section class="mx-auto w-full max-w-xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="mb-6 flex flex-col gap-2">
            <h1 class="text-2xl font-bold">Set a new password</h1>
            <p class="text-sm text-slate-600">You must replace your temporary password before continuing.</p>
        </div>

        <form class="flex flex-col gap-5" method="POST" action="{{ route('password.change.update') }}">
            @csrf
            @method('PUT')
            <label class="flex flex-col gap-2 text-sm font-medium">
                Current password
                <input class="rounded-lg border border-slate-300 px-3 py-2.5 font-normal" name="current_password" type="password" autocomplete="current-password" required>
            </label>
            <label class="flex flex-col gap-2 text-sm font-medium">
                New password
                <input class="rounded-lg border border-slate-300 px-3 py-2.5 font-normal" name="password" type="password" autocomplete="new-password" minlength="6" maxlength="12" required>
                <span class="font-normal text-slate-600">Use 6 to 12 characters.</span>
            </label>
            <label class="flex flex-col gap-2 text-sm font-medium">
                Confirm new password
                <input class="rounded-lg border border-slate-300 px-3 py-2.5 font-normal" name="password_confirmation" type="password" autocomplete="new-password" minlength="6" maxlength="12" required>
            </label>
            <button class="rounded-lg bg-indigo-700 px-4 py-3 font-semibold text-white hover:bg-indigo-800" type="submit">Update password</button>
        </form>
    </section>
@endsection
