@extends('layouts.app', ['title' => 'Sign in'])

@section('content')
    <main class="relative flex w-full max-w-[440px] flex-col gap-4 overflow-hidden font-[Poppins,sans-serif] text-[#222] min-[861px]:aspect-[1465/974] min-[861px]:max-w-[1465px] min-[861px]:gap-0" aria-label="Sign in to Pedago Academy ERP">
        <span class="fixed left-0 top-0 z-0 hidden h-[290px] w-[326px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-gradient-to-br from-[#5B52E6] to-[#3F2FC0] min-[861px]:block" aria-hidden="true"></span>
        <span class="fixed right-0 bottom-0 z-0 hidden size-[350px] translate-x-1/2 translate-y-1/2 rounded-full bg-gradient-to-br from-[#4A22A8] to-[#3A1C93] min-[861px]:block" aria-hidden="true"></span>

        <aside class="relative order-2 aspect-[4/5] w-full overflow-hidden rounded-lg bg-[#0B3FA8] shadow-[0_8px_24px_rgba(20,40,120,0.18)] min-[861px]:absolute min-[861px]:right-[6.9%] min-[861px]:top-[8.2%] min-[861px]:z-0 min-[861px]:order-none min-[861px]:aspect-auto min-[861px]:h-[83.2%] min-[861px]:w-[46.6%]" aria-label="Welcome to Pedago Academy">
            <img class="size-full object-cover" src="{{ asset('user_defined_assets/img/Welcome%20to%20Pedago%20Academy%20ERP%20System.png') }}" alt="Welcome to Pedago Academy ERP System. Smarter management, better learning.">
        </aside>

        <section class="relative z-10 order-1 flex w-full flex-col rounded-lg bg-white px-6 py-10 shadow-[0_6px_26px_rgba(0,0,0,0.09)] min-[861px]:absolute min-[861px]:left-[6.9%] min-[861px]:top-[15.5%] min-[861px]:order-none min-[861px]:h-[68.7%] min-[861px]:w-[42%] min-[861px]:px-[5.2%] min-[861px]:py-0" aria-labelledby="login-title">
            <h1 class="text-center text-2xl font-semibold text-[#333] underline decoration-2 underline-offset-[8px] min-[861px]:mt-[15%] min-[861px]:h-[6%] min-[861px]:shrink-0 min-[861px]:text-[clamp(1.25rem,1.8vw,1.625rem)] min-[861px]:leading-none">
                Login please
            </h1>

            @if ($errors->any())
                <div class="mt-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 min-[861px]:absolute min-[861px]:left-[5.2%] min-[861px]:right-[5.2%] min-[861px]:top-[2%] min-[861px]:mt-0" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form class="flex w-full flex-col" method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="mt-8 flex h-14 items-center rounded-md border border-slate-100 border-l-4 border-l-[#0B42BE] bg-white pl-4 shadow-[0_5px_18px_rgba(30,70,170,0.13)] transition focus-within:ring-[3px] focus-within:ring-[#0B42BE]/35 min-[861px]:mt-[13%] min-[861px]:h-[clamp(3.5rem,5.7vw,5.15rem)] min-[861px]:shrink-0 min-[861px]:pl-[7.5%]">
                    <label class="sr-only" for="phone_number">Phone number</label>
                    <svg class="mr-3 size-5 shrink-0 text-[#8A8A8A] min-[861px]:mr-[6%] min-[861px]:size-[clamp(1.25rem,2.2vw,2rem)]" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="6" width="28" height="20" rx="2"></rect>
                        <path d="M2.5 7l13.5 11L29.5 7M2.5 25l9.5-9.5M29.5 25L20 15.5"></path>
                    </svg>
                    <span class="mr-3 h-[18px] w-0.5 shrink-0 bg-[#7F93CC] min-[861px]:mr-[5%]"></span>
                    <input class="h-full min-w-0 flex-1 border-0 bg-transparent pr-3 text-base italic text-[#222] outline-none placeholder:text-[#767676] focus:ring-0 min-[861px]:pr-2 min-[861px]:text-[clamp(0.875rem,1.25vw,1.125rem)]" id="phone_number" name="phone_number" type="tel" inputmode="tel" value="{{ old('phone_number') }}" placeholder="Input your phone number" autocomplete="username" required autofocus>
                </div>

                <div class="mt-[18px] flex h-14 items-center rounded-md border border-slate-100 border-l-4 border-l-[#0B42BE] bg-white pl-4 shadow-[0_5px_18px_rgba(30,70,170,0.13)] transition focus-within:ring-[3px] focus-within:ring-[#0B42BE]/35 min-[861px]:mt-[6%] min-[861px]:h-[clamp(3.5rem,5.7vw,5.15rem)] min-[861px]:shrink-0 min-[861px]:pl-[7.5%]">
                    <label class="sr-only" for="password">Password</label>
                    <svg class="mr-3 size-5 shrink-0 text-[#8A8A8A] min-[861px]:mr-[6%] min-[861px]:size-[clamp(1.25rem,2.2vw,2rem)]" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <circle cx="9" cy="9" r="1.6"></circle>
                        <path d="M15.5 16.5L27 28M23 24l3-3M20 21l3-3"></path>
                    </svg>
                    <span class="mr-3 h-[18px] w-0.5 shrink-0 bg-[#7F93CC] min-[861px]:mr-[5%]"></span>
                    <input class="h-full min-w-0 flex-1 border-0 bg-transparent pr-3 text-base italic text-[#222] outline-none placeholder:text-[#767676] focus:ring-0 min-[861px]:pr-2 min-[861px]:text-[clamp(0.875rem,1.25vw,1.125rem)]" id="password" name="password" type="password" placeholder="Input your password" autocomplete="current-password" minlength="6" maxlength="12" required>
                </div>

                <div class="mt-6 flex min-h-8 flex-wrap items-center justify-between gap-3 px-1 min-[861px]:mt-[12%] min-[861px]:min-h-7 min-[861px]:shrink-0 min-[861px]:gap-2">
                    <label class="relative inline-flex cursor-pointer items-center gap-3 text-[15px] text-[#444] min-[861px]:gap-[clamp(0.5rem,1.4vw,1.3rem)] min-[861px]:text-[clamp(0.875rem,1.25vw,1.125rem)]">
                        <input class="peer absolute size-6 cursor-pointer opacity-0 focus-visible:outline-none" name="remember" type="checkbox" value="1" @checked(old('remember', true))>
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-[3px] bg-[#6B6B6B] text-white peer-checked:bg-[#6B6B6B] peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#0B42BE] peer-not-checked:border-2 peer-not-checked:border-[#6B6B6B] peer-not-checked:bg-white peer-not-checked:text-transparent">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 10.5l4 4 8-9"></path>
                            </svg>
                        </span>
                        Remember me
                    </label>
                    <span class="text-sm font-semibold text-[#222] underline decoration-1 underline-offset-1 min-[861px]:text-[clamp(0.875rem,1.2vw,1.0625rem)]" title="Contact your administrator to reset your password">Forgot Password?</span>
                </div>

                <button class="mx-auto mt-7 inline-flex h-[52px] w-40 items-center justify-center gap-2.5 rounded bg-[#0B42BE] text-base font-bold tracking-wide text-white shadow-[0_6px_14px_rgba(11,66,190,0.35)] transition hover:bg-[#0A369B] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0B42BE] min-[861px]:mt-[11.5%] min-[861px]:h-[clamp(3.25rem,3.8vw,3.625rem)] min-[861px]:w-[37%] min-[861px]:shrink-0 min-[861px]:text-[clamp(0.875rem,1.2vw,1.0625rem)]">
                    <svg class="size-5 min-[861px]:size-[clamp(1rem,1.65vw,1.5rem)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 12h10M9 8l4 4-4 4"></path>
                        <path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4" fill="currentColor"></path>
                    </svg>
                    LOG IN
                </button>
            </form>
        </section>
    </main>
@endsection
