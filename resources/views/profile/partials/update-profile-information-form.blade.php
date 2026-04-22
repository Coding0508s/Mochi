<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            프로필 정보
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            표시명은 영어 이름을 우선으로 사용합니다. 이름과 영어 이름을 수정할 수 있으며, 이메일은 읽기 전용입니다.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="이름" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="english_name" value="영어 이름" />
            <x-text-input id="english_name" name="english_name" type="text" class="mt-1 block w-full" :value="old('english_name', $englishName ?? '')" required autocomplete="off" />
            <x-input-error class="mt-2" :messages="$errors->get('english_name')" />
        </div>

        <div>
            <x-input-label for="email" value="이메일" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full cursor-not-allowed bg-gray-50 text-gray-500" :value="$user->email" readonly autocomplete="username" />
            <p class="mt-1 text-xs text-gray-500">이메일은 프로필에서 변경할 수 없습니다. 변경이 필요하면 관리자에게 요청해 주세요.</p>
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        이메일 인증이 완료되지 않았습니다.

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            인증 메일 다시 보내기
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            새 인증 링크를 이메일로 전송했습니다.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>저장</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >저장되었습니다.</p>
            @endif
        </div>
    </form>
</section>
