<x-layouts.app title="GS Brochure Admin">
    <div class="mochi-page max-w-4xl">
        <div class="mochi-summary-card">
            <h1 class="text-lg font-semibold text-[#2b78c5]">GS Brochure 관리자 대시보드</h1>
            <p class="mt-2 text-sm text-gray-600">
                관리자 전용 API(`/api/gs-brochure/admin/*`)는 모카 인증(`auth`)을 사용하도록 통합되었습니다.
            </p>
            <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-gray-700">
                <li>기관 관리: <code>/api/gs-brochure/admin/institutions</code></li>
                <li>관리자 계정: <code>/api/gs-brochure/admin/users</code></li>
                <li>데이터 초기화: <code>/api/gs-brochure/admin/reset</code></li>
            </ul>
        </div>
    </div>
</x-layouts.app>
