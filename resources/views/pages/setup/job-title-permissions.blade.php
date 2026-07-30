<x-layouts.app title="SetUp — 직책 권한">
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <h2 class="text-base font-semibold text-[#2b78c5]">직책 권한</h2>
            <p class="mt-1 text-sm text-gray-600">
                직책별 기능 권한 표준을 설정합니다. People 계정의 기능 플래그는 이 표와 동기화됩니다.
            </p>
        </div>

        <livewire:setup-job-title-permission-matrix />
    </div>
</x-layouts.app>
