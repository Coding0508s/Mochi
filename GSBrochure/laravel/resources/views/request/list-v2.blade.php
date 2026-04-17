@extends('layouts.shell-public')

@section('title', '신청 내역 조회')

@section('sidebar-footer-label', '신청 내역 조회')

@section('content')
    <header class="mb-8 max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-primary dark:text-purple-400 mb-2">신청 내역 조회</h1>
        <p class="text-gray-600 dark:text-gray-300">기관명 또는 전화번호로 본인 신청 내역만 조회할 수 있습니다.</p>
    </header>

    <div id="alert" class="max-w-5xl mx-auto mb-6 hidden rounded-lg border p-4" role="alert"></div>

    <div class="max-w-5xl mx-auto space-y-6">
        <div id="stats" class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark p-5 text-center shadow-sm">
                <div id="totalRequests" class="text-2xl font-bold text-primary">0</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">총 신청 건수</div>
            </div>
            <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark p-5 text-center shadow-sm">
                <div id="pendingInvoice" class="text-2xl font-bold text-primary">0</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">운송장 입력 대기</div>
            </div>
            <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark p-5 text-center shadow-sm">
                <div id="withInvoice" class="text-2xl font-bold text-primary">0</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">운송장 등록 완료</div>
            </div>
        </div>

        <section class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <span class="material-icons text-primary text-xl">search</span>
                조회
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">기관명 또는 전화번호를 입력한 후 조회해 주세요. 입력한 정보와 일치하는 신청 내역만 표시됩니다.</p>
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[180px] space-y-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">기관명</label>
                    <input type="text" id="searchSchool" placeholder="기관명을 입력하세요" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5">
                </div>
                <div class="flex-1 min-w-[180px] space-y-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">전화번호</label>
                    <input type="text" id="searchPhone" placeholder="전화번호를 입력하세요" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5">
                </div>
                <button type="button" id="searchBtn" class="px-5 py-2.5 bg-primary hover:bg-purple-800 text-white font-medium rounded-lg shadow-sm transition-colors flex items-center gap-2">
                    <span class="material-icons text-sm">search</span>
                    조회
                </button>
            </div>
        </section>

        <div id="requestsContainer" class="space-y-6">
            <div class="empty-state text-center py-16 px-4 text-gray-500 dark:text-gray-400">
                <span class="material-icons text-6xl mb-4 block opacity-50">person_search</span>
                <p>기관명 또는 전화번호를 입력한 후 조회해 주세요.</p>
            </div>
        </div>

        <div class="flex flex-wrap justify-center items-center gap-4 py-6">
            <div id="paginationInfo" class="text-sm text-gray-600 dark:text-gray-400"></div>
            <ul id="pagination" class="flex flex-wrap list-none p-0 m-0 gap-2"></ul>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/api.js') }}"></script>
