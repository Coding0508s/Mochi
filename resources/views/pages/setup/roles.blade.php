<x-layouts.app title="SetUp — 역할·권한">
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <h2 class="text-base font-semibold text-[#2b78c5]">역할·권한</h2>
            <p class="mt-1 text-sm text-gray-600">
                역할은 <strong class="font-medium text-gray-700">SetUp 화면 위임</strong>과 <strong class="font-medium text-gray-700">계정 특수 권한</strong>을 정의합니다. People·기관 등 업무 메뉴는 팀·담당 범위가 기본입니다. 목록의 <span class="font-medium text-gray-700">사용자</span> 버튼으로 역할을 할당하세요.
            </p>
        </div>

        <livewire:setup-role-management />
    </div>
</x-layouts.app>
