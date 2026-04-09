<x-layouts.app title="SetUp — 직원 등록">
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <h2 class="text-base font-semibold text-[#2b78c5]">신규 직원 등록</h2>
            <p class="mt-1 text-sm text-gray-600">
                사번·이름·부서 등 필수 정보를 입력해 직원을 등록합니다. (관리자 전용)
            </p>
        </div>

        <livewire:setup-employee-create />
    </div>
</x-layouts.app>
