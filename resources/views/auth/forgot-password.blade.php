<x-guest-layout>
    <div class="w-full max-w-[440px] mx-auto">
        <x-auth-session-status
            class="mb-4 rounded-lg border border-emerald-200/80 bg-emerald-50/95 px-4 py-3 text-sm text-emerald-900 shadow-sm"
            :status="session('status')"
        />

        <article
            class="rounded-2xl bg-white px-8 py-10 shadow-xl shadow-slate-300/40 ring-1 ring-mochi-header/10"
            aria-labelledby="password-recovery-title"
        >
            <header class="text-center">
                <div class="mb-5 flex justify-center">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-mochi-header/25 bg-mochi-header/8 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-mochi-header"
                    >
                        <svg class="size-3.5 shrink-0 text-mochi-header" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12V12Z" />
                        </svg>
                        {{ __('Account recovery') }}
                    </div>
                </div>
                <h1 id="password-recovery-title" class="text-2xl font-bold tracking-tight text-mochi-header">
                    {{ __('Password recovery') }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    {{ __('Password recovery subtitle') }}
                </p>
            </header>

            <div
                class="mt-6 rounded-xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-left text-xs leading-relaxed text-slate-600"
                role="note"
            >
                <ul class="list-inside list-disc space-y-1 marker:text-mochi-header/60">
                    <li>{{ __('Password recovery hint inbox') }}</li>
                    <li>{{ __('Password recovery hint security') }}</li>
                    <li>{{ __('Password recovery hint expiry') }}</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="email" class="sr-only">{{ __('Corporate email') }}</label>
                    <x-ui.mochi-floating-input
                        name="email"
                        id="email"
                        type="email"
                        :label="__('Corporate email')"
                        :value="old('email')"
                        autocomplete="username"
                        placeholder="name@grapeseed.com"
                        required
                        autofocus
                    >
                        <x-slot name="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                        </x-slot>
                    </x-ui.mochi-floating-input>
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm" />
                </div>

                <div class="pt-1">
                    <x-ui.liquid-glass-button pill="true" filter-id="forgot-password-glass-filter" variant="mochi-blue" class="w-full">
                        {{ __('Send reset link') }}
                        <svg class="size-4 opacity-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                    </x-ui.liquid-glass-button>
                </div>
            </form>

            <nav class="mt-8 border-t border-slate-100 pt-6 text-center" aria-label="{{ __('Footer navigation') }}">
                @if (Route::has('login'))
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center gap-2 text-sm font-medium text-mochi-header transition hover:text-mochi-excel focus:outline-none focus-visible:underline"
                    >
                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        {{ __('Back to sign in') }}
                    </a>
                @endif
            </nav>
        </article>
    </div>
</x-guest-layout>
