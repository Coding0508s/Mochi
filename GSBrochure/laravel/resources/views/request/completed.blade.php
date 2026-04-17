<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>운송장 입력 완료 내역</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: '맑은 고딕', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #440b86;
            text-align: center;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }

        .search-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .search-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .search-group {
            flex: 1;
            min-width: 200px;
        }

        .search-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .search-group input,
        .search-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: #440b86;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0ca22c;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .card-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
        }

        .request-card {
            border: 2px solid #440b86;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: white;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #440b86;
        }

        .request-title {
            font-size: 18px;
            font-weight: bold;
            color: #440b86;
        }

        .request-date {
            color: #666;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            background-color: #28a745;
            color: white;
        }

        .request-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            font-size: 12px;
            color: #666;
        }

        .info-value {
            color: #333;
            font-size: 14px;
        }

        .brochure-list {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .brochure-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }

        .brochure-name {
            flex: 1;
            font-weight: bold;
        }

        .brochure-quantity {
            color: #440b86;
            font-weight: bold;
            margin-right: 20px;
        }

        .invoice-list {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .invoice-item {
            display: inline-block;
            padding: 5px 10px;
            margin: 5px 5px 5px 0;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            font-size: 12px;
            color: #155724;
            font-weight: bold;
        }

        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .stat-item {
            flex: 1;
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #440b86;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .nav-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #440b86;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s;
        }

        .nav-link:hover {
            color: #0ca22c;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 30px 0;
            gap: 10px;
        }

        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 5px;
        }

        .pagination li {
            display: inline-block;
        }

        .pagination a {
            display: block;
            padding: 8px 12px;
            text-decoration: none;
            color: #440b86;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .pagination a:hover {
            background-color: #440b86;
            color: white;
        }

        .pagination .active a {
            background-color: #440b86;
            color: white;
        }

        .pagination .disabled a {
            color: #ccc;
            pointer-events: none;
            cursor: not-allowed;
        }

        .pagination-info {
            color: #666;
            font-size: 14px;
            margin: 0 15px;
        }

        /* 반응형 디자인 */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }

            h1 {
                font-size: 24px;
            }

            .request-info {
                grid-template-columns: 1fr;
            }

            .request-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-row {
                flex-direction: column;
            }

            .search-group {
                min-width: 100%;
            }

            .request-card {
                padding: 15px;
            }

            .card-actions {
                flex-direction: column;
            }

            .card-actions .btn {
                width: 100%;
            }

            .stats {
                flex-direction: column;
                gap: 10px;
            }

            .pagination-container {
                flex-wrap: wrap;
            }

            .btn {
                width: 100%;
                margin-bottom: 5px;
            }
        }

        @media screen and (max-width: 480px) {
            .container {
                padding: 10px;
            }

            h1 {
                font-size: 20px;
            }

            .request-card {
                padding: 10px;
            }

            input, select {
                font-size: 16px; /* iOS 줌 방지 */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
            <a href="{{ url('requestbrochure') }}" class="nav-link">← 신청 페이지로 돌아가기</a>
            <a href="{{ url('requestbrochure-logistics') }}" class="nav-link">← 운송장 입력 페이지로 돌아가기</a>
        </div>
        <h1>운송장 입력 완료 내역</h1>
        <p class="subtitle">운송장 번호가 입력 완료된 신청 내역을 확인할 수 있습니다.</p>

        <div class="search-section">
            <div class="search-row">
                <div class="search-group">
                    <label>기관명 검색</label>
                    <input type="text" id="searchSchool" placeholder="기관명을 입력하세요" onkeyup="filterRequests()">
                </div>
                <div class="search-group">
                    <label>날짜 검색</label>
                    <input type="date" id="searchDate" onchange="filterRequests()">
                </div>
                <div class="search-group">
                    <label>운송장 번호 검색</label>
                    <input type="text" id="searchInvoice" placeholder="운송장 번호를 입력하세요" onkeyup="filterRequests()">
                </div>
                <div class="search-group">
                    <button class="btn btn-primary" onclick="filterRequests()">검색</button>
                </div>
            </div>
        </div>

        <div class="stats" id="stats">
            <div class="stat-item">
                <div class="stat-number" id="totalRequests">0</div>
                <div class="stat-label">완료된 신청 건수</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="totalBrochures">0</div>
                <div class="stat-label">총 브로셔 수량</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="totalInvoices">0</div>
                <div class="stat-label">총 운송장 번호</div>
            </div>
        </div>

        <div id="requestsContainer">
            <!-- 운송장 입력 완료 내역이 여기에 표시됩니다 -->
        </div>

        <div class="pagination-container">
            <div class="pagination-info" id="paginationInfo"></div>
            <ul class="pagination" id="pagination">
                <!-- 페이지네이션 버튼이 여기에 동적으로 추가됩니다 -->
            </ul>
        </div>
    </div>

    <script>window.API_BASE_URL = '{{ url("/api") }}';</script>
    <script src="{{ asset('js/api.js') }}"></script>
    <script>
        let allRequests = [];
        let filteredRequests = [];
        let currentPage = 1;
        const itemsPerPage = 10;

        // 알림 표시 함수
        function showAlert(message, type = 'success') {
            // 간단한 alert로 대체
            alert(message);
        }

        // 저장된 신청 내역 로드 (운송장이 입력된 것만)
        async function loadRequests() {
            try {
                const requests = await RequestAPI.getAll();
                
                // 운송장이 입력된 신청만 필터링 (빈 배열이나 빈 문자열만 있는 경우 제외)
                const completed = requests.filter(req => {
                    if (!req.invoices || req.invoices.length === 0) {
                        return false;
                    }
                    // 실제로 값이 있는 운송장 번호가 있는지 확인
                    return req.invoices.some(inv => inv && inv.trim() !== '');
                });
                
                // API 응답을 기존 형식으로 변환
                allRequests = completed.map(req => ({
                    id: req.id,
                    requests: [{
                        id: req.id,
                        date: req.date,
                        schoolname: req.schoolname,
                        address: req.address,
                        phone: req.phone,
                        contact: req.contact_id,
                        contactName: req.contact_name,
                        // API에서 받은 items를 기존 형식으로 변환 (brochure_name -> brochureName)
                        brochures: (req.items || []).map(item => ({
                            brochure: item.brochure_id,
                            brochureName: item.brochure_name,
                            quantity: item.quantity
                        })),
                        invoices: req.invoices || []
                    }],
                    submittedAt: req.submitted_at
                }));
            
                filteredRequests = [...allRequests];
                currentPage = 1;
                displayRequests();
                updateStats();
            } catch (error) {
                console.error('신청 내역 로드 오류:', error);
                showAlert('신청 내역을 불러오는 중 오류가 발생했습니다.', 'danger');
            }
        }

        // 모든 신청을 평면 배열로 변환
        function flattenRequests() {
            const flatList = [];
            filteredRequests.forEach((requestGroup, groupIndex) => {
                if (requestGroup.requests && requestGroup.requests.length > 0) {
                    requestGroup.requests.forEach((request, requestIndex) => {
                        flatList.push({
                            request: request,
                            groupIndex: groupIndex,
                            requestIndex: requestIndex
                        });
                    });
                }
            });
            return flatList;
        }

        // 신청 내역 표시
        function displayRequests() {
            const container = document.getElementById('requestsContainer');
            container.innerHTML = '';

            const flatList = flattenRequests();
            const totalItems = flatList.length;

            if (totalItems === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <p>운송장이 입력 완료된 신청 내역이 없습니다.</p>
                    </div>
                `;
                document.getElementById('pagination').innerHTML = '';
                document.getElementById('paginationInfo').textContent = '';
                return;
            }

            // 페이지네이션 계산
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageItems = flatList.slice(startIndex, endIndex);

            // 현재 페이지의 항목만 표시
            pageItems.forEach(item => {
                const requestCard = createRequestCard(item.request, item.groupIndex, item.requestIndex);
                container.appendChild(requestCard);
            });

            // 페이지네이션 UI 업데이트
            updatePagination(totalPages, totalItems);
        }

        // 페이지네이션 UI 업데이트
        function updatePagination(totalPages, totalItems) {
            const pagination = document.getElementById('pagination');
            const paginationInfo = document.getElementById('paginationInfo');
            
            pagination.innerHTML = '';
            
            if (totalPages <= 1) {
                paginationInfo.textContent = `총 ${totalItems}개`;
                return;
            }

            // 이전 버튼
            const prevLi = document.createElement('li');
            prevLi.className = currentPage === 1 ? 'disabled' : '';
            prevLi.innerHTML = `<a onclick="goToPage(${currentPage - 1})">이전</a>`;
            pagination.appendChild(prevLi);

            // 페이지 번호들
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) {
                const firstLi = document.createElement('li');
                firstLi.innerHTML = `<a onclick="goToPage(1)">1</a>`;
                pagination.appendChild(firstLi);
                if (startPage > 2) {
                    const dotsLi = document.createElement('li');
                    dotsLi.className = 'disabled';
                    dotsLi.innerHTML = '<a>...</a>';
                    pagination.appendChild(dotsLi);
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = i === currentPage ? 'active' : '';
                li.innerHTML = `<a onclick="goToPage(${i})">${i}</a>`;
                pagination.appendChild(li);
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const dotsLi = document.createElement('li');
                    dotsLi.className = 'disabled';
                    dotsLi.innerHTML = '<a>...</a>';
                    pagination.appendChild(dotsLi);
                }
                const lastLi = document.createElement('li');
                lastLi.innerHTML = `<a onclick="goToPage(${totalPages})">${totalPages}</a>`;
                pagination.appendChild(lastLi);
            }

            // 다음 버튼
            const nextLi = document.createElement('li');
            nextLi.className = currentPage === totalPages ? 'disabled' : '';
            nextLi.innerHTML = `<a onclick="goToPage(${currentPage + 1})">다음</a>`;
            pagination.appendChild(nextLi);

            // 페이지 정보
            const startItem = (currentPage - 1) * itemsPerPage + 1;
            const endItem = Math.min(currentPage * itemsPerPage, totalItems);
            paginationInfo.textContent = `총 ${totalItems}개 중 ${startItem}-${endItem}개 표시`;
        }

        // 페이지 이동
        function goToPage(page) {
            const flatList = flattenRequests();
            const totalPages = Math.ceil(flatList.length / itemsPerPage);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            displayRequests();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // 신청 카드 생성
        function createRequestCard(request, groupIndex, requestIndex) {
            const card = document.createElement('div');
            card.className = 'request-card';

            // 브로셔 목록 생성
            let brochureListHtml = '';
            if (request.brochures && request.brochures.length > 0) {
                request.brochures.forEach(brochure => {
                    brochureListHtml += `
                        <div class="brochure-item">
                            <span class="brochure-name">${brochure.brochureName}</span>
                            <span class="brochure-quantity">${brochure.quantity}권</span>
                        </div>
                    `;
                });
            }

            // 송장번호 목록 생성
            let invoiceListHtml = '';
            if (request.invoices && request.invoices.length > 0) {
                request.invoices.forEach(invoice => {
                    invoiceListHtml += `<span class="invoice-item">${invoice}</span>`;
                });
            }

            card.innerHTML = `
                <div class="request-header">
                    <div>
                        <div class="request-title">${request.schoolname}</div>
                        <div class="request-date">신청일: ${formatDate(request.date)}</div>
                    </div>
                    <span class="status-badge">배송 준비 완료</span>
                </div>
                <div class="request-info">
                    <div class="info-item">
                        <div class="info-label">기관명</div>
                        <div class="info-value">${request.schoolname}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">주소</div>
                        <div class="info-value">${request.address}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">전화번호</div>
                        <div class="info-value">${request.phone}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">담당자</div>
                        <div class="info-value">${request.contactName || request.contact || '-'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">신청일</div>
                        <div class="info-value">${formatDate(request.date)}</div>
                    </div>
                </div>
                <div class="brochure-list">
                    <div class="info-label" style="margin-bottom: 10px;">신청 브로셔</div>
                    ${brochureListHtml}
                </div>
                <div class="invoice-list">
                    <div class="info-label" style="margin-bottom: 10px;">운송장 번호</div>
                    ${invoiceListHtml}
                </div>
                <div class="card-actions">
                    <button class="btn btn-warning" onclick="cancelInvoice(${groupIndex}, ${requestIndex})">운송장 입력 취소</button>
                </div>
            `;

            return card;
        }

        // 날짜 포맷팅
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('ko-KR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // 필터링
        function filterRequests() {
            const schoolSearch = document.getElementById('searchSchool').value.toLowerCase();
            const dateSearch = document.getElementById('searchDate').value;
            const invoiceSearch = document.getElementById('searchInvoice').value.toLowerCase();

            filteredRequests = allRequests.map(requestGroup => {
                const filtered = requestGroup.requests.filter(request => {
                    const matchSchool = !schoolSearch || request.schoolname.toLowerCase().includes(schoolSearch);
                    const matchDate = !dateSearch || request.date === dateSearch;
                    const matchInvoice = !invoiceSearch || 
                        (request.invoices && request.invoices.some(inv => 
                            inv.toLowerCase().includes(invoiceSearch)
                        ));

                    return matchSchool && matchDate && matchInvoice;
                });

                return {
                    ...requestGroup,
                    requests: filtered
                };
            }).filter(group => group.requests.length > 0);

            currentPage = 1;
            displayRequests();
            updateStats();
        }

        // 필터 초기화
        function clearFilters() {
            document.getElementById('searchSchool').value = '';
            document.getElementById('searchDate').value = '';
            document.getElementById('searchInvoice').value = '';
            filteredRequests = [...allRequests];
            currentPage = 1;
            displayRequests();
            updateStats();
        }

        // 운송장 입력 취소 함수
        async function cancelInvoice(groupIndex, requestIndex) {
            if (!confirm('운송장 입력을 취소하시겠습니까? 취소된 건은 다시 신청 내역 및 운송장 번호 입력 페이지에 표시됩니다.')) {
                return;
            }

            try {
                // 필터링된 배열에서 해당 요청 찾기
                const targetRequest = filteredRequests[groupIndex]?.requests[requestIndex];
                if (!targetRequest) {
                    alert('신청 내역을 찾을 수 없습니다.');
                    return;
                }

                // 요청 ID 가져오기
                const requestId = targetRequest.id || allRequests[groupIndex]?.id || allRequests[groupIndex]?.requests?.[requestIndex]?.id;
                
                if (!requestId) {
                    alert('요청 ID를 찾을 수 없습니다.');
                    return;
                }

                // API를 통해 운송장 번호 삭제
                await RequestAPI.deleteInvoices(requestId);
                
                alert('운송장 입력이 취소되었습니다. 해당 건은 다시 신청 내역 및 운송장 번호 입력 페이지에 표시됩니다.');
                
                // 페이지 새로고침하여 목록 업데이트
                await loadRequests();
            } catch (error) {
                console.error('운송장 취소 오류:', error);
                alert('운송장 취소 중 오류가 발생했습니다: ' + error.message);
            }
        }

        // 통계 업데이트
        function updateStats() {
            let totalRequests = 0;
            let totalBrochures = 0;
            let totalInvoices = 0;

            filteredRequests.forEach(requestGroup => {
                if (requestGroup.requests) {
                    requestGroup.requests.forEach(request => {
                        totalRequests++;
                        if (request.brochures) {
                            request.brochures.forEach(b => {
                                totalBrochures += parseInt(b.quantity) || 0;
                            });
                        }
                        if (request.invoices && request.invoices.length > 0) {
                            totalInvoices += request.invoices.length;
                        }
                    });
                }
            });

            document.getElementById('totalRequests').textContent = totalRequests;
            document.getElementById('totalBrochures').textContent = totalBrochures;
            document.getElementById('totalInvoices').textContent = totalInvoices;
        }

        // 페이지 로드 시 데이터 불러오기
        window.addEventListener('DOMContentLoaded', function() {
            loadRequests();
        });
    </script>
</body>
</html>

