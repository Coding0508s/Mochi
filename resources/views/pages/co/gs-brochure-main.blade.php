<x-layouts.app title="GS Brochure Main">
    <div class="mochi-page max-w-4xl">
        <div class="mochi-summary-card">
            <h1 class="text-xl font-semibold text-[#2b78c5]">GrapeSEED Brochure Management System</h1>
            <p class="mt-2 text-sm text-gray-600">원하시는 서비스를 선택해 주세요.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('co.gs-brochure.request') }}" class="rounded-lg bg-[#2b78c5] px-4 py-2 text-sm font-semibold text-white">브로셔 신청</a>
                <a href="{{ route('co.gs-brochure.admin.dashboard') }}" class="rounded-lg bg-[#2b78c5] px-4 py-2 text-sm font-semibold text-white">관리자 페이지</a>
            </div>
        </div>
    </div>
</x-layouts.app>
