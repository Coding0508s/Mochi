<x-layouts.app title="프로필 설정">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('profile.partials.update-profile-information-form')
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('profile.partials.update-password-form')
        </section>

        @if (auth()->user()?->hasFullAccess())
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                @include('profile.partials.delete-user-form')
            </section>
        @endif
    </div>
</x-layouts.app>
