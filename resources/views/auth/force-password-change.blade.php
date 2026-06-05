<x-guest-layout>
<div class="w-full max-w-[420px] mx-auto">
    <div class="mochi-auth-card-view-transition rounded-2xl bg-white px-8 py-10 shadow-xl shadow-slate-300/40 ring-1 ring-mochi-header/10">
        <div class="text-center">
            <h1 class="text-xl font-bold tracking-tight text-mochi-header">
                비밀번호 변경이 필요합니다
            </h1>
            <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                관리자가 발급한 임시 비밀번호로 로그인하셨습니다.
                보안을 위해 <strong class="font-semibold text-slate-800">새 비밀번호</strong>를 설정한 뒤 서비스를 이용해 주세요.
            </p>
        </div>

        <form method="POST" action="{{ route('password.force-change.store') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">새 비밀번호</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                    autofocus
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-mochi-header focus:outline-none focus:ring-2 focus:ring-mochi-header/20"
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">새 비밀번호 확인</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-mochi-header focus:outline-none focus:ring-2 focus:ring-mochi-header/20"
                />
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-mochi-header px-4 py-2.5 text-sm font-semibold text-white hover:bg-mochi-header/90 focus:outline-none focus:ring-2 focus:ring-mochi-header/30"
            >
                비밀번호 저장
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-xs text-slate-500 hover:text-slate-700 underline">
                다른 계정으로 로그인
            </button>
        </form>
    </div>
</div>
</x-guest-layout>
