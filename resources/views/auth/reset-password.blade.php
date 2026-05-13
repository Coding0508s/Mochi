<x-guest-layout>
    <div class="w-full max-w-[440px] mx-auto">
        <x-auth-session-status
            class="mb-4 rounded-lg border border-emerald-200/80 bg-emerald-50/95 px-4 py-3 text-sm text-emerald-900 shadow-sm"
            :status="session('status')"
        />

        <article
            class="mochi-auth-card-view-transition rounded-2xl bg-white px-8 py-10 shadow-xl shadow-slate-300/40 ring-1 ring-mochi-header/10"
            aria-labelledby="reset-password-title"
        >
            <header class="text-center">
                <h1 id="reset-password-title" class="text-2xl font-bold tracking-tight text-mochi-header">
                    {{ __('비밀번호 재설정') }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    {{ __('새 비밀번호를 입력한 뒤 아래 버튼으로 저장해 주세요.') }}
                </p>
            </header>

            <div
                class="mt-6 rounded-xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-left text-xs leading-relaxed text-slate-600"
                role="note"
            >
                <ul class="list-inside list-disc space-y-1 marker:text-mochi-header/60">
                    <li>{{ __('비밀번호는 안전하게 보관되며, 다른 서비스와 동일한 비밀번호 사용은 피해 주세요.') }}</li>
                    <li>{{ __('링크가 만료되었다면 비밀번호 찾기를 다시 진행해 주세요.') }}</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5" id="reset-password-form">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div>
                    <label for="email" class="sr-only">{{ __('회사 이메일') }}</label>
                    <x-ui.mochi-floating-input
                        name="email"
                        id="email"
                        type="email"
                        :label="__('회사 이메일')"
                        :value="old('email', $request->email)"
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

                <div>
                    <label for="password" class="sr-only">{{ __('새 비밀번호') }}</label>
                    <x-ui.mochi-floating-input
                        name="password"
                        id="password"
                        type="password"
                        :label="__('새 비밀번호')"
                        autocomplete="new-password"
                        placeholder="••••••••"
                        required
                    >
                        <x-slot name="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </x-slot>
                    </x-ui.mochi-floating-input>
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm" />
                </div>

                <div>
                    <label for="password_confirmation" class="sr-only">{{ __('새 비밀번호 확인') }}</label>
                    <x-ui.mochi-floating-input
                        name="password_confirmation"
                        id="password_confirmation"
                        type="password"
                        :label="__('새 비밀번호 확인')"
                        autocomplete="new-password"
                        placeholder="••••••••"
                        required
                    >
                        <x-slot name="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                        </x-slot>
                    </x-ui.mochi-floating-input>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-sm" />
                </div>

                <div class="pt-1">
                    <x-ui.liquid-glass-button id="reset-password-submit" pill="true" filter-id="reset-password-glass-filter" variant="mochi-blue" class="w-full">
                        <span data-reset-password-idle class="inline-flex items-center justify-center gap-2">
                            {{ __('비밀번호 재설정') }}
                            <svg class="size-4 opacity-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </span>
                        <span data-reset-password-busy class="hidden items-center justify-center gap-2" aria-hidden="true">
                            <svg class="size-4 shrink-0 animate-spin text-white/95 motion-reduce:animate-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('처리 중') }}
                        </span>
                    </x-ui.liquid-glass-button>
                </div>
            </form>

            <nav class="mt-8 border-t border-slate-100 pt-6 text-center" aria-label="{{ __('Footer navigation') }}">
                @if (Route::has('login'))
                    <a
                        href="{{ route('login') }}"
                        rel="prefetch"
                        class="inline-flex items-center justify-center gap-2 text-sm font-medium text-mochi-header transition-colors duration-200 ease-out hover:text-mochi-excel focus:outline-none focus-visible:underline"
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

    <script>
        (function () {
            const form = document.getElementById('reset-password-form');
            const btn = document.getElementById('reset-password-submit');
            if (!form || !btn) {
                return;
            }
            form.addEventListener('submit', function () {
                if (btn.disabled) {
                    return;
                }
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                const idle = btn.querySelector('[data-reset-password-idle]');
                const busy = btn.querySelector('[data-reset-password-busy]');
                idle?.classList.add('hidden');
                busy?.classList.remove('hidden');
                busy?.classList.add('inline-flex');
            });
        })();
    </script>
</x-guest-layout>
