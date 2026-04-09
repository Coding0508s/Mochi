<x-guest-layout>
<div class="w-full max-w-[420px] mx-auto">
    <x-auth-session-status class="mb-4 rounded-lg border border-mochi-header/15 bg-white/90 px-4 py-2 text-sm text-slate-700 shadow-sm" :status="session('status')" />

    <div class="rounded-2xl bg-white px-8 py-10 shadow-xl shadow-slate-300/40 ring-1 ring-mochi-header/10">
        {{-- 뱃지 --}}
        <div class="flex justify-center mb-4">
            <div
                class="inline-flex items-center gap-2 rounded-full border border-mochi-header/25 bg-mochi-header/8 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-mochi-header"
            >
                <svg class="size-3.5 shrink-0 text-mochi-header" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                {{ __('Internal access only') }}
            </div>
        </div>
        <div class="mt-6 text-center">
            <h1
                class="text-2xl font-bold tracking-tight text-mochi-header"
                aria-label="{{ __('GrapeSEED MOCHI') }}"
            >
                <span
                    data-special-text
                    data-speed="80"
                    data-text="{{ __('GrapeSEED MOCHI') }}"
                    class="inline-block min-h-[2rem] font-mono font-bold tracking-tight"
                    aria-hidden="true"
                ></span>
            </h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('Log in to access Mochi') }}</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf
            {{-- 회사 이메일 --}}
            <div>
                <label for="email" class="mb-2 block text-[11px] font-bold uppercase tracking-wide text-slate-800">
                    {{ __('Corporate email') }}
                </label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-mochi-header">
                        <svg class="size-[18px]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                    </span>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="name@grapeseed.com"
                        class="block w-full rounded-xl border border-mochi-header/20 bg-mochi-header/[0.07] py-3 pl-11 pr-4 text-sm text-slate-800 placeholder:text-slate-400 shadow-inner shadow-slate-200/40 outline-none transition focus:border-mochi-excel focus:bg-white focus:ring-2 focus:ring-mochi-header/25"
                    />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm" />
            </div>

            {{-- 비밀번호 + 비밀번호 찾기 --}}
            <div>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <label for="password" class="text-[11px] font-bold uppercase tracking-wide text-slate-800">
                        {{ __('Password') }}
                    </label>
                    @if (Route::has('password.request'))
                        <a
                            href="{{ route('password.request') }}"
                            class="text-xs font-medium text-mochi-header transition hover:text-mochi-excel focus:outline-none focus:underline"
                        >
                            {{ __('Forgot password?') }}
                        </a>
                    @endif
                </div>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-mochi-header">
                        <svg class="size-[18px]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </span>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="block w-full rounded-xl border border-mochi-header/20 bg-mochi-header/[0.07] py-3 pl-11 pr-4 text-sm text-slate-800 placeholder:text-slate-400 shadow-inner shadow-slate-200/40 outline-none transition focus:border-mochi-excel focus:bg-white focus:ring-2 focus:ring-mochi-header/25"
                    />
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm" />
            </div>

            <div class="flex items-center">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="size-4 rounded border-slate-300 text-mochi-header focus:ring-mochi-header"
                    name="remember"
                />
                <label for="remember_me" class="ms-2 text-sm text-slate-600">{{ __('Remember me') }}</label>
            </div>

            <div class="pt-1">
                <x-ui.liquid-glass-button pill="true" filter-id="login-glass-filter" class="w-full">
                    {{ __('Sign In') }}
                    <svg class="size-4 opacity-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </x-ui.liquid-glass-button>
            </div>
        </form>
    </div>

    {{-- 푸터 링크 --}}
   <!--  <nav class="mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm font-medium text-slate-800" aria-label="{{ __('Footer navigation') }}">
        <a href="#" class="inline-flex items-center gap-2 transition hover:text-slate-950 focus:outline-none focus:underline">
            <svg class="size-4 text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.051.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.051.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
            </svg>
            {{ __('Internal Wiki') }}
        </a>
        <a href="#" class="inline-flex items-center gap-2 transition hover:text-slate-950 focus:outline-none focus:underline">
            <svg class="size-4 text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
            </svg>
            {{ __('IT Support') }}
        </a>
        <a href="#" class="inline-flex items-center gap-2 transition hover:text-slate-950 focus:outline-none focus:underline">
            <svg class="size-4 text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9 9 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
            </svg>
            {{ __('Help Desk') }}
        </a>
    </nav> -->
</div>
</x-guest-layout>