<script>
    let allRequests = [];
    let filteredRequests = [];
    let currentPage = 1;
    const itemsPerPage = 10;

    async function loadBrochureOptions() {
        try {
            const brochures = await BrochureAPI.getAll();
            return brochures.map(b => ({ value: b.id, text: b.name }));
        } catch (error) {
            console.error('브로셔 옵션 로드 오류:', error);
            return [];
        }
    }

    async function loadContactOptions() {
        try {
            const contacts = await ContactAPI.getAll();
            return contacts.map(c => ({ value: c.id, text: c.name }));
        } catch (error) {
            console.error('담당자 옵션 로드 오류:', error);
            return [];
        }
    }

    async function doSearch() {
        const schoolname = (document.getElementById('searchSchool') && document.getElementById('searchSchool').value) ? document.getElementById('searchSchool').value.trim() : '';
        const phone = (document.getElementById('searchPhone') && document.getElementById('searchPhone').value) ? document.getElementById('searchPhone').value.trim() : '';
        if (!schoolname && !phone) {
            showAlert('기관명 또는 전화번호를 입력해 주세요.', 'danger');
            return;
        }
        const btn = document.getElementById('searchBtn');
        if (btn) { btn.disabled = true; btn.textContent = '조회 중...'; }
        try {
            const requests = await RequestAPI.search({ schoolname: schoolname || null, phone: phone || null });
            allRequests = requests.map(req => ({
                id: req.id,
                requests: [{
                    id: req.id,
                    date: req.date,
                    schoolname: req.schoolname,
                    address: req.address,
                    phone: req.phone,
                    contact: req.contact_id,
                    contactName: req.contact_name,
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
        } catch (err) {
            showAlert(err.message || '조회 중 오류가 발생했습니다.', 'danger');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = '조회'; }
        }
    }

    function flattenRequests() {
        const flatList = [];
        filteredRequests.forEach((requestGroup, groupIndex) => {
            if (requestGroup.requests && requestGroup.requests.length > 0) {
                requestGroup.requests.forEach((request, requestIndex) => {
                    flatList.push({ request: request, groupIndex: groupIndex, requestIndex: requestIndex });
                });
            }
        });
        return flatList;
    }

    function displayRequests() {
        const container = document.getElementById('requestsContainer');
        if (!container) return;
        container.innerHTML = '';
        const flatList = flattenRequests();
        const totalItems = flatList.length;

        if (totalItems === 0) {
            container.innerHTML = `
                <div class="empty-state text-center py-16 px-4 text-gray-500 dark:text-gray-400">
                    <span class="material-icons text-6xl mb-4 block opacity-50">description</span>
                    <p>일치하는 신청 내역이 없습니다.</p>
                </div>
            `;
            document.getElementById('pagination').innerHTML = '';
            document.getElementById('paginationInfo').textContent = '';
            return;
        }

        const totalPages = Math.ceil(totalItems / itemsPerPage);
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageItems = flatList.slice(startIndex, endIndex);

        pageItems.forEach(item => {
            const requestCard = createRequestCard(item.request, item.groupIndex, item.requestIndex);
            container.appendChild(requestCard);
        });

        updatePagination(totalPages, totalItems);
    }

    function updatePagination(totalPages, totalItems) {
        const pagination = document.getElementById('pagination');
        const paginationInfo = document.getElementById('paginationInfo');
        if (!pagination || !paginationInfo) return;
        pagination.innerHTML = '';
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;

        if (totalPages <= 1) {
            paginationInfo.textContent = `총 ${totalItems}개`;
            return;
        }

        const prevLi = document.createElement('li');
        prevLi.className = currentPage === 1 ? 'opacity-50 pointer-events-none' : '';
        prevLi.innerHTML = `<a onclick="goToPage(${currentPage - 1})" class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-primary hover:text-white hover:border-primary transition-colors cursor-pointer text-sm">이전</a>`;
        pagination.appendChild(prevLi);

        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            const firstLi = document.createElement('li');
            firstLi.innerHTML = `<a onclick="goToPage(1)" class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-primary hover:text-white hover:border-primary transition-colors cursor-pointer text-sm">1</a>`;
            pagination.appendChild(firstLi);
            if (startPage > 2) {
                const dotsLi = document.createElement('li');
                dotsLi.innerHTML = '<span class="px-2 py-2 text-gray-500">...</span>';
                pagination.appendChild(dotsLi);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const li = document.createElement('li');
            const activeClass = i === currentPage ? 'bg-primary text-white border-primary' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-primary hover:text-white hover:border-primary';
            li.innerHTML = `<a onclick="goToPage(${i})" class="inline-flex items-center px-3 py-2 rounded-lg border transition-colors cursor-pointer text-sm ${activeClass}">${i}</a>`;
            pagination.appendChild(li);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const dotsLi = document.createElement('li');
                dotsLi.innerHTML = '<span class="px-2 py-2 text-gray-500">...</span>';
                pagination.appendChild(dotsLi);
            }
            const lastLi = document.createElement('li');
            lastLi.innerHTML = `<a onclick="goToPage(${totalPages})" class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-primary hover:text-white hover:border-primary transition-colors cursor-pointer text-sm">${totalPages}</a>`;
            pagination.appendChild(lastLi);
        }

        const startItem = startIndex + 1;
        const endItem = Math.min(endIndex, totalItems);
        paginationInfo.textContent = `총 ${totalItems}개 중 ${startItem}-${endItem}개 표시`;
    }

    function goToPage(page) {
        const flatList = flattenRequests();
        const totalPages = Math.ceil(flatList.length / itemsPerPage);
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        displayRequests();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function createRequestCard(request, groupIndex, requestIndex) {
        const card = document.createElement('div');
        card.className = 'request-card bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border-2 border-primary/30 dark:border-primary/40 overflow-hidden';

        let brochureListHtml = '';
        if (request.brochures && request.brochures.length > 0) {
            request.brochures.forEach(brochure => {
                brochureListHtml += `
                    <div class="brochure-item flex justify-between items-center py-2.5 px-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg mb-2 last:mb-0">
                        <span class="brochure-name font-medium text-gray-900 dark:text-white">${brochure.brochureName}</span>
                        <span class="brochure-quantity font-semibold text-primary">${brochure.quantity}권</span>
                    </div>
                `;
            });
        }

        const hasInvoices = request.invoices && request.invoices.length > 0 && request.invoices.some(inv => inv && inv.trim() !== '');
        let invoiceListHtml = '';
        if (hasInvoices) {
            request.invoices.forEach(invoice => {
                if (invoice && invoice.trim() !== '') {
                    invoiceListHtml += `<span class="invoice-item inline-block py-1.5 px-2.5 mr-2 mb-2 bg-gray-200 dark:bg-gray-700 rounded text-xs">${invoice}</span>`;
                }
            });
        } else {
            invoiceListHtml = '<span class="no-invoice text-gray-500 dark:text-gray-400 italic text-sm">배송 대기중</span>';
        }

        const statusBadge = hasInvoices
            ? '<span class="status-badge completed inline-block py-1.5 px-4 rounded-full text-xs font-semibold bg-green-500 text-white">배송완료</span>'
            : '<span class="status-badge pending inline-block py-1.5 px-4 rounded-full text-xs font-semibold bg-amber-400 text-gray-900">배송 대기중</span>';

        const cardId = `card-${groupIndex}-${requestIndex}`;
        const isEditable = !hasInvoices;
        card.id = cardId;
        card.dataset.groupIndex = groupIndex;
        card.dataset.requestIndex = requestIndex;

        const editButton = isEditable
            ? `<div class="card-actions mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                <button type="button" class="m-[5px] px-4 py-2 bg-primary hover:bg-purple-800 text-white font-medium rounded-lg transition-colors" onclick="editRequest('${cardId}')">수정</button>
               </div>`
            : '';

        card.innerHTML = `
            <div class="request-header flex flex-wrap justify-between items-start gap-4 p-6 pb-4 border-b-2 border-primary/20">
                <div>
                    <div class="request-title text-lg font-bold text-primary">${request.schoolname}</div>
                    <div class="request-date text-sm text-gray-500 dark:text-gray-400 mt-1">신청일: ${formatDate(request.date)}</div>
                </div>
                ${statusBadge}
            </div>
            <div class="request-info grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-6">
                <div class="info-item space-y-1">
                    <div class="info-label text-xs font-medium text-gray-500 dark:text-gray-400">기관명</div>
                    <div class="info-value text-sm text-gray-900 dark:text-white" data-field="schoolname">${request.schoolname}</div>
                </div>
                <div class="info-item space-y-1">
                    <div class="info-label text-xs font-medium text-gray-500 dark:text-gray-400">주소</div>
                    <div class="info-value text-sm text-gray-900 dark:text-white" data-field="address">${request.address}</div>
                </div>
                <div class="info-item space-y-1">
                    <div class="info-label text-xs font-medium text-gray-500 dark:text-gray-400">전화번호</div>
                    <div class="info-value text-sm text-gray-900 dark:text-white" data-field="phone">${formatPhoneDisplay(request.phone)}</div>
                </div>
                <div class="info-item space-y-1">
                    <div class="info-label text-xs font-medium text-gray-500 dark:text-gray-400">담당자</div>
                    <div class="info-value text-sm text-gray-900 dark:text-white" data-field="contactName">${request.contactName || request.contact || '-'}</div>
                </div>
                <div class="info-item space-y-1">
                    <div class="info-label text-xs font-medium text-gray-500 dark:text-gray-400">신청일</div>
                    <div class="info-value text-sm text-gray-900 dark:text-white" data-field="date">${formatDate(request.date)}</div>
                </div>
            </div>
            <div class="brochure-list px-6 pb-4" data-brochures="${encodeURIComponent(JSON.stringify(request.brochures || []))}">
                <div class="info-label text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">신청 브로셔</div>
                <div class="brochure-items-container">${brochureListHtml}</div>
            </div>
            <div class="invoice-list px-6 pb-4">
                <div class="info-label text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">운송장 번호</div>
                ${invoiceListHtml}
            </div>
            ${editButton}
        `;

        return card;
    }

    async function editRequest(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;

        const groupIndex = parseInt(card.dataset.groupIndex);
        const requestIndex = parseInt(card.dataset.requestIndex);
        const request = filteredRequests[groupIndex]?.requests[requestIndex];
        if (!request) return;

        try {
            const infoValues = card.querySelectorAll('.info-value');
            for (const item of infoValues) {
                const field = item.dataset.field;
                const currentValue = item.textContent.trim();

                if (field === 'date') {
                    const dateValue = request.date || '';
                    item.innerHTML = `<input type="date" value="${dateValue}" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white sm:text-sm py-2 px-2">`;
                } else if (field === 'contactName') {
                    const contactOptions = await loadContactOptions();
                    let optionsHtml = '<option value="">선택하세요</option>';
                    contactOptions.forEach(opt => {
                        const selected = opt.value == request.contact ? 'selected' : '';
                        optionsHtml += `<option value="${opt.value}" ${selected}>${opt.text}</option>`;
                    });
                    item.innerHTML = `<select class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white sm:text-sm py-2 px-2">${optionsHtml}</select>`;
                } else {
                    item.innerHTML = `<input type="text" value="${currentValue}" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white sm:text-sm py-2 px-2">`;
                }
            }

            const brochureContainer = card.querySelector('.brochure-items-container');
            if (brochureContainer && request.brochures) {
                const brochureOptions = await loadBrochureOptions();
                let brochureEditHtml = '';
                request.brochures.forEach((brochure, index) => {
                    let optionsHtml = '<option value="">선택하세요</option>';
                    brochureOptions.forEach(opt => {
                        const selected = opt.value == brochure.brochure ? 'selected' : '';
                        optionsHtml += `<option value="${opt.value}" ${selected}>${opt.text}</option>`;
                    });
                    brochureEditHtml += `
                        <div class="brochure-item-edit flex gap-2 items-center mb-2" data-index="${index}">
                            <select data-field="brochure" class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white sm:text-sm py-2 px-2">${optionsHtml}</select>
                            <input type="number" data-field="quantity" value="${brochure.quantity}" min="10" step="10" class="w-24 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white sm:text-sm py-2 px-2" title="10권 단위">
                            <button type="button" class="px-3 py-2 bg-red-500 hover:bg-red-600 text-white text-sm rounded-lg transition-colors" onclick="removeBrochureItem(this)">삭제</button>
                        </div>
                    `;
                });
                brochureEditHtml += `<button type="button" class="mt-2 px-3 py-2 bg-secondary hover:bg-green-600 text-white text-sm rounded-lg transition-colors" onclick="addBrochureItemEdit('${cardId}')">+ 브로셔 추가</button>`;
                brochureContainer.innerHTML = brochureEditHtml;
            }
        } catch (error) {
            console.error('수정 모드 전환 오류:', error);
            showAlert('수정 모드로 전환하는 중 오류가 발생했습니다.', 'danger');
        }

        const actionsDiv = card.querySelector('.card-actions');
        if (actionsDiv) {
            actionsDiv.innerHTML = `
                <button type="button" class="m-[5px] px-4 py-2 bg-primary hover:bg-purple-800 text-white font-medium rounded-lg transition-colors" onclick="saveRequest('${cardId}')">저장</button>
                <button type="button" class="m-[5px] px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-medium rounded-lg transition-colors" onclick="cancelEdit('${cardId}')">취소</button>
            `;
        }
    }

    async function addBrochureItemEdit(cardId) {
        const card = document.getElementById(cardId);
        const container = card && card.querySelector('.brochure-items-container');
        if (!container) return;

        try {
            const brochureOptions = await loadBrochureOptions();
            let optionsHtml = '<option value="">선택하세요</option>';
            brochureOptions.forEach(opt => {
                optionsHtml += `<option value="${opt.value}">${opt.text}</option>`;
            });

            const newItem = document.createElement('div');
            newItem.className = 'brochure-item-edit flex gap-2 items-center mb-2';
            newItem.innerHTML = `
                <select data-field="brochure" class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white sm:text-sm py-2 px-2">${optionsHtml}</select>
                <input type="number" data-field="quantity" value="10" min="10" step="10" class="w-24 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white sm:text-sm py-2 px-2" title="10권 단위">
                <button type="button" class="px-3 py-2 bg-red-500 hover:bg-red-600 text-white text-sm rounded-lg transition-colors" onclick="removeBrochureItem(this)">삭제</button>
            `;
            const addBtn = container.lastElementChild;
            container.insertBefore(newItem, addBtn);
        } catch (error) {
            console.error('브로셔 옵션 로드 오류:', error);
        }
    }

    function removeBrochureItem(button) {
        const el = button.closest('.brochure-item-edit');
        if (el) el.remove();
    }

    async function saveRequest(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;

        const groupIndex = parseInt(card.dataset.groupIndex);
        const requestIndex = parseInt(card.dataset.requestIndex);

        const schoolname = card.querySelector('[data-field="schoolname"] input')?.value || '';
        const address = card.querySelector('[data-field="address"] input')?.value || '';
        const phone = card.querySelector('[data-field="phone"] input')?.value || '';
        const date = card.querySelector('[data-field="date"] input')?.value || '';
        const contactSelect = card.querySelector('[data-field="contactName"] select');
        const contact = contactSelect?.value || '';
        const contactName = contactSelect?.options[contactSelect.selectedIndex]?.text || '';

        const brochures = [];
        const brochureItems = card.querySelectorAll('.brochure-item-edit');
        brochureItems.forEach(item => {
            const brochureSelect = item.querySelector('[data-field="brochure"]');
            const quantityInput = item.querySelector('[data-field="quantity"]');
            if (brochureSelect && brochureSelect.value && quantityInput && quantityInput.value) {
                brochures.push({
                    brochure: brochureSelect.value,
                    brochureName: brochureSelect.options[brochureSelect.selectedIndex].text,
                    quantity: parseInt(quantityInput.value, 10) || 0
                });
            }
        });

        if (!schoolname || !address || !phone || !date || brochures.length === 0) {
            alert('모든 필수 항목을 입력해주세요.');
            return;
        }
        const invalidQty = brochures.some(b => { const q = parseInt(b.quantity, 10) || 0; return q < 10 || q % 10 !== 0; });
        if (invalidQty) {
            alert('수량은 10권 단위(10, 20, 30…)로 입력해 주세요.');
            return;
        }

        try {
            const flatList = flattenRequests();
            const currentItem = flatList.find(item => item.groupIndex === groupIndex && item.requestIndex === requestIndex);
            if (!currentItem) {
                alert('신청 내역을 찾을 수 없습니다.');
                return;
            }

            const requestId = allRequests[groupIndex]?.id || allRequests[groupIndex]?.requests?.[requestIndex]?.id;
            if (!requestId) {
                alert('요청 ID를 찾을 수 없습니다.');
                return;
            }

            await RequestAPI.update(requestId, {
                date: date,
                schoolname: schoolname,
                address: address,
                phone: phone,
                contact_id: contact,
                contact_name: contactName,
                brochures: brochures.map(b => ({ brochure: b.brochure, brochureName: b.brochureName, quantity: b.quantity }))
            });

            await doSearch();
            showAlert('신청 내역이 수정되었습니다.', 'success');
        } catch (error) {
            console.error('수정 오류:', error);
            showAlert('수정 중 오류가 발생했습니다: ' + error.message, 'danger');
        }
    }

    function cancelEdit(cardId) {
        doSearch();
    }

    function formatPhoneDisplay(phone) {
        if (phone == null || phone === '') return '-';
        const digits = String(phone).replace(/\D/g, '');
        if (digits.length === 11 && digits.startsWith('010')) return digits.slice(0, 3) + '-' + digits.slice(3, 7) + '-' + digits.slice(7);
        if (digits.length === 10 && digits.startsWith('010')) return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
        if (digits.length === 10 && digits.startsWith('02')) return digits.slice(0, 2) + '-' + digits.slice(2, 6) + '-' + digits.slice(6);
        if (digits.length === 9 && digits.startsWith('02')) return digits.slice(0, 2) + '-' + digits.slice(2, 5) + '-' + digits.slice(5);
        if (digits.length === 10 && /^01[16789]/.test(digits)) return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
        if (digits.length === 9 && /^0[3-6]\d/.test(digits)) return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
        if (digits.length === 10 && /^0[3-6]\d/.test(digits)) return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
        if (digits.length === 11) return digits.slice(0, 3) + '-' + digits.slice(3, 7) + '-' + digits.slice(7);
        if (digits.length === 10) return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6, 10);
        if (digits.length === 9) return digits.slice(0, 2) + '-' + digits.slice(2, 5) + '-' + digits.slice(5);
        return phone;
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('ko-KR', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function updateStats() {
        let totalRequests = 0;
        let withInvoice = 0;

        filteredRequests.forEach(requestGroup => {
            if (requestGroup.requests) {
                requestGroup.requests.forEach(request => {
                    totalRequests++;
                    if (request.invoices && request.invoices.length > 0) withInvoice++;
                });
            }
        });

        const pendingInvoice = totalRequests - withInvoice;
        const totalRequestsEl = document.getElementById('totalRequests');
        const pendingInvoiceEl = document.getElementById('pendingInvoice');
        const withInvoiceEl = document.getElementById('withInvoice');
        if (totalRequestsEl) totalRequestsEl.textContent = totalRequests;
        if (pendingInvoiceEl) pendingInvoiceEl.textContent = pendingInvoice;
        if (withInvoiceEl) withInvoiceEl.textContent = withInvoice;
    }

    function showAlert(message, type) {
        const alertDiv = document.getElementById('alert');
        if (!alertDiv) return;
        alertDiv.className = 'max-w-5xl mx-auto mb-6 rounded-lg border p-4 ' + (type === 'danger' ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200' : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200');
        alertDiv.textContent = message;
        alertDiv.classList.remove('hidden');
        setTimeout(() => alertDiv.classList.add('hidden'), 3000);
    }

    function formatPhoneInput(value) {
        const digits = String(value).replace(/\D/g, '');
        if (digits.length <= 3) return digits;
        if (digits.startsWith('02') && digits.length <= 2) return digits;
        if (digits.startsWith('02') && digits.length <= 5) return digits.slice(0, 2) + '-' + digits.slice(2);
        if (digits.startsWith('02') && digits.length <= 9) return digits.slice(0, 2) + '-' + digits.slice(2, 5) + '-' + digits.slice(5);
        if (digits.startsWith('02') && digits.length <= 10) return digits.slice(0, 2) + '-' + digits.slice(2, 6) + '-' + digits.slice(6);
        if (digits.startsWith('010')) {
            if (digits.length <= 6) return digits.slice(0, 3) + '-' + digits.slice(3);
            if (digits.length <= 10) return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
            if (digits.length <= 11) return digits.slice(0, 3) + '-' + digits.slice(3, 7) + '-' + digits.slice(7, 11);
            return digits.slice(0, 3) + '-' + digits.slice(3, 7) + '-' + digits.slice(7, 11);
        }
        if (digits.startsWith('01') && digits.length <= 3) return digits;
        if (digits.startsWith('01') && digits.length <= 6) return digits.slice(0, 3) + '-' + digits.slice(3);
        if (digits.startsWith('01') && digits.length <= 10) return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
        if (/^0[3-6]\d/.test(digits) && digits.length <= 6) return digits.slice(0, 3) + '-' + digits.slice(3);
        if (/^0[3-6]\d/.test(digits) && digits.length <= 10) return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
        if (/^0[3-6]\d/.test(digits)) return digits.slice(0, 3) + '-' + digits.slice(3, 7) + '-' + digits.slice(7, 11);
        if (digits.length <= 7) return digits.slice(0, 3) + '-' + digits.slice(3);
        if (digits.length <= 11) return digits.slice(0, 3) + '-' + digits.slice(3, 7) + '-' + digits.slice(7, 11);
        return digits.slice(0, 3) + '-' + digits.slice(3, 7) + '-' + digits.slice(7, 11);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const searchBtn = document.getElementById('searchBtn');
        if (searchBtn) searchBtn.addEventListener('click', doSearch);
        const searchPhone = document.getElementById('searchPhone');
        if (searchPhone) {
            searchPhone.addEventListener('input', function() {
                this.value = formatPhoneInput(this.value);
            });
        }
    });
</script>
@endpush
