@extends('layouts.shell-public')

@section('title', 'Brochure Request Form Variant 2')

@section('sidebar-footer-label', '브로셔 신청')

@section('content')
    <header class="mb-10 max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-primary dark:text-purple-400 mb-2">GrapeSEED Brochure Request</h1>
        <p class="text-gray-600 dark:text-gray-300">필요한 브로셔를 신청하세요. 신청하신 브로셔는 최대 3일 이내에 발송됩니다.</p>
    </header>

    <div id="alertV2" class="max-w-5xl mx-auto mb-6 hidden rounded-lg border p-4" role="alert"></div>

    <!-- 주소 검증 모달 -->
    <div id="addressConfirmModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeAddressConfirmModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">주소 확인</h3>
                <p id="addressConfirmMessage" class="text-sm text-gray-600 dark:text-gray-300 mb-4"></p>
                <div id="addressConfirmValue" class="hidden mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-sm text-gray-800 dark:text-gray-200 break-words"></div>
                <div class="flex justify-end">
                    <button type="button" id="addressConfirmBtn" class="px-4 py-2 rounded-lg bg-primary hover:bg-purple-800 text-white text-sm font-medium">확인</button>
                </div>
            </div>
        </div>
    </div>

    <form class="max-w-5xl mx-auto space-y-8" id="brochureFormV2" method="post" action="#">
        @csrf
        <!-- 1. 배송 정보 (신청·배송 통합) -->
        <section class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden">
            <div class="px-6 py-4 border-b border-border-light dark:border-border-dark bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-primary text-white text-xs font-bold">1</span>
                    배송 정보 (Delivery Info)
                </h2>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="request-date">신청일</label>
                        <input class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="request-date" name="request_date" type="date" required/>
                    </div>
                    <div class="space-y-2 relative" id="org-name-wrap">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="org-name">기관명</label>
                        <input class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="org-name" name="org_name" placeholder="기관명을 입력하세요" type="text" required autocomplete="off"/>
                        <ul id="org-name-dropdown" class="hidden absolute z-20 left-0 right-0 mt-1 py-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-52 overflow-y-auto" role="listbox"></ul>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="contact">담당자</label>
                        <select class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="contact" name="contact">
                            <option value="">선택하세요</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="phone">전화번호</label>
                        <input class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="phone" name="phone" placeholder="010-0000-0000" type="tel" maxlength="13" required/>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="address">주소</label>
                        <div class="flex gap-2">
                            <input class="block flex-1 min-w-0 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="address" name="address" type="text" required placeholder="주소 검색 버튼을 눌러 주소를 입력하세요"/>
                            <button type="button" id="addressSearchBtn" class="shrink-0 px-4 py-2.5 rounded-lg bg-primary hover:bg-purple-800 text-white text-sm font-medium transition-colors whitespace-nowrap">주소 검색</button>
                        </div>
                    </div>
                </div>
                <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="flex items-start gap-3">
                        <span class="material-icons text-gray-400 mt-0.5">local_shipping</span>
                        <!-- <div>
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-1">송장번호 (화성 물류창고 입력)</h4>
                            <p class="text-sm text-gray-500 italic">송장번호가 아직 등록되지 않았습니다.</p>
                        </div> -->
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. 브로셔 선택 -->
        <section class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden">
            <div class="px-6 py-4 border-b border-border-light dark:border-border-dark bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-primary text-white text-xs font-bold">2</span>
                    브로셔 선택 (Brochure Selection)
                </h2>
            </div>
            <div class="p-6">
                <div id="brochureCardsContainer" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <p class="col-span-2 md:col-span-4 text-sm text-gray-500 dark:text-gray-400 py-4">브로셔 목록을 불러오는 중...</p>
                </div>
            </div>
        </section>

        <div class="flex justify-end pt-4 pb-12">
            <button type="submit" class="px-10 py-3 bg-primary hover:bg-purple-800 text-white font-medium rounded-lg shadow-md hover:shadow-lg transition-all transform active:scale-95">
                신청하기
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script src="{{ asset('js/api.js') }}"></script>
<script>
(function() {
    window.BROCHURE_PLACEHOLDER_IMG = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='267' viewBox='0 0 200 267'%3E%3Crect fill='%23e5e7eb' width='200' height='267'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%239ca3af' font-size='14' font-family='sans-serif'%3E%EC%9D%B4%EB%AF%B8%EC%A7%80%20%EC%97%86%EC%9D%8C%3C/text%3E%3C/svg%3E";

    function escapeHtml(s) {
        if (!s) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function openPostcodeForAddress() {
        if (typeof daum === 'undefined' || !daum.Postcode) {
            showAlertV2('주소 검색 서비스를 불러오는 중입니다. 잠시 후 다시 시도해 주세요.', 'danger');
            return;
        }
        new daum.Postcode({
            oncomplete: function(data) {
                var addr = data.userSelectedType === 'R' ? data.roadAddress : data.jibunAddress;
                if (data.buildingName) addr += ' ' + data.buildingName;
                var el = document.getElementById('address');
                if (el) el.value = addr;
            }
        }).open();
    }

    async function loadBrochureCards() {
        var container = document.getElementById('brochureCardsContainer');
        if (!container) return;
        try {
            var raw = await BrochureAPI.getAll();
            var list = Array.isArray(raw) ? raw : (raw && Array.isArray(raw.data) ? raw.data : (raw && Array.isArray(raw.brochures) ? raw.brochures : []));
            var brochures = list.filter(function(b) { return b != null && (b.id !== undefined && b.id !== null); });
            container.innerHTML = '';
            if (brochures.length === 0) {
                container.innerHTML = '<p class="col-span-2 md:col-span-4 text-sm text-gray-500 dark:text-gray-400 py-4">등록된 브로셔가 없습니다.</p>';
                return;
            }
            brochures.forEach(function(b) {
                var id = b.id;
                var name = (b.name || '').trim();
                var nameEsc = escapeHtml(name);
                var imgSrc = (b.image_url && String(b.image_url).trim()) ? b.image_url : window.BROCHURE_PLACEHOLDER_IMG;
                var card = document.createElement('div');
                card.className = 'flex flex-col brochure-card rounded-xl border-2 border-slate-200 dark:border-slate-600 p-3 bg-white dark:bg-gray-800/50';
                card.dataset.brochureId = id;
                card.dataset.brochureName = name;
                card.innerHTML =
                    '<div class="relative rounded-lg overflow-hidden border-2 border-slate-200 dark:border-slate-600 aspect-[3/4] bg-gray-100 dark:bg-gray-800">' +
                    '<img alt="' + nameEsc + '" class="w-full h-full object-cover" src="' + escapeHtml(imgSrc) + '" onerror="this.onerror=null;this.src=window.BROCHURE_PLACEHOLDER_IMG">' +
                    '</div>' +
                    '<label class="mt-2 block">' +
                    '<span class="sr-only">수량</span>' +
                    '<div class="flex items-center gap-2">' +
                    '<input type="number" name="quantity[' + id + ']" min="0" step="10" value="0" placeholder="수량(10권 단위)" class="brochure-qty flex-1 min-w-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2 px-3 text-center" data-brochure-id="' + id + '">' +
                    '<span class="text-sm font-medium text-gray-700 dark:text-gray-300 shrink-0">권</span>' +
                    '</div>' +
                    '</label>';
                container.appendChild(card);
            });
        } catch (err) {
            console.error('브로셔 목록 로드 오류:', err);
            container.innerHTML = '<p class="col-span-2 md:col-span-4 text-sm text-red-500 dark:text-red-400 py-4">브로셔 목록을 불러오지 못했습니다.</p>';
        }
    }

    function showAlertV2(message, type) {
        var el = document.getElementById('alertV2');
        if (!el) return;
        el.textContent = message;
        el.className = 'max-w-5xl mx-auto mb-6 rounded-lg border p-4 ' + (type === 'danger' ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200' : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200');
        el.classList.remove('hidden');
        setTimeout(function() { el.classList.add('hidden'); }, 4000);
    }

    function collectBrochureItemsV2() {
        var items = [];
        var cards = document.querySelectorAll('#brochureCardsContainer .brochure-card');
        cards.forEach(function(card) {
            var qtyInput = card.querySelector('.brochure-qty');
            var qty = qtyInput ? (parseInt(qtyInput.value, 10) || 0) : 0;
            if (qty > 0) {
                items.push({
                    brochure: card.dataset.brochureId,
                    brochureName: card.dataset.brochureName || '',
                    quantity: qty
                });
            }
        });
        return items;
    }

    function formatPhoneNumberV2(input) {
        var value = input.value.replace(/\D/g, '');
        if (value.length <= 3) { input.value = value; return; }
        if (value.startsWith('02')) {
            if (value.length <= 5) input.value = value.slice(0, 2) + '-' + value.slice(2);
            else if (value.length <= 9) input.value = value.slice(0, 2) + '-' + value.slice(2, 5) + '-' + value.slice(5);
            else input.value = value.slice(0, 2) + '-' + value.slice(2, 6) + '-' + value.slice(6, 10);
            return;
        }
        if (value.startsWith('010')) {
            if (value.length <= 6) input.value = value.slice(0, 3) + '-' + value.slice(3);
            else if (value.length <= 10) input.value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6);
            else input.value = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7, 11);
            return;
        }
        if (/^0[3-6]\d/.test(value)) {
            if (value.length <= 6) input.value = value.slice(0, 3) + '-' + value.slice(3);
            else if (value.length <= 10) input.value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6);
            else input.value = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7, 11);
            return;
        }
        if (value.startsWith('01') && value.length <= 6) { input.value = value.slice(0, 3) + '-' + value.slice(3); return; }
        if (value.startsWith('01') && value.length <= 10) { input.value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6); return; }
        if (value.length <= 7) input.value = value.slice(0, 3) + '-' + value.slice(3);
        else if (value.length <= 11) input.value = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7);
        else input.value = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7, 11);
    }

    var MIN_STOCK_FOR_REQUEST = 100;
    async function deductBrochureStockV2(brochures, contactName, schoolname, date) {
        try {
            var brochureMaster = await BrochureAPI.getAll();
            var insufficientStock = [];
            var stockChanges = [];
            for (var i = 0; i < brochures.length; i++) {
                var brochure = brochures[i];
                var masterItem = brochureMaster.find(function(b) { return b.id == brochure.brochure; });
                if (masterItem) {
                    var currentStock = masterItem.stock_warehouse ?? 0;
                    var requestedQuantity = brochure.quantity || 0;
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
            var dateStr = date || new Date().toISOString().split('T')[0];
            for (var j = 0; j < stockChanges.length; j++) {
                var change = stockChanges[j];
                await BrochureAPI.updateWarehouseStock(change.brochureId, -change.quantity, dateStr);
                await StockHistoryAPI.create({
                    type: '출고',
                    location: 'warehouse',
                    date: dateStr,
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

    async function loadContactOptions() {
        try {
            var contacts = await ContactAPI.getAll();
            var list = Array.isArray(contacts) ? contacts : (contacts && contacts.data ? contacts.data : []);
            var select = document.getElementById('contact');
            if (!select) return;
            select.innerHTML = '<option value="">선택하세요</option>';
            list.forEach(function(c) {
                var opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name || '';
                select.appendChild(opt);
            });
        } catch (err) {
            console.error('담당자 옵션 로드 오류:', err);
        }
    }

    function initOrgNameAutocomplete() {
        var input = document.getElementById('org-name');
        var dropdown = document.getElementById('org-name-dropdown');
        var wrap = document.getElementById('org-name-wrap');
        if (!input || !dropdown || !wrap) return;
        var debounceTimer = null;
        function showDropdown(items) {
            dropdown.innerHTML = '';
            if (!items || items.length === 0) {
                dropdown.classList.add('hidden');
                return;
            }
            items.forEach(function(item) {
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.className = 'px-4 py-2.5 text-sm text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer';
                li.textContent = item.name || '';
                li.dataset.name = item.name || '';
                li.dataset.address = item.address || '';
                li.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    input.value = item.name || '';
                    var addressEl = document.getElementById('address');
                    var addressVal = (item.address || '').trim();
                    if (addressEl) addressEl.value = addressVal;
                    dropdown.classList.add('hidden');
                    window.addressConfirmed = false;
                    var modal = document.getElementById('addressConfirmModal');
                    var msgEl = document.getElementById('addressConfirmMessage');
                    var valueWrap = document.getElementById('addressConfirmValue');
                    if (modal && msgEl && valueWrap) {
                        if (!addressVal) {
                            msgEl.textContent = '배송 주소를 입력해 주세요.';
                            valueWrap.classList.add('hidden');
                            valueWrap.textContent = '';
                        } else {
                            msgEl.textContent = '입력하신 배송 주소를 확인해 주세요.';
                            valueWrap.textContent = addressVal;
                            valueWrap.classList.remove('hidden');
                        }
                        modal.classList.remove('hidden');
                    }
                    input.focus();
                });
                dropdown.appendChild(li);
            });
            dropdown.classList.remove('hidden');
        }
        function hideDropdown() {
            dropdown.classList.add('hidden');
        }
        function fetchAndShow() {
            var q = (input.value || '').trim();
            if (typeof window.fetchInstitutionsForAutocomplete !== 'function') return;
            window.fetchInstitutionsForAutocomplete(q).then(function(list) {
                showDropdown(Array.isArray(list) ? list : []);
            }).catch(function() {
                showDropdown([]);
            });
        }
        input.addEventListener('focus', function() {
            fetchAndShow();
        });
        input.addEventListener('input', function() {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchAndShow, 200);
        });
        input.addEventListener('blur', function() {
            debounceTimer = setTimeout(hideDropdown, 150);
        });
        dropdown.addEventListener('mousedown', function(e) {
            e.preventDefault();
        });
        document.addEventListener('click', function(e) {
            if (wrap && !wrap.contains(e.target)) hideDropdown();
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var dateEl = document.getElementById('request-date');
        if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];
        var addressSearchBtn = document.getElementById('addressSearchBtn');
        if (addressSearchBtn) addressSearchBtn.addEventListener('click', openPostcodeForAddress);
        loadBrochureCards();
        loadContactOptions();
        initOrgNameAutocomplete();
    });

    var phoneEl = document.getElementById('phone');
    if (phoneEl) {
        phoneEl.addEventListener('focus', function() {
            if (window.addressConfirmed) return;
            var addressEl = document.getElementById('address');
            var addressVal = (addressEl && addressEl.value) ? addressEl.value.trim() : '';
            var modal = document.getElementById('addressConfirmModal');
            var msgEl = document.getElementById('addressConfirmMessage');
            var valueWrap = document.getElementById('addressConfirmValue');
            if (!modal || !msgEl || !valueWrap) return;
            if (!addressVal) {
                msgEl.textContent = '배송 주소를 입력해 주세요.';
                valueWrap.classList.add('hidden');
                valueWrap.textContent = '';
            } else {
                msgEl.textContent = '입력하신 배송 주소를 확인해 주세요.';
                valueWrap.textContent = addressVal;
                valueWrap.classList.remove('hidden');
            }
            modal.classList.remove('hidden');
        });
        phoneEl.addEventListener('input', function() { formatPhoneNumberV2(this); });
    }
    function closeAddressConfirmModal() {
        window.addressConfirmed = true;
        var modal = document.getElementById('addressConfirmModal');
        if (modal) modal.classList.add('hidden');
        var addressEl = document.getElementById('address');
        var addressVal = (addressEl && addressEl.value) ? addressEl.value.trim() : '';
        if (!addressVal) {
            if (addressEl) addressEl.focus();
        }
    }
    document.getElementById('addressConfirmBtn').addEventListener('click', closeAddressConfirmModal);

    document.getElementById('brochureFormV2').addEventListener('submit', async function(e) {
        e.preventDefault();
        var date = (document.getElementById('request-date') && document.getElementById('request-date').value) || '';
        var schoolname = (document.getElementById('org-name') && document.getElementById('org-name').value) || '';
        var address = (document.getElementById('address') && document.getElementById('address').value) || '';
        var phone = (document.getElementById('phone') && document.getElementById('phone').value) || '';
        var contactSelect = document.getElementById('contact');
        var contactId = (contactSelect && contactSelect.value) ? contactSelect.value : null;
        var contactName = (contactSelect && contactSelect.options[contactSelect.selectedIndex]) ? contactSelect.options[contactSelect.selectedIndex].text : '';
        var brochures = collectBrochureItemsV2();
        if (!date || !schoolname.trim() || !address.trim() || !phone.trim()) {
            showAlertV2('신청일, 기관명, 전화번호, 주소를 모두 입력해 주세요.', 'danger');
            return;
        }
        if (brochures.length === 0) {
            showAlertV2('브로셔를 선택하고 수량을 입력해 주세요.', 'danger');
            return;
        }
        var invalidQty = brochures.some(function(b) { var q = parseInt(b.quantity, 10) || 0; return q < 10 || q % 10 !== 0; });
        if (invalidQty) {
            showAlertV2('수량은 10권 단위(10, 20, 30…)로 입력해 주세요.', 'danger');
            return;
        }
        try {
            var stockResult = await deductBrochureStockV2(brochures, contactName || '', schoolname, date);
            if (!stockResult.success) {
                if (stockResult.insufficient && stockResult.insufficient.length) {
                    var msg = '신청할 수 없습니다:\n';
                    stockResult.insufficient.forEach(function(item) {
                        if (item.reason === 'min') {
                            msg += '- ' + item.name + ': 현재 재고 ' + item.available + '권 (100권 이상일 때만 신청 가능)\n';
                        } else {
                            msg += '- ' + item.name + ': 요청 ' + item.requested + '권, 보유 ' + item.available + '권\n';
                        }
                    });
                    alert(msg);
                } else {
                    showAlertV2('재고 확인 중 오류가 발생했습니다: ' + (stockResult.error || '알 수 없는 오류'), 'danger');
                }
                return;
            }
            var requestData = {
                date: date,
                schoolname: schoolname.trim(),
                address: address.trim(),
                phone: phone.trim(),
                contact_id: contactId ? parseInt(contactId, 10) : null,
                contact_name: contactName ? contactName.trim() : null,
                brochures: brochures.map(function(b) { return { brochure: b.brochure, brochureName: b.brochureName, quantity: b.quantity }; }),
                invoices: []
            };
            await RequestAPI.create(requestData);
            window.location.href = '{{ url("requestbrochure-success") }}';
        } catch (err) {
            showAlertV2('신청 저장 중 오류가 발생했습니다: ' + err.message, 'danger');
        }
    });
})();
</script>
@endpush
