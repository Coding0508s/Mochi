<!DOCTYPE html>
<html class="light" lang="ko">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>운송장 입력 - BrochureSys</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#7f13ec",
                        "background-light": "#f7f6f8",
                        "background-dark": "#191022",
                    },
                    fontFamily: { "display": ["Inter", "sans-serif"] },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                },
            },
        }
    </script>
    <script src="{{ asset('js/xlsx.full.min.js') }}"></script>
    <script>window.API_BASE_URL = '{{ url("/api") }}';</script>
    <script src="{{ asset('js/api.js') }}"></script>
    <style>#logisticsSidebar.open{transform:translateX(0);}</style>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-slate-900 dark:text-white overflow-hidden">
    <header class="md:hidden sticky top-0 z-20 flex items-center justify-between px-4 py-3 bg-white dark:bg-[#1e1e1e] border-b border-slate-200 dark:border-slate-800">
        <button type="button" id="logisticsMenuBtn" class="p-2 -ml-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="메뉴 열기"><span class="material-symbols-outlined" style="font-size:24px;">menu</span></button>
        <div class="flex items-center gap-2"><span class="material-symbols-outlined text-primary" style="font-size:24px;">library_books</span><span class="font-bold text-slate-900 dark:text-white text-sm">운송장 입력</span></div>
        <div class="w-10"></div>
    </header>
    <div id="logisticsOverlay" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden" aria-hidden="true" onclick="document.getElementById('logisticsSidebar').classList.remove('open');this.classList.add('hidden');"></div>
    <div class="flex h-screen w-full">
        <div id="logisticsSidebar" class="fixed inset-y-0 left-0 z-30 w-64 flex flex-col bg-white dark:bg-[#1e1e1e] border-r border-slate-200 dark:border-slate-800 h-full -translate-x-full md:translate-x-0 md:relative transition-transform duration-200 shrink-0">
            <div class="p-6 pb-2">
                <div class="flex items-center gap-3 mb-8">
                    <div class="rounded-full size-10 bg-primary/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary" style="font-size: 24px;">library_books</span>
                    </div>
                    <div class="flex flex-col">
                        <h1 class="text-slate-900 dark:text-white text-base font-bold leading-normal">BrochureSys</h1>
                        <p class="text-slate-500 dark:text-slate-400 text-xs font-normal">Logistics</p>
                    </div>
                </div>
                <nav class="flex flex-col gap-1">
                    <a href="{{ url('/') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">home</span>
                        <span class="text-sm font-medium">메인</span>
                    </a>
                    <a href="{{ url('requestbrochure') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">add_circle</span>
                        <span class="text-sm font-medium">신청</span>
                    </a>
                    <a href="{{ url('requestbrochure-list') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">campaign</span>
                        <span class="text-sm font-medium">신청 내역</span>
                    </a>
                    <a href="{{ url('requestbrochure-logistics') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">local_shipping</span>
                        <span class="text-sm font-medium">운송장 입력</span>
                    </a>
                    <a href="{{ url('requestbrochure-completed') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">done_all</span>
                        <span class="text-sm font-medium">완료 내역</span>
                    </a>
                    <a href="{{ url('admin/login') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">admin_panel_settings</span>
                        <span class="text-sm font-medium">관리자</span>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-y-auto">
            <header class="w-full px-8 py-6 flex flex-wrap justify-between items-end gap-4 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-[#1e1e1e]/80 sticky top-0 z-10">
                <div class="flex flex-col gap-1">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">운송장 입력</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm">엑셀 파일을 먼저 다운로드 받은 후 신청된 브로셔의 운송장 번호를 입력하세요.</p>
                </div>
                <button type="button" onclick="downloadExcel()" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white transition-colors shadow-md">
                    <span class="material-symbols-outlined" style="font-size: 20px;">cloud_download</span>
                    엑셀 다운로드
                </button>
            </header>

            <div id="alert" class="mx-8 mt-4 hidden rounded-lg px-4 py-3 text-sm" role="alert"></div>

            <main class="flex-1 px-8 py-6">
                <form id="brochureForm">
                    <div class="bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">신청 내역 및 운송장 번호 입력</h2>
                        <div id="rowsContainer">
                            <!-- 동적 행 -->
                        </div>
                        <div class="flex flex-wrap justify-center items-center gap-4 mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                            <span class="text-slate-500 dark:text-slate-400 text-sm" id="paginationInfo"></span>
                            <ul class="flex flex-wrap list-none gap-2 p-0 m-0" id="pagination"></ul>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script>
        let rowCount = 0;
        let currentPage = 1;
        const itemsPerPage = 10;
        let allPendingRequests = [];

        async function loadBrochureOptions() {
            try {
                const brochures = await BrochureAPI.getAll();
                return brochures.map(b => ({ value: b.id, text: b.name }));
            } catch (error) {
                console.error('브로셔 옵션 로드 오류:', error);
                return [];
            }
        }

        async function addRowFromData(request, requestIndex, itemIndex, requestId) {
            rowCount++;
            const rowsContainer = document.getElementById('rowsContainer');
            const rowDiv = document.createElement('div');
            rowDiv.className = 'border-2 border-primary/30 rounded-xl p-4 mb-4 bg-white dark:bg-slate-800/50 dark:border-slate-700';
            rowDiv.id = 'row-' + rowCount;
            rowDiv.dataset.requestIndex = requestIndex;
            rowDiv.dataset.itemIndex = itemIndex;
            rowDiv.dataset.requestId = requestId;

            try {
                const brochureOptions = await loadBrochureOptions();
                let brochureOptionsHtml = '<option value="">선택하세요</option>';
                brochureOptions.forEach(option => {
                    const selected = option.value == request.brochure ? 'selected' : '';
                    brochureOptionsHtml += '<option value="' + option.value + '" ' + selected + '>' + (option.text || '') + '</option>';
                });

                rowDiv.innerHTML =
                    '<div class="flex flex-wrap gap-4 items-end mb-4 pb-3 border-b border-slate-200 dark:border-slate-700">' +
                    '<div class="min-w-[120px]"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">날짜</label><input type="date" id="date-' + rowCount + '" name="date-' + rowCount + '" value="' + (request.date || '') + '" disabled class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 cursor-not-allowed text-sm"></div>' +
                    '<div class="flex-1 min-w-[200px]"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">기관명</label><input type="text" id="schoolname-' + rowCount + '" name="schoolname-' + rowCount + '" value="' + (request.schoolname || '') + '" disabled class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white font-medium cursor-not-allowed text-sm"></div>' +
                    '<div class="min-w-[120px]"><span class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">담당자</span><span class="block text-sm text-slate-900 dark:text-white">' + (request.contactName || request.contact || '-') + '</span></div>' +
                    '</div>' +
                    '<div class="flex flex-wrap gap-4 mb-4">' +
                    '<div class="flex-1 min-w-[200px]"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">주소</label><input type="text" id="address-' + rowCount + '" name="address-' + rowCount + '" value="' + (request.address || '') + '" disabled class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 cursor-not-allowed text-sm"></div>' +
                    '<div class="min-w-[140px]"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">전화번호</label><input type="tel" id="phone-' + rowCount + '" name="phone-' + rowCount + '" value="' + (request.phone || '') + '" disabled class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 cursor-not-allowed text-sm"></div>' +
                    '</div>' +
                    '<div class="mb-4"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">브로셔 신청 내역</label><div id="brochure-list-' + rowCount + '" class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 text-sm text-slate-900 dark:text-white">' + (request.brochures && request.brochures.length > 0 ? request.brochures.map(function(b) { return (b.brochureName || '') + ' - ' + (b.quantity || 0) + '권'; }).join('<br>') : '브로셔 정보 없음') + '</div></div>' +
                    '<div class="mb-4"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">운송장 번호</label><div class="flex flex-wrap gap-2 items-end" id="invoice-container-' + rowCount + '"></div><button type="button" onclick="addInvoiceField(' + rowCount + ')" class="mt-2 flex items-center gap-1 px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors">+ 운송장 번호 추가</button></div>' +
                    '<div class="flex justify-end"><button type="button" onclick="saveSingleInvoice(' + rowCount + ',' + requestIndex + ',' + itemIndex + ')" class="px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white transition-colors">저장</button></div>';

                rowsContainer.appendChild(rowDiv);
                await new Promise(resolve => setTimeout(resolve, 0));
            } catch (error) {
                console.error('행 추가 오류:', error);
            }

            const currentRowCount = rowCount;
            if (request.invoices && request.invoices.length > 0) {
                request.invoices.forEach((invoice, index) => {
                    addInvoiceField(currentRowCount, index === 0);
                    const input = document.querySelector('#invoice-container-' + currentRowCount + ' input[type="text"]:last-of-type');
                    if (input) input.value = invoice;
                });
            } else {
                addInvoiceField(currentRowCount, true);
            }
        }

        function addInvoiceField(rowId, isDefault) {
            const container = document.getElementById('invoice-container-' + rowId);
            if (!container) return;
            const invoiceCount = container.querySelectorAll('.invoice-group').length;
            const invoiceId = 'invoice-' + rowId + '-' + (invoiceCount + 1);
            const invoiceGroup = document.createElement('div');
            invoiceGroup.className = 'invoice-group flex gap-2 items-end';
            invoiceGroup.id = 'invoice-group-' + invoiceId;
            const input = document.createElement('input');
            input.type = 'text';
            input.id = invoiceId;
            input.name = invoiceId;
            input.placeholder = '송장번호를 입력하세요';
            input.className = 'w-48 min-w-[120px] px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-medium';
            invoiceGroup.appendChild(input);
            if (!isDefault) {
                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'px-2 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium';
                deleteBtn.textContent = '삭제';
                deleteBtn.onclick = function() { invoiceGroup.remove(); };
                invoiceGroup.appendChild(deleteBtn);
            }
            container.appendChild(invoiceGroup);
        }

        function collectInvoiceFields(rowId) {
            const container = document.getElementById('invoice-container-' + rowId);
            if (!container) return [];
            const invoices = [];
            container.querySelectorAll('input[type="text"]').forEach(input => {
                if (input.value.trim()) invoices.push(input.value.trim());
            });
            return invoices;
        }

        function showAlert(message, type) {
            type = type || 'success';
            const alertDiv = document.getElementById('alert');
            alertDiv.className = 'mx-8 mt-4 rounded-lg px-4 py-3 text-sm ' + (type === 'danger' ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200' : 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200');
            alertDiv.textContent = message;
            alertDiv.classList.remove('hidden');
            setTimeout(function() { alertDiv.classList.add('hidden'); }, 3000);
        }

        async function saveSingleInvoice(rowId, requestIndex, itemIndex) {
            const invoices = collectInvoiceFields(rowId).filter(inv => inv && inv.trim() !== '');
            if (invoices.length === 0) {
                showAlert('운송장 번호를 입력해주세요.', 'danger');
                return;
            }
            try {
                const row = document.getElementById('row-' + rowId);
                const requestId = row ? row.dataset.requestId : null;
                if (!requestId) {
                    showAlert('요청 ID를 찾을 수 없습니다.', 'danger');
                    return;
                }
                await RequestAPI.addInvoices(requestId, invoices);
                showAlert('운송장 번호가 저장되었습니다!', 'success');
                setTimeout(function() { loadSavedRequests(); }, 500);
            } catch (error) {
                console.error('운송장 번호 저장 오류:', error);
                showAlert('운송장 번호 저장 중 오류가 발생했습니다: ' + (error.message || ''), 'danger');
            }
        }

        document.getElementById('brochureForm').addEventListener('submit', function(e) {
            e.preventDefault();
            showAlert('각 건마다 개별 저장 버튼을 사용해주세요.', 'danger');
        });

        async function loadSavedRequests() {
            try {
                const allRequests = await RequestAPI.getAll();
                const rowsContainer = document.getElementById('rowsContainer');
                rowsContainer.innerHTML = '';

                if (allRequests.length === 0) {
                    rowsContainer.innerHTML = '<p class="text-center text-slate-500 dark:text-slate-400 py-8">저장된 신청 내역이 없습니다.</p>';
                    document.getElementById('pagination').innerHTML = '';
                    document.getElementById('paginationInfo').textContent = '';
                    return;
                }

                allPendingRequests = [];
                allRequests.forEach(function(req) {
                    if (!req.invoices || req.invoices.length === 0 || req.invoices.every(function(inv) { return !inv || (inv.trim && inv.trim() === ''); })) {
                        const request = {
                            date: req.date,
                            schoolname: req.schoolname,
                            address: req.address,
                            phone: req.phone,
                            contact: req.contact_id,
                            contactName: req.contact_name,
                            brochures: (req.items || []).map(function(item) {
                                return { brochure: item.brochure_id, brochureName: item.brochure_name, quantity: item.quantity };
                            }),
                            invoices: req.invoices || []
                        };
                        allPendingRequests.push({ request: request, requestId: req.id });
                    }
                });

                if (allPendingRequests.length === 0) {
                    rowsContainer.innerHTML = '<p class="text-center text-slate-500 dark:text-slate-400 py-8">운송장 번호 입력이 필요한 신청 내역이 없습니다.</p>';
                    document.getElementById('pagination').innerHTML = '';
                    document.getElementById('paginationInfo').textContent = '';
                    return;
                }

                currentPage = 1;
                displayPagedRequests();
            } catch (error) {
                console.error('신청 내역 로드 오류:', error);
                showAlert('신청 내역을 불러오는 중 오류가 발생했습니다.', 'danger');
            }
        }

        async function displayPagedRequests() {
            const rowsContainer = document.getElementById('rowsContainer');
            rowsContainer.innerHTML = '';

            const totalItems = allPendingRequests.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const startIndex = (currentPage - 1) * itemsPerPage;
            const pageItems = allPendingRequests.slice(startIndex, startIndex + itemsPerPage);

            for (let i = 0; i < pageItems.length; i++) {
                const item = pageItems[i];
                await addRowFromData(item.request, startIndex + i, 0, item.requestId);
            }

            const pagination = document.getElementById('pagination');
            const paginationInfo = document.getElementById('paginationInfo');
            pagination.innerHTML = '';

            if (totalPages <= 1) {
                paginationInfo.textContent = '총 ' + totalItems + '개';
                return;
            }

            paginationInfo.textContent = '총 ' + totalItems + '개 중 ' + (startIndex + 1) + '-' + Math.min(startIndex + itemsPerPage, totalItems) + '개 표시';

            var prevLi = document.createElement('li');
            prevLi.innerHTML = '<button type="button" onclick="goToPage(' + (currentPage - 1) + ')" ' + (currentPage === 1 ? 'disabled' : '') + ' class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium ' + (currentPage === 1 ? 'text-slate-400 cursor-not-allowed' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">이전</button>';
            pagination.appendChild(prevLi);

            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, currentPage + 2);
            if (startPage > 1) {
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToPage(1)" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">1</button>';
                pagination.appendChild(li);
                if (startPage > 2) {
                    var d = document.createElement('li');
                    d.innerHTML = '<span class="px-2 text-slate-400">...</span>';
                    pagination.appendChild(d);
                }
            }
            for (var p = startPage; p <= endPage; p++) {
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToPage(' + p + ')" class="px-3 py-1.5 rounded-lg border text-sm font-medium ' + (p === currentPage ? 'bg-primary border-primary text-white' : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">' + p + '</button>';
                pagination.appendChild(li);
            }
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    var d = document.createElement('li');
                    d.innerHTML = '<span class="px-2 text-slate-400">...</span>';
                    pagination.appendChild(d);
                }
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToPage(' + totalPages + ')" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">' + totalPages + '</button>';
                pagination.appendChild(li);
            }

            var nextLi = document.createElement('li');
            nextLi.innerHTML = '<button type="button" onclick="goToPage(' + (currentPage + 1) + ')" ' + (currentPage === totalPages ? 'disabled' : '') + ' class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium ' + (currentPage === totalPages ? 'text-slate-400 cursor-not-allowed' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">다음</button>';
            pagination.appendChild(nextLi);
        }

        async function goToPage(page) {
            var totalPages = Math.ceil(allPendingRequests.length / itemsPerPage);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            await displayPagedRequests();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function downloadExcel() {
            if (typeof XLSX === 'undefined') { showAlert('엑셀 라이브러리를 불러오지 못했습니다. 페이지를 새로고침한 후 다시 시도해주세요.', 'danger'); return; }
            if (allPendingRequests.length === 0) {
                showAlert('다운로드할 신청 내역이 없습니다.', 'danger');
                return;
            }
            var excelData = [[
                '배송메세지1', '받는분성명', '받는분전화번호', '받는분주소(전체, 분할)',
                '내품코드', '내품명', '내품수량', '박스타입', '운임구분', '운송장번호', ''
            ]];
            allPendingRequests.forEach(function(item) {
                var request = item.request;
                if (request.brochures && request.brochures.length > 0) {
                    request.brochures.forEach(function(brochure) {
                        excelData.push([
                            '브로셔', request.schoolname || '', request.phone || '', request.address || '',
                            'Brochure', brochure.brochureName || '', brochure.quantity || '', '', '',
                            request.invoices && request.invoices.length > 0 ? request.invoices.join(', ') : '', ''
                        ]);
                    });
                } else {
                    excelData.push([
                        '브로셔', request.schoolname || '', request.phone || '', request.address || '',
                        'Brochure', '', '', '', '', request.invoices && request.invoices.length > 0 ? request.invoices.join(', ') : '', ''
                    ]);
                }
            });
            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(excelData);
            ws['!cols'] = [{ wch: 15 }, { wch: 15 }, { wch: 18 }, { wch: 40 }, { wch: 12 }, { wch: 30 }, { wch: 12 }, { wch: 12 }, { wch: 12 }, { wch: 20 }, { wch: 10 }];
            XLSX.utils.book_append_sheet(wb, ws, '신청 내역');
            var dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
            XLSX.writeFile(wb, '브로셔_신청내역_' + dateStr + '.xlsx');
            showAlert('엑셀 파일이 다운로드되었습니다.', 'success');
        }

        (function(){
            var btn=document.getElementById('logisticsMenuBtn'),sb=document.getElementById('logisticsSidebar'),ov=document.getElementById('logisticsOverlay');
            if(btn&&sb&&ov){
                btn.addEventListener('click',function(){sb.classList.toggle('open');ov.classList.toggle('hidden',!sb.classList.contains('open'));});
                sb.querySelectorAll('nav a').forEach(function(a){a.addEventListener('click',function(){sb.classList.remove('open');ov.classList.add('hidden');});});
            }
        })();
        window.addEventListener('DOMContentLoaded', function() {
            loadSavedRequests();
        });
    </script>
</body>
</html>
