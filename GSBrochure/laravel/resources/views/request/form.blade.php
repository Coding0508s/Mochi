@extends('layouts.shell')

@section('title', 'GrapeSEED Brochure Request')

@section('sidebar-footer-label', '브로셔 신청')

@section('content')
    <header class="mb-10 max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-primary dark:text-purple-400 mb-2">GrapeSEED Brochure Request</h1>
        <p class="text-gray-600 dark:text-gray-300">필요한 브로셔를 신청하세요. 신청하신 브로셔는 최대 3일 이내에 발송됩니다.</p>
    </header>

    <div id="alert" class="max-w-5xl mx-auto mb-6 hidden rounded-lg border p-4" role="alert"></div>
    <div id="cursor-tooltip" role="tooltip" class="fixed z-[9999] pointer-events-none hidden px-3 py-2 text-sm text-white bg-gray-800 dark:bg-gray-700 rounded-lg shadow-lg whitespace-nowrap" style="left:0;top:0;"></div>

    <form id="brochureForm" class="max-w-5xl mx-auto space-y-8">
        <!-- 신청자 정보 (공통) -->
        <section class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden">
            <div class="px-6 py-4 border-b border-border-light dark:border-border-dark bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-primary text-white text-xs font-bold">1</span>
                    신청자 정보 (Requester Info)
                </h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="option-1">담당자 이름</label>
                    <select class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="option-1" name="option-1">
                        <option value="">선택하세요</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- 기관별 신청 행 -->
        <div id="rowsContainer" class="space-y-8">
            <!-- 행들이 여기에 동적으로 추가됩니다 -->
        </div>

        <div class="flex justify-end pt-4">
            <button type="button" class="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-3 border-2 border-secondary text-secondary font-medium rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors" onclick="addRow()">
                <span class="material-icons text-sm">add_business</span>
                기관 추가
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script src="{{ asset('js/api.js') }}"></script>
<script>
    let rowCount = 0;

    function openAddressSearch(inputId) {
        if (typeof daum === 'undefined' || !daum.Postcode) {
            showAlert('주소 검색 서비스를 불러오는 중입니다. 잠시 후 다시 시도해 주세요.', 'danger');
            return;
        }
        new daum.Postcode({
            oncomplete: function(data) {
                const addr = data.userSelectedType === 'R' ? data.roadAddress : data.jibunAddress;
                const fullAddr = data.buildingName ? addr + ' ' + data.buildingName : addr;
                const el = document.getElementById(inputId);
                if (el) el.value = fullAddr;
            }
        }).open();
    }

    async function loadBrochureOptions() {
        try {
            const raw = await BrochureAPI.getAll();
            const list = Array.isArray(raw)
                ? raw
                : (raw && Array.isArray(raw.data) ? raw.data : (raw && Array.isArray(raw.brochures) ? raw.brochures : []));
            const options = list
                .filter(b => b != null && typeof b === 'object')
                .map(b => {
                    const id = b.id !== undefined && b.id !== null ? Number(b.id) : NaN;
                    const name = b.name !== undefined && b.name !== null ? String(b.name).trim() : '';
                    const stockWarehouse = Number(b.stock_warehouse) || 0;
                    return { value: id, text: name, stock_warehouse: stockWarehouse };
                })
                .filter(opt => !Number.isNaN(opt.value));
            const byId = new Map();
            options.forEach(opt => { if (!byId.has(opt.value)) byId.set(opt.value, opt); });
            return Array.from(byId.values()).sort((a, b) => a.value - b.value);
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

    const TOOLTIP_OFFSET = 14;
    function getCursorTooltipEl() {
        let el = document.getElementById('cursor-tooltip');
        if (!el) {
            el = document.createElement('div');
            el.id = 'cursor-tooltip';
            el.setAttribute('role', 'tooltip');
            el.className = 'fixed z-[9999] pointer-events-none hidden px-3 py-2 text-sm text-white bg-gray-800 dark:bg-gray-700 rounded-lg shadow-lg whitespace-nowrap';
            el.style.left = '0'; el.style.top = '0';
            document.body.appendChild(el);
        }
        return el;
    }
    function showCursorTooltip(text, x, y) {
        const el = getCursorTooltipEl();
        el.textContent = text;
        el.style.left = (x + TOOLTIP_OFFSET) + 'px';
        el.style.top = (y + TOOLTIP_OFFSET) + 'px';
        el.classList.remove('hidden');
    }
    function moveCursorTooltip(x, y) {
        const el = getCursorTooltipEl();
        el.style.left = (x + TOOLTIP_OFFSET) + 'px';
        el.style.top = (y + TOOLTIP_OFFSET) + 'px';
    }
    function hideCursorTooltip() {
        const el = document.getElementById('cursor-tooltip');
        if (el) { el.classList.add('hidden'); }
    }

    function attachBrochureSelectBehavior(select) {
        if (!select) return;
        select.addEventListener('focus', function () {
            [].slice.call(select.options).forEach(opt => {
                const ft = opt.getAttribute('data-fulltext');
                if (ft) opt.textContent = ft;
            });
        });
        function showSelectedNameOnly() {
            const opt = select.options[select.selectedIndex];
            if (opt && opt.getAttribute('data-name')) opt.textContent = opt.getAttribute('data-name');
        }
        select.addEventListener('change', showSelectedNameOnly);
        select.addEventListener('blur', showSelectedNameOnly);
    }

    async function deductBrochureStock(brochures, contactName, schoolname, date) {
        try {
            const brochureMaster = await BrochureAPI.getAll();
            const insufficientStock = [];
            const stockChanges = [];

            const MIN_STOCK_FOR_REQUEST = 100; // 재고 부족이어도 100권 이상이면 신청 가능

            for (const brochure of brochures) {
                const masterItem = brochureMaster.find(b => b.id == brochure.brochure);
                if (masterItem) {
                    const currentStock = masterItem.stock_warehouse ?? 0;
                    const requestedQuantity = brochure.quantity || 0;
                    if (currentStock < MIN_STOCK_FOR_REQUEST) {
                        insufficientStock.push({ name: brochure.brochureName, requested: requestedQuantity, available: currentStock, reason: 'min' });
                    } else if (currentStock < requestedQuantity) {
                        insufficientStock.push({ name: brochure.brochureName, requested: requestedQuantity, available: currentStock, reason: 'qty' });
                    } else {
                        stockChanges.push({
                            brochureId: brochure.brochure,
                            brochureName: brochure.brochureName,
                            quantity: requestedQuantity,
                            beforeStock: currentStock,
                            afterStock: currentStock - requestedQuantity
                        });
                    }
                }
            }

            if (insufficientStock.length > 0) {
                return { success: false, insufficient: insufficientStock };
            }

            for (const change of stockChanges) {
                await BrochureAPI.updateWarehouseStock(change.brochureId, -change.quantity, date || new Date().toISOString().split('T')[0]);
                await StockHistoryAPI.create({
                    type: '출고',
                    location: 'warehouse',
                    date: date || new Date().toISOString().split('T')[0],
                    brochure_id: change.brochureId,
                    brochure_name: change.brochureName,
                    quantity: change.quantity,
                    contact_name: contactName || '',
                    schoolname: schoolname || '',
                    before_stock: change.beforeStock,
                    after_stock: change.afterStock
                });
            }
            return { success: true };
        } catch (error) {
            console.error('재고 차감 오류:', error);
            return { success: false, error: error.message };
        }
    }

    async function addRow() {
        rowCount++;
        const rowsContainer = document.getElementById('rowsContainer');
        const rowDiv = document.createElement('div');
        rowDiv.className = 'bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden';
        rowDiv.id = `row-${rowCount}`;

        rowDiv.innerHTML = `
            <div class="px-6 py-4 border-b border-border-light dark:border-border-dark bg-gray-50/50 dark:bg-gray-800/50">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-primary text-white text-xs font-bold">2</span>
                    배송 정보 (Delivery Info)
                </h2>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="date-${rowCount}">날짜</label>
                        <input type="date" id="date-${rowCount}" name="date-${rowCount}" required
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="schoolname-${rowCount}">기관명</label>
                        <input type="text" id="schoolname-${rowCount}" name="schoolname-${rowCount}" placeholder="기관명을 입력하세요" required
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="phone-${rowCount}">전화번호</label>
                        <input type="tel" id="phone-${rowCount}" name="phone-${rowCount}" placeholder="010-0000-0000" maxlength="13" required oninput="formatPhoneNumber(this)"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5">
                    </div>
                    <div class="space-y-2 md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="address-${rowCount}">주소</label>
                        <div class="flex gap-2">
                            <input type="text" id="address-${rowCount}" name="address-${rowCount}" required placeholder="주소 검색"
                                class="block flex-1 min-w-0 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5">
                            <button type="button" onclick="openAddressSearch('address-${rowCount}')" class="shrink-0 px-3 py-2.5 rounded-lg bg-primary hover:bg-purple-800 text-white text-sm font-medium whitespace-nowrap">주소 검색</button>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="flex items-start gap-3">
                        <span class="material-icons text-gray-400 mt-0.5">local_shipping</span>
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-1">송장번호 (화성 물류창고 입력)</h4>
                            <div id="invoice-container-${rowCount}"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-border-light dark:border-border-dark bg-gray-50/50 dark:bg-gray-800/50">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2 mb-4">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-primary text-white text-xs font-bold">3</span>
                    브로셔 선택 (Brochure Selection)
                </h2><p class="text-sm text-gray-600 dark:text-gray-400 mb-4 text-red-500">잔여 재고가 100권 이하인 브로셔는 신청이 제한됩니다.</p>
                <div class="space-y-4">
                    <div id="brochure-container-${rowCount}" class="space-y-4"></div>
                    <button type="button" class="flex items-center justify-center gap-2 px-4 py-2.5 border-2 border-secondary text-secondary font-medium rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors text-sm" onclick="addBrochureItem(${rowCount})">
                        <span class="material-icons text-sm">add</span>
                        브로셔 추가
                    </button>
                </div>
            </div>
            <div class="px-6 py-4 flex flex-col-reverse sm:flex-row justify-end gap-4 border-t border-border-light dark:border-border-dark">
                <button type="button" class="w-full sm:w-auto px-6 py-3 bg-white dark:bg-gray-700 text-red-500 border border-red-200 dark:border-red-900/30 hover:bg-red-50 dark:hover:bg-red-900/20 font-medium rounded-lg transition-colors" onclick="removeRow(${rowCount})">삭제</button>
                <button type="button" class="w-full sm:w-auto px-10 py-3 bg-primary hover:bg-purple-800 text-white font-medium rounded-lg shadow-md hover:shadow-lg transition-all transform active:scale-95" onclick="saveSingleRequest(${rowCount})">저장</button>
            </div>
        `;

        rowsContainer.appendChild(rowDiv);
        const dateInput = document.getElementById(`date-${rowCount}`);
        if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
        addBrochureItem(rowCount, true).catch(err => console.error('브로셔 항목 추가 오류:', err));
        addInvoiceField(rowCount, []);
    }

    function removeRow(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        if (row) row.remove();
    }

    const MIN_STOCK_FOR_DISPLAY = 100;

    function fillBrochureDropdownOptions(dropdown, hiddenInput, wrapper, trigger, brochureOptions) {
        dropdown.innerHTML = '';
        (brochureOptions || []).forEach(option => {
            const stock = Number(option.stock_warehouse) || 0;
            const canOrder = stock >= MIN_STOCK_FOR_DISPLAY;
            const statusText = canOrder ? '신청 가능' : '신청 불가';
            const fullText = `${option.text || ''} (${statusText})`;
            const optEl = document.createElement('div');
            optEl.className = 'brochure-option px-3 py-2 text-sm dark:text-white cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700' + (canOrder ? '' : ' opacity-70 cursor-not-allowed');
            optEl.textContent = fullText;
            optEl.dataset.value = String(option.value);
            optEl.dataset.name = option.text || '';
            if (!canOrder) {
                optEl.dataset.stock = String(stock);
                optEl.dataset.disabled = '1';
                optEl.addEventListener('mouseenter', function (e) {
                    showCursorTooltip(`현재 재고가 ${stock}권이라 신청할 수 없습니다`, e.clientX, e.clientY);
                });
                optEl.addEventListener('mousemove', function (e) {
                    moveCursorTooltip(e.clientX, e.clientY);
                });
                optEl.addEventListener('mouseleave', function () {
                    hideCursorTooltip();
                });
            } else {
                optEl.addEventListener('click', function (e) {
                    e.stopPropagation();
                    hiddenInput.value = optEl.dataset.value;
                    wrapper.dataset.selectedName = optEl.dataset.name;
                    trigger.textContent = optEl.dataset.name;
                    dropdown.classList.add('hidden');
                });
            }
            dropdown.appendChild(optEl);
        });
    }

    async function addBrochureItem(rowId, isDefault = false) {
        const container = document.getElementById(`brochure-container-${rowId}`);
        if (!container) return;

        const brochureCount = container.querySelectorAll('.brochure-item').length;
        const itemId = `brochure-${rowId}-${brochureCount + 1}`;

        const brochureOptions = await loadBrochureOptions();

        const brochureItem = document.createElement('div');
        brochureItem.className = 'brochure-item flex flex-wrap items-end gap-4 p-4 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-gray-800/50';
        brochureItem.id = `brochure-item-${itemId}`;

        const selectCell = document.createElement('div');
        selectCell.className = 'flex-1 min-w-[200px] space-y-2 relative';
        const label = document.createElement('label');
        label.className = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
        label.setAttribute('for', itemId);
        label.textContent = '브로셔';
        const wrapper = document.createElement('div');
        wrapper.className = 'brochure-select-wrapper relative';
        wrapper.dataset.selectedName = '';
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = itemId;
        hiddenInput.name = itemId;
        hiddenInput.required = true;
        const trigger = document.createElement('div');
        trigger.className = 'brochure-select-trigger block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 py-2.5 px-3 text-left text-sm dark:text-white cursor-pointer focus:ring-2 focus:ring-primary focus:border-primary';
        trigger.textContent = '선택하세요';
        const dropdown = document.createElement('div');
        dropdown.className = 'brochure-select-dropdown absolute left-0 right-0 top-full mt-1 z-50 hidden max-h-60 overflow-auto rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg';

        function positionDropdownFixed() {
            const rect = trigger.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.left = rect.left + 'px';
            dropdown.style.top = (rect.bottom + 4) + 'px';
            dropdown.style.minWidth = rect.width + 'px';
            dropdown.style.right = 'auto';
        }

        fillBrochureDropdownOptions(dropdown, hiddenInput, wrapper, trigger, brochureOptions);

        trigger.addEventListener('click', async function (e) {
            e.stopPropagation();
            const wasHidden = dropdown.classList.contains('hidden');
            if (wasHidden) {
                try {
                    const opts = await loadBrochureOptions();
                    fillBrochureDropdownOptions(dropdown, hiddenInput, wrapper, trigger, opts);
                    requestAnimationFrame(function () {
                        positionDropdownFixed();
                        dropdown.classList.remove('hidden');
                    });
                } catch (err) {
                    console.error('브로셔 목록 새로고침 오류:', err);
                    positionDropdownFixed();
                    dropdown.classList.remove('hidden');
                }
            } else {
                dropdown.classList.add('hidden');
            }
        });
        document.addEventListener('click', function closeDropdown(e) {
            if (!wrapper.contains(e.target)) dropdown.classList.add('hidden');
        });

        wrapper.appendChild(hiddenInput);
        wrapper.appendChild(trigger);
        wrapper.appendChild(dropdown);
        selectCell.appendChild(label);
        selectCell.appendChild(wrapper);

        const qtyCell = document.createElement('div');
        qtyCell.className = 'w-full sm:w-32 space-y-2';
        qtyCell.innerHTML = `
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="quantity-${itemId}">수량 (10권 단위)</label>
            <input type="number" id="quantity-${itemId}" name="quantity-${itemId}" min="10" step="10" placeholder="10, 20, 30…" required class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5">
        `;

        brochureItem.appendChild(selectCell);
        brochureItem.appendChild(qtyCell);
        if (!isDefault) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg';
            btn.textContent = '삭제';
            btn.onclick = () => removeBrochureItem(itemId);
            brochureItem.appendChild(btn);
        }
        container.appendChild(brochureItem);
    }

    function removeBrochureItem(itemId) {
        const item = document.getElementById(`brochure-item-${itemId}`);
        if (item) item.remove();
    }

    function collectBrochureItems(rowId) {
        const container = document.getElementById(`brochure-container-${rowId}`);
        if (!container) return [];
        const items = [];
        container.querySelectorAll('.brochure-item').forEach(item => {
            const wrapper = item.querySelector('.brochure-select-wrapper');
            const input = item.querySelector('input[type="number"]');
            if (wrapper && input) {
                const valueInput = wrapper.querySelector('input[type="hidden"]');
                if (valueInput && valueInput.value && input.value) {
                    items.push({
                        brochure: valueInput.value,
                        brochureName: wrapper.dataset.selectedName || '',
                        quantity: parseInt(input.value)
                    });
                }
            }
        });
        return items;
    }

    function formatPhoneNumber(input) {
        let value = input.value.replace(/[^\d]/g, '');
        if (value.length <= 3) input.value = value;
        else if (value.length <= 7) input.value = value.slice(0, 3) + '-' + value.slice(3);
        else if (value.length <= 11) input.value = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7);
        else input.value = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7, 11);
    }

    function addInvoiceField(rowId, invoices = []) {
        const container = document.getElementById(`invoice-container-${rowId}`);
        if (!container) return;
        container.innerHTML = '';
        if (invoices && invoices.length > 0) {
            invoices.forEach((invoice) => {
                const div = document.createElement('p');
                div.className = 'text-sm text-gray-600 dark:text-gray-400';
                div.textContent = invoice;
                container.appendChild(div);
            });
        } else {
            const emptyMsg = document.createElement('p');
            emptyMsg.className = 'text-sm text-gray-500 italic';
            emptyMsg.textContent = '송장번호가 아직 등록되지 않았습니다.';
            container.appendChild(emptyMsg);
        }
    }

    function collectInvoiceFields(rowId) {
        const container = document.getElementById(`invoice-container-${rowId}`);
        if (!container) return [];
        const invoices = [];
        container.querySelectorAll('input[type="text"]').forEach(input => {
            if (input.value && input.value.trim()) invoices.push(input.value.trim());
        });
        return invoices;
    }

    function showAlert(message, type) {
        const alertDiv = document.getElementById('alert');
        alertDiv.className = 'max-w-5xl mx-auto mb-6 rounded-lg border p-4 ' + (type === 'danger' ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200' : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200');
        alertDiv.textContent = message;
        alertDiv.classList.remove('hidden');
        setTimeout(() => alertDiv.classList.add('hidden'), 3000);
    }

    async function saveSingleRequest(rowId) {
        const contactSelect = document.getElementById('option-1');
        const contact = contactSelect.value;
        const contactName = contactSelect.options[contactSelect.selectedIndex]?.text || '';
        if (!contact) {
            showAlert('담당자를 선택해주세요.', 'danger');
            return;
        }

        const date = document.getElementById(`date-${rowId}`)?.value;
        const schoolname = document.getElementById(`schoolname-${rowId}`)?.value;
        const address = document.getElementById(`address-${rowId}`)?.value;
        const phone = document.getElementById(`phone-${rowId}`)?.value;
        const brochures = collectBrochureItems(rowId);
        const invoices = collectInvoiceFields(rowId);

        if (!date || !schoolname || !address || !phone || brochures.length === 0) {
            showAlert('모든 필수 항목을 입력해주세요.', 'danger');
            return;
        }
        const invalidQty = brochures.some(b => { const q = parseInt(b.quantity, 10) || 0; return q < 10 || q % 10 !== 0; });
        if (invalidQty) {
            showAlert('수량은 10권 단위(10, 20, 30…)로 입력해 주세요.', 'danger');
            return;
        }

        try {
            const stockResult = await deductBrochureStock(brochures, contactName, schoolname, date);
            if (!stockResult.success) {
                if (stockResult.insufficient) {
                    let message = '신청할 수 없습니다:\n';
                    stockResult.insufficient.forEach(item => {
                        if (item.reason === 'min') {
                            message += `- ${item.name}: 현재 재고 ${item.available}권 (100권 이상일 때만 신청 가능)\n`;
                        } else {
                            message += `- ${item.name}: 요청 ${item.requested}권, 보유 ${item.available}권\n`;
                        }
                    });
                    alert(message);
                } else {
                    showAlert('재고 확인 중 오류가 발생했습니다: ' + (stockResult.error || '알 수 없는 오류'), 'danger');
                }
                return;
            }

            const requestData = {
                date, schoolname, address, phone,
                contact_id: contact,
                contact_name: contactName,
                brochures: brochures.map(b => ({ brochure: b.brochure, brochureName: b.brochureName, quantity: b.quantity })),
                invoices: invoices.filter(inv => inv && inv.trim())
            };

            await RequestAPI.create(requestData);
            showAlert('브로셔 신청이 완료되었습니다!', 'success');

            const row = document.getElementById(`row-${rowId}`);
            if (row) row.remove();
        } catch (error) {
            showAlert('신청 저장 중 오류가 발생했습니다: ' + error.message, 'danger');
        }
    }

    document.getElementById('brochureForm').addEventListener('submit', function(e) {
        e.preventDefault();
        showAlert('각 건마다 개별 저장 버튼을 사용해주세요.', 'danger');
    });

    async function loadContactOptionsToSelect() {
        const contactSelect = document.getElementById('option-1');
        if (!contactSelect) return;
        try {
            const contacts = await loadContactOptions();
            contactSelect.innerHTML = '<option value="">선택하세요</option>';
            contacts.forEach(contact => {
                const option = document.createElement('option');
                option.value = contact.value;
                option.textContent = contact.text;
                contactSelect.appendChild(option);
            });
        } catch (error) {
            console.error('담당자 옵션 로드 오류:', error);
        }
    }

    window.addEventListener('DOMContentLoaded', async function() {
        await loadContactOptionsToSelect();
        await addRow();
    });
</script>
@endpush