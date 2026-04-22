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
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">입력된 주소가 맞나요?</h3>
                <p id="addressConfirmMessage" class="text-sm text-gray-600 dark:text-gray-300 mb-4"></p>
                <div id="addressConfirmValue" class="hidden mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-sm text-gray-800 dark:text-gray-200 break-words"></div>
                <div class="flex justify-end">
                    <button type="button" id="addressConfirmBtn" class="px-4 py-2 rounded-lg bg-primary hover:bg-purple-800 text-white text-sm font-medium">확인</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 카카오 주소 검색 모달 -->
    <div id="addressSearchModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true" role="dialog">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeAddressSearchModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-3xl w-full border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">주소 검색</h3>
                    <button type="button" id="addressSearchCloseBtn" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-300" aria-label="주소 검색 닫기">
                        <span class="material-icons text-xl">close</span>
                    </button>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900/30">
                    <div id="addressSearchPostcodeContainer" class="w-full h-[520px] rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white"></div>
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
                    <div class="hidden md:block"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2 relative" id="org-name-wrap">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="org-name">기관명</label>
                        <input class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="org-name" name="org_name" placeholder="기관명을 입력하세요" type="text" required autocomplete="off"/>
                        <ul id="org-name-dropdown" class="hidden absolute z-20 left-0 right-0 mt-1 py-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-52 overflow-y-auto" role="listbox"></ul>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="address">주소</label>
                        <div class="flex gap-2">
                            <input class="block flex-1 min-w-0 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="address" name="address" type="text" required placeholder="주소 검색 버튼을 눌러 주소를 입력하세요"/>
                            <button type="button" id="addressSearchBtn" class="shrink-0 px-4 py-2.5 rounded-lg bg-primary hover:bg-purple-800 text-white text-sm font-medium transition-colors whitespace-nowrap">주소 검색</button>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="phone">전화번호</label>
                        <input class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="phone" name="phone" placeholder="010-0000-0000" type="tel" maxlength="13" required/>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="verify-code">인증번호</label>
                        <div class="flex flex-wrap items-center gap-3">
                            <span id="verify-code-wrap" class="hidden">
                                <input class="block w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5" id="verify-code" placeholder="6자리" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code"/>
                            </span>
                            <button type="button" id="verify-btn" class="px-4 py-2.5 rounded-lg bg-primary hover:bg-purple-800 text-white text-sm font-medium transition-colors whitespace-nowrap shadow-sm disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-primary">
                                인증번호 발송
                            </button>
                            <span id="verify-status" class="text-sm text-gray-500 dark:text-gray-400"></span>
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
        <section class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark overflow-visible">
            <div class="px-6 py-4 border-b border-border-light dark:border-border-dark bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center flex-wrap gap-2">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-primary text-white text-xs font-bold">2</span>
                    브로셔 선택 (Brochure Selection)
                </h2>
                <span class="text-sm text-purple-500 dark:text-purple-400 font-medium">* 브로셔를 선택하고 수량(10권 단위) 입력 후 추가해 주세요</span>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="relative flex-1 min-w-[220px]" id="brochureDropdownWrap">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">브로셔</label>
                        <button type="button" id="brochureDropdownTrigger" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-left text-sm text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <img id="brochureDropdownThumb" src="" alt="" class="w-10 h-14 object-cover rounded hidden shrink-0"/>
                            <span id="brochureDropdownLabel" class="flex-1">추가할 브로셔를 선택하세요</span>
                            <span class="material-icons text-gray-500 dark:text-gray-400 shrink-0">arrow_drop_down</span>
                        </button>
                        <div id="brochureDropdownPanel" class="hidden absolute z-20 left-0 mt-1 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg max-h-80 overflow-auto min-w-[280px] w-full">
                            <p id="brochureDropdownLoading" class="p-4 text-sm text-gray-500 dark:text-gray-400">불러오는 중...</p>
                            <div id="brochureDropdownOptions" class="p-2 grid grid-cols-1 sm:grid-cols-2 gap-1"></div>
                        </div>
                        <div id="brochureHoverPreview" class="hidden absolute z-30 left-full ml-2 top-0 w-40 rounded-lg border-2 border-primary/30 bg-white dark:bg-gray-800 shadow-xl p-2 pointer-events-none">
                            <img id="brochureHoverPreviewImg" src="" alt="" class="w-full aspect-[3/4] object-cover rounded border border-gray-100 dark:border-gray-600" onerror="this.onerror=null;this.src=window.BROCHURE_PLACEHOLDER_IMG"/>
                            <p id="brochureHoverPreviewName" class="text-xs font-medium text-gray-900 dark:text-white mt-1.5 line-clamp-2 break-words"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">수량</label>
                        <input type="number" id="brochureQuantity" min="10" step="10" value="10" title="10권 단위" class="w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-center text-sm py-2 px-2 dark:text-white"/>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">권 (10권 단위)</span>
                    </div>
                    <button type="button" id="brochureAddBtn" class="px-4 py-2.5 rounded-lg bg-primary hover:bg-purple-800 text-white text-sm font-medium shrink-0">
                        추가
                    </button>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">추가한 브로셔</p>
                    <div id="brochureSelectedList" class="rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th class="text-left py-2 px-3 font-medium text-gray-700 dark:text-gray-300">브로셔명</th>
                                    <th class="text-right py-2 px-3 font-medium text-gray-700 dark:text-gray-300 w-24">수량</th>
                                    <th class="w-12"></th>
                                </tr>
                            </thead>
                            <tbody id="brochureSelectedBody" class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr id="brochureSelectedEmpty" class="text-gray-500 dark:text-gray-400 text-center py-6">
                                    <td colspan="3">추가된 브로셔가 없습니다.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex justify-end pt-4 pb-12">
            <button type="submit" id="brochureSubmitBtn" disabled class="px-10 py-3 bg-primary hover:bg-purple-800 text-white font-medium rounded-lg shadow-md hover:shadow-lg transition-all transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-primary disabled:active:scale-100">
                신청하기
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script src="{{ asset('js/gs-brochure-api.js') }}"></script>
<script>
(function() {
    window.BROCHURE_PLACEHOLDER_IMG = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='267' viewBox='0 0 200 267'%3E%3Crect fill='%23e5e7eb' width='200' height='267'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%239ca3af' font-size='14' font-family='sans-serif'%3E%EC%9D%B4%EB%AF%B8%EC%A7%80%20%EC%97%86%EC%9D%8C%3C/text%3E%3C/svg%3E";

    /** img src에 절대 undefined가 들어가지 않도록 보장 (GET /undefined 404 방지) */
    function safeBrochureImageUrl(url) {
        var placeholder = window.BROCHURE_PLACEHOLDER_IMG || '';
        if (url === undefined || url === null || String(url).trim() === '' || String(url) === 'undefined') return placeholder;
        return String(url).trim();
    }

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
        var modal = document.getElementById('addressSearchModal');
        var container = document.getElementById('addressSearchPostcodeContainer');
        if (!modal || !container) return;

        modal.classList.remove('hidden');
        container.innerHTML = '';

        new daum.Postcode({
            oncomplete: function(data) {
                var addr = data.userSelectedType === 'R' ? data.roadAddress : data.jibunAddress;
                var extra = '';
                if (data.userSelectedType === 'R') {
                    if (data.bname && /[동|로|가]$/g.test(data.bname)) extra += data.bname;
                    if (data.buildingName && data.apartment === 'Y') extra += (extra ? ', ' + data.buildingName : data.buildingName);
                    if (extra) addr += ' (' + extra + ')';
                }
                var el = document.getElementById('address');
                if (el) el.value = addr;
                window.addressConfirmed = false;
                closeAddressSearchModal();
            }
        }).embed(container);
    }

    function closeAddressSearchModal() {
        var modal = document.getElementById('addressSearchModal');
        if (modal) modal.classList.add('hidden');
    }
    window.closeAddressSearchModal = closeAddressSearchModal;

    var selectedBrochure = null;

    async function loadBrochureDropdown() {
        var optionsEl = document.getElementById('brochureDropdownOptions');
        var loadingEl = document.getElementById('brochureDropdownLoading');
        var panelEl = document.getElementById('brochureDropdownPanel');
        if (!optionsEl || !panelEl) return;
        try {
            var raw = await BrochureAPI.getAll();
            var list = Array.isArray(raw) ? raw : (raw && Array.isArray(raw.data) ? raw.data : (raw && Array.isArray(raw.brochures) ? raw.brochures : []));
            var brochures = list.filter(function(b) { return b != null && (b.id !== undefined && b.id !== null); });
            window._brochuresListV2 = brochures;
            if (loadingEl) loadingEl.classList.add('hidden');
            optionsEl.innerHTML = '';
            if (brochures.length === 0) {
                optionsEl.innerHTML = '<p class="col-span-2 p-4 text-sm text-gray-500 dark:text-gray-400">등록된 브로셔가 없습니다.</p>';
                return;
            }
            brochures.forEach(function(b) {
                var id = b.id;
                var name = (b.name || '').trim();
                var nameEsc = escapeHtml(name);
                var imgSrc = safeBrochureImageUrl(b.image_url);
                var opt = document.createElement('button');
                opt.type = 'button';
                opt.className = 'flex items-center gap-3 p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-left w-full transition-colors brochure-dropdown-option';
                opt.dataset.brochureId = id;
                opt.dataset.brochureName = name;
                opt.dataset.brochureImage = imgSrc;
                opt.innerHTML = '<img src="' + escapeHtml(imgSrc) + '" alt="" class="w-14 h-20 object-cover rounded shrink-0" onerror="this.onerror=null;this.src=window.BROCHURE_PLACEHOLDER_IMG">' +
                    '<span class="text-sm font-medium text-gray-900 dark:text-white truncate flex-1">' + nameEsc + '</span>';
                optionsEl.appendChild(opt);
            });
            bindBrochureHoverPreview();
        } catch (err) {
            console.error('브로셔 목록 로드 오류:', err);
            if (loadingEl) loadingEl.classList.add('hidden');
            optionsEl.innerHTML = '<p class="col-span-2 p-4 text-sm text-red-500 dark:text-red-400">목록을 불러오지 못했습니다.</p>';
        }
    }

    function setSelectedBrochure(b) {
        selectedBrochure = b;
        var thumb = document.getElementById('brochureDropdownThumb');
        var label = document.getElementById('brochureDropdownLabel');
        if (!label) return;
        if (b) {
            if (thumb) {
                thumb.src = safeBrochureImageUrl(b.image_url);
                thumb.classList.remove('hidden');
            }
            label.textContent = b.name || '';
        } else {
            if (thumb) { thumb.src = ''; thumb.classList.add('hidden'); }
            label.textContent = '추가할 브로셔를 선택하세요';
        }
    }

    function addSelectedBrochureRow() {
        if (!selectedBrochure) {
            showAlertV2('브로셔를 선택해 주세요.', 'danger');
            return;
        }
        var qtyEl = document.getElementById('brochureQuantity');
        var qty = qtyEl ? (parseInt(qtyEl.value, 10) || 0) : 0;
        if (qty < 10 || qty % 10 !== 0) {
            showAlertV2('수량은 10권 단위(10, 20, 30…)로 입력해 주세요.', 'danger');
            return;
        }
        var tbody = document.getElementById('brochureSelectedBody');
        var emptyRow = document.getElementById('brochureSelectedEmpty');
        if (!tbody) return;
        if (emptyRow) emptyRow.classList.add('hidden');
        var tr = document.createElement('tr');
        tr.className = 'brochure-selected-row';
        tr.dataset.brochureId = selectedBrochure.id;
        tr.dataset.brochureName = selectedBrochure.name || '';
        tr.dataset.quantity = String(qty);
        tr.innerHTML = '<td class="py-2 px-3 text-gray-900 dark:text-white">' + escapeHtml(selectedBrochure.name || '') + '</td>' +
            '<td class="py-2 px-3 text-right text-gray-700 dark:text-gray-300">' + qty + '권</td>' +
            '<td class="py-2 px-2"><button type="button" class="brochure-remove-row text-red-600 dark:text-red-400 hover:underline text-xs">삭제</button></td>';
        tbody.appendChild(tr);
        qtyEl.value = '10';
        setSelectedBrochure(null);
    }

    var brochurePreviewHideTimer = null;

    function bindBrochureHoverPreview() {
        var optionsEl = document.getElementById('brochureDropdownOptions');
        var previewEl = document.getElementById('brochureHoverPreview');
        var previewImg = document.getElementById('brochureHoverPreviewImg');
        var previewName = document.getElementById('brochureHoverPreviewName');
        if (!optionsEl || !previewEl || !previewImg || !previewName) return;
        optionsEl.removeEventListener('mouseenter', onBrochureOptionHover);
        optionsEl.removeEventListener('mouseleave', onBrochureOptionLeave);
        optionsEl.addEventListener('mouseenter', onBrochureOptionHover, true);
        optionsEl.addEventListener('mouseleave', onBrochureOptionLeave, true);
    }

    function onBrochureOptionHover(e) {
        if (brochurePreviewHideTimer) {
            clearTimeout(brochurePreviewHideTimer);
            brochurePreviewHideTimer = null;
        }
        var opt = e.target && e.target.closest && e.target.closest('.brochure-dropdown-option');
        if (!opt) return;
        var imgSrc = safeBrochureImageUrl(opt.dataset.brochureImage);
        var name = opt.dataset.brochureName || '';
        var previewEl = document.getElementById('brochureHoverPreview');
        var previewImg = document.getElementById('brochureHoverPreviewImg');
        var previewName = document.getElementById('brochureHoverPreviewName');
        if (previewImg) previewImg.src = imgSrc;
        if (previewName) previewName.textContent = name;
        if (previewEl) {
            previewEl.classList.remove('hidden');
            var wrap = document.getElementById('brochureDropdownWrap');
            if (wrap) {
                var rect = opt.getBoundingClientRect();
                var wrapRect = wrap.getBoundingClientRect();
                previewEl.style.top = (rect.top - wrapRect.top) + 'px';
            }
        }
    }

    function onBrochureOptionLeave(e) {
        var optionsEl = document.getElementById('brochureDropdownOptions');
        var related = e.relatedTarget;
        if (related && optionsEl && optionsEl.contains(related)) return;
        if (brochurePreviewHideTimer) clearTimeout(brochurePreviewHideTimer);
        brochurePreviewHideTimer = setTimeout(function() {
            brochurePreviewHideTimer = null;
            var previewEl = document.getElementById('brochureHoverPreview');
            if (previewEl) previewEl.classList.add('hidden');
        }, 80);
    }

    function collectBrochureItemsV2FromList() {
        var items = [];
        var rows = document.querySelectorAll('#brochureSelectedBody .brochure-selected-row');
        rows.forEach(function(tr) {
            var id = tr.dataset.brochureId;
            var name = tr.dataset.brochureName || '';
            var qty = parseInt(tr.dataset.quantity, 10) || 0;
            if (id && qty > 0) items.push({ brochure: id, brochureName: name, quantity: qty });
        });
        return items;
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
        return collectBrochureItemsV2FromList();
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
        var addressSearchCloseBtn = document.getElementById('addressSearchCloseBtn');
        if (addressSearchCloseBtn) addressSearchCloseBtn.addEventListener('click', closeAddressSearchModal);
        loadBrochureDropdown();
        initOrgNameAutocomplete();

        var trigger = document.getElementById('brochureDropdownTrigger');
        var panel = document.getElementById('brochureDropdownPanel');
        var wrap = document.getElementById('brochureDropdownWrap');
        if (trigger && panel) {
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                panel.classList.toggle('hidden');
            });
        }
        document.getElementById('brochureDropdownOptions').addEventListener('click', function(e) {
            var opt = e.target.closest('.brochure-dropdown-option');
            if (!opt) return;
            selectedBrochure = {
                id: opt.dataset.brochureId,
                name: opt.dataset.brochureName || '',
                image_url: opt.dataset.brochureImage || ''
            };
            setSelectedBrochure(selectedBrochure);
            if (panel) panel.classList.add('hidden');
        });
        document.getElementById('brochureAddBtn').addEventListener('click', function() {
            addSelectedBrochureRow();
        });
        document.getElementById('brochureSelectedBody').addEventListener('click', function(e) {
            var btn = e.target.closest('.brochure-remove-row');
            if (!btn) return;
            var row = btn.closest('tr');
            if (row) row.remove();
            var rows = document.querySelectorAll('#brochureSelectedBody .brochure-selected-row');
            var emptyRow = document.getElementById('brochureSelectedEmpty');
            if (emptyRow && rows.length === 0) emptyRow.classList.remove('hidden');
        });
        document.addEventListener('click', function(e) {
            if (panel && !panel.classList.contains('hidden') && wrap && !wrap.contains(e.target)) {
                panel.classList.add('hidden');
                if (brochurePreviewHideTimer) { clearTimeout(brochurePreviewHideTimer); brochurePreviewHideTimer = null; }
                var previewEl = document.getElementById('brochureHoverPreview');
                if (previewEl) previewEl.classList.add('hidden');
            }
        });

        var verifyBtn = document.getElementById('verify-btn');
        var verifyCodeEl = document.getElementById('verify-code');
        var verifyStatusEl = document.getElementById('verify-status');
        function setVerifyStatus(text, isError) {
            if (!verifyStatusEl) return;
            verifyStatusEl.textContent = text || '';
            verifyStatusEl.className = 'text-sm ' + (isError ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400');
        }
        function updateVerifyBtnLabel() {
            if (!verifyBtn || !verifyCodeEl) return;
            var code = (verifyCodeEl.value || '').trim();
            verifyBtn.textContent = code ? '인증 확인' : '인증번호 발송';
        }
        if (verifyCodeEl) {
            verifyCodeEl.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
                updateVerifyBtnLabel();
            });
        }
        if (verifyBtn && verifyCodeEl && verifyStatusEl) {
            verifyBtn.addEventListener('click', async function() {
                var phone = (document.getElementById('phone') && document.getElementById('phone').value) || '';
                var code = (verifyCodeEl.value || '').trim();
                if (!phone.trim()) {
                    setVerifyStatus('전화번호를 먼저 입력해 주세요.', true);
                    return;
                }
                if (!code) {
                    verifyBtn.disabled = true;
                    setVerifyStatus('발송 중...');
                    try {
                        await VerificationAPI.sendCode(phone);
                        var wrap = document.getElementById('verify-code-wrap');
                        if (wrap) wrap.classList.remove('hidden');
                        setVerifyStatus('인증번호가 발송되었습니다. 문자를 확인한 뒤 입력해 주세요.');
                        if (verifyCodeEl) { verifyCodeEl.value = ''; verifyCodeEl.focus(); }
                    } catch (err) {
                        setVerifyStatus(err.message || '발송에 실패했습니다.', true);
                    }
                    verifyBtn.disabled = false;
                } else {
                    verifyBtn.disabled = true;
                    setVerifyStatus('확인 중...');
                    try {
                        await VerificationAPI.verify(phone, code);
                        setVerifyStatus('인증이 완료되었습니다.');
                        window.phoneVerified = true;
                        var submitBtn = document.getElementById('brochureSubmitBtn');
                        if (submitBtn) submitBtn.disabled = false;
                        // 인증 완료 시 버튼 비활성화 유지
                    } catch (err) {
                        setVerifyStatus(err.message || '인증에 실패했습니다.', true);
                        verifyBtn.disabled = false;
                    }
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAddressSearchModal();
        });
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
        phoneEl.addEventListener('input', function() {
            if (window.phoneVerified) {
                window.phoneVerified = false;
                var submitBtn = document.getElementById('brochureSubmitBtn');
                if (submitBtn) submitBtn.disabled = true;
                var verifyBtn = document.getElementById('verify-btn');
                if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = '인증번호 발송'; }
            }
        });
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
            var requestData = {
                date: date,
                schoolname: schoolname.trim(),
                address: address.trim(),
                phone: phone.trim(),
                contact_id: null,
                contact_name: null,
                brochures: brochures.map(function(b) { return { brochure: b.brochure, brochureName: b.brochureName, quantity: b.quantity }; }),
                invoices: []
            };
            await RequestAPI.create(requestData);
            window.location.href = '{{ route("co.gs-brochure.request.success") }}';
        } catch (err) {
            showAlertV2('신청 저장 중 오류가 발생했습니다: ' + err.message, 'danger');
        }
    });
})();
</script>
@endpush
