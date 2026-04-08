<x-layouts.app title="SetUp">
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <h2 class="text-base font-semibold text-[#2b78c5]">SetUp</h2>
            <p class="mt-1 text-sm text-gray-600">
                운영 기준값을 한 곳에서 관리하는 화면입니다.
            </p>
        </div>

        <livewire:setup-team-management />
        <livewire:setup-common-code-management />
        <livewire:setup-role-management />

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @php
                $setupSections = [
                    ['title' => '사용자 계정 관리', 'desc' => '계정 활성/비활성 및 기본 정보 관리'],
                    ['title' => '시스템 기본값', 'desc' => '시간대, 포맷, 공통 옵션 기본값 관리'],
                    ['title' => '변경 이력', 'desc' => '누가/언제/무엇을 변경했는지 조회'],
                ];
            @endphp

            @foreach($setupSections as $section)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $section['title'] }}</h3>
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">준비중</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 leading-5">{{ $section['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>

