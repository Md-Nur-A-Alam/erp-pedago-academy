@extends('layouts.app', ['title' => 'Sign in'])

@section('content')
    @php
        $hasError = $errors->any();
        $input = 'h-12 w-full rounded-lg border bg-white pl-11 pr-3 text-base text-pedago-ink transition-colors duration-150 placeholder:text-pedago-muted/70 focus:border-pedago-blue focus:outline-none focus:ring-4 focus:ring-pedago-blue/15';
    @endphp

    <div class="grid min-h-screen w-full bg-white font-[Poppins,'IBM_Plex_Sans',sans-serif] lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <aside class="relative h-48 overflow-hidden bg-pedago-blue-dark sm:h-60 lg:h-auto" aria-label="Welcome to Pedago Academy">
            <img class="absolute inset-0 size-full object-cover object-[50%_16%] lg:object-center" src="{{ asset('user_defined_assets/img/Welcome%20to%20Pedago%20Academy%20ERP%20System.png') }}" alt="Welcome to Pedago Academy ERP System. Smarter management, better learning.">
            <span class="absolute inset-x-0 bottom-0 h-1.5 bg-pedago-red lg:hidden" aria-hidden="true"></span>
        </aside>

        <main class="flex flex-col px-6 py-8 sm:px-12 lg:px-16 xl:px-24" aria-label="Sign in to Pedago Academy ERP">
            <div class="flex flex-1 items-center">
                <section class="mx-auto w-full max-w-[26rem]" aria-labelledby="login-title">
                    <img class="mb-8 h-auto w-48" src="{{ asset('user_defined_assets/img/horizontal%20logo%201%20on%20whtie%20bg.png') }}" alt="Pedago Academy" width="192" height="52">

                    <h1 class="text-[1.75rem] font-semibold leading-tight tracking-tight text-pedago-ink" id="login-title">Sign in to your account</h1>
                    <span class="mt-3 block h-1 w-12 rounded-full bg-pedago-red" aria-hidden="true"></span>
                    <p class="mt-3 font-sans text-[15px] leading-6 text-pedago-muted">Use the phone number your administrator registered for you.</p>

                    @if ($hasError)
                        <div class="mt-6 flex items-start gap-2.5 rounded-lg border border-pedago-red-line bg-pedago-red-tint px-4 py-3 font-sans text-sm font-medium text-pedago-red" role="alert">
                            <x-ui.icon name="alert" class="mt-0.5 size-4" />
                            <div class="space-y-1">
                                @foreach ($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <form class="mt-7 flex flex-col gap-5 font-sans" id="login-form" method="POST" action="{{ route('login.store') }}">
                        @csrf

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-pedago-ink" for="phone_number">Phone number</label>
                            <div class="relative">
                                <x-ui.icon name="phone" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-pedago-muted" />
                                <input @class([$input, 'border-pedago-red' => $hasError, 'border-pedago-line' => ! $hasError]) id="phone_number" name="phone_number" type="tel" inputmode="tel" value="{{ old('phone_number') }}" placeholder="01XXXXXXXXX" autocomplete="username" required autofocus @if ($hasError) aria-invalid="true" @endif>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-pedago-ink" for="password">Password</label>
                            <div class="relative">
                                <x-ui.icon name="lock" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-pedago-muted" />
                                <input @class([$input, 'pr-12', 'border-pedago-red' => $hasError, 'border-pedago-line' => ! $hasError]) id="password" name="password" type="password" placeholder="Your password" autocomplete="current-password" minlength="6" maxlength="12" required aria-describedby="password-help">
                                <button class="absolute right-1 top-1/2 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-md text-pedago-muted transition-colors duration-150 hover:text-pedago-blue-dark" id="password-toggle" type="button" aria-controls="password" aria-pressed="false" aria-label="Show password">
                                    <x-ui.icon name="eye" class="size-5" />
                                </button>
                            </div>
                            <p class="mt-1.5 text-xs text-pedago-muted" id="password-help">6 to 12 characters.</p>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                            <label class="inline-flex min-h-8 cursor-pointer items-center gap-2.5 text-sm text-pedago-ink" for="remember">
                                <input class="size-[18px] rounded border-pedago-line accent-pedago-blue" id="remember" name="remember" type="checkbox" value="1" @checked(old('remember', true))>
                                Remember me
                            </label>
                            <details class="group text-sm">
                                <summary class="cursor-pointer list-none rounded font-semibold text-pedago-blue-dark underline underline-offset-2 hover:text-pedago-blue">Forgot Password?</summary>
                            </details>
                        </div>
                        <p class="-mt-2 hidden rounded-lg bg-pedago-tint px-4 py-3 text-[13px] leading-5 text-pedago-blue-dark" id="forgot-help">Ask your administrator to reset it. You will get a temporary password and choose your own when you sign in.</p>

                        <button class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-pedago-blue text-base font-semibold text-white transition-colors duration-150 hover:bg-pedago-blue-dark disabled:cursor-wait disabled:opacity-80" id="login-submit" type="submit">
                            <span id="login-submit-label">Log in</span>
                            <x-ui.icon name="arrow-right" class="size-5" />
                        </button>
                    </form>
                </section>
            </div>

            <footer class="mx-auto mt-8 w-full max-w-[26rem] border-t border-pedago-line pt-4 font-sans text-xs leading-5 text-pedago-muted">
                <p class="flex items-start gap-2"><x-ui.icon name="lock" class="mt-0.5 size-3.5" /><span>Authorised staff only. Sign-ins and access to staff passwords are recorded.</span></p>
                <p class="mt-1">&copy; {{ now()->year }} Pedago Academy</p>
            </footer>
        </main>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const password = document.getElementById('password');
            const toggle = document.getElementById('password-toggle');
            toggle.addEventListener('click', () => {
                const show = password.type === 'password';
                password.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', String(show));
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });

            const forgot = document.querySelector('details');
            const help = document.getElementById('forgot-help');
            forgot.addEventListener('toggle', () => help.classList.toggle('hidden', !forgot.open));

            const form = document.getElementById('login-form');
            form.addEventListener('submit', () => {
                const button = document.getElementById('login-submit');
                if (form.checkValidity()) {
                    button.disabled = true;
                    document.getElementById('login-submit-label').textContent = 'Signing in…';
                }
            });
        })();
    </script>
@endpush
