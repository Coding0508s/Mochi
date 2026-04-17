<x-layouts.app title="GS Brochure Request">
    <div class="mochi-page max-w-4xl">
        <div class="mochi-summary-card">
            <h1 class="text-lg font-semibold text-[#2b78c5]">브로셔 신청</h1>
            <p class="mt-2 text-sm text-gray-600">
                이 페이지는 통합 API를 호출하는 시작점입니다. 현재 등록된 브로셔 수를 조회해 API 연결 상태를 확인합니다.
            </p>
            <div class="mt-4 rounded border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                <p>API Base: <code>/api/gs-brochure</code></p>
                <p id="gsb-request-status" class="mt-2">상태 확인 중...</p>
            </div>
        </div>
    </div>

    <script>
        (async () => {
            const status = document.getElementById('gsb-request-status');
            try {
                const res = await fetch('/api/gs-brochure/brochures');
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                status.textContent = `브로셔 ${Array.isArray(data) ? data.length : 0}건 조회 성공`;
            } catch (error) {
                status.textContent = 'API 연결 실패: ' + error.message;
            }
        })();
    </script>
</x-layouts.app>
