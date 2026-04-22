<!DOCTYPE html>
<html class="light" lang="ko">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Brochure Management Dashboard</title>
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
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    @php
        $parseSizeToBytes = static function (?string $value): int {
            $value = trim((string) $value);
            if ($value === '') {
                return 0;
            }
            if (! preg_match('/^\s*(\d+)\s*([KMG]?)\s*$/i', $value, $matches)) {
                return (int) $value;
            }

            $bytes = (int) $matches[1];
            $unit = strtoupper($matches[2] ?? '');
            if ($unit === 'G') {
                return $bytes * 1024 * 1024 * 1024;
            }
            if ($unit === 'M') {
                return $bytes * 1024 * 1024;
            }
            if ($unit === 'K') {
                return $bytes * 1024;
            }

            return $bytes;
        };
        $phpUploadLimitBytes = $parseSizeToBytes(ini_get('upload_max_filesize'));
        $phpPostLimitBytes = $parseSizeToBytes(ini_get('post_max_size'));
        $appImageLimitBytes = 30 * 1024 * 1024;
        $effectiveUploadMaxBytes = max(1, min(array_filter([
            $phpUploadLimitBytes,
            $phpPostLimitBytes,
            $appImageLimitBytes,
        ], static fn (int $value): bool => $value > 0) ?: [$appImageLimitBytes]));
    @endphp
    <script>
        window.API_BASE_URL = '{{ url("/api/gs-brochure") }}';
        window.GS_BROCHURE_UPLOAD_MAX_BYTES = {{ $effectiveUploadMaxBytes }};
    </script>
    <script src="{{ asset('js/gs-brochure-api.js') }}"></script>
    <style>#dashboardSidebar.open{transform:translateX(0);}</style>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-slate-900 dark:text-white overflow-hidden">
    <!-- Mobile header -->
    <header class="md:hidden sticky top-0 z-20 flex items-center justify-between px-4 py-3 bg-white dark:bg-[#1e1e1e] border-b border-slate-200 dark:border-slate-800">
        <button type="button" id="dashboardMenuBtn" class="p-2 -ml-2 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-label="메뉴 열기">
            <span class="material-symbols-outlined" style="font-size: 24px;">menu</span>
        </button>
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary" style="font-size: 24px;">library_books</span>
            <span class="font-bold text-slate-900 dark:text-white text-sm">Brochure Management</span>
        </div>
        <div class="w-10"></div>
    </header>
    <div id="dashboardOverlay" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden" aria-hidden="true" role="button" tabindex="-1" onclick="document.getElementById('dashboardSidebar').classList.remove('open');this.classList.add('hidden');"></div>
    <div class="flex h-screen w-full">
        <!-- Side Navigation (drawer on mobile) -->
        <div id="dashboardSidebar" class="fixed inset-y-0 left-0 z-30 w-64 flex flex-col bg-white dark:bg-[#1e1e1e] border-r border-slate-200 dark:border-slate-800 h-full transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-200 ease-out shrink-0">
            <div class="p-6 pb-2">
                <div class="flex items-center gap-3 mb-8">
                    <div class="rounded-full size-10 bg-primary/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary" style="font-size: 24px;">library_books</span>
                    </div>
                    <div class="flex flex-col">
                        <h1 class="text-slate-900 dark:text-white text-base font-bold leading-normal">BrochureSys</h1>
                        <p class="text-slate-500 dark:text-slate-400 text-xs font-normal">Admin Portal</p>
                    </div>
                </div>
                <nav class="flex flex-col gap-1">
                    <a href="#" data-nav="dashboard" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">grid_view</span>
                        <span class="text-sm font-medium">대시보드</span>
                    </a>
                    <div class="warehouse-nav-group">
                        <button type="button" id="warehouseNavToggle" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left">
                            <span class="material-symbols-outlined" style="font-size: 24px;">warehouse</span>
                            <span class="text-sm font-medium flex-1">물류창고</span>
                            <span class="material-symbols-outlined warehouse-chevron text-slate-400 transition-transform" style="font-size: 20px;">expand_more</span>
                        </button>
                        <div id="warehouseSubmenu" class="hidden pl-4 mt-0.5 space-y-0.5 border-l-2 border-slate-200 dark:border-slate-700 ml-5">
                            <a href="#" data-nav="inventory" class="nav-link flex items-center gap-2 py-2 px-3 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-300 text-sm transition-colors">
                                <span class="material-symbols-outlined" style="font-size: 18px;">inventory_2</span>
                                물류센터 브로셔 재고관리
                            </a>
                            <a href="#" data-nav="logistics" class="nav-link flex items-center gap-2 py-2 px-3 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-300 text-sm transition-colors">
                                <span class="material-symbols-outlined" style="font-size: 18px;">local_shipping</span>
                                운송장 입력
                            </a>
                        </div>
                    </div>
                    <a href="#" data-nav="inventory2" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">inventory_2</span>
                        <span class="text-sm font-medium">본사 브로셔 재고관리</span>
                    </a>
                    <a href="{{ route('co.gs-brochure.request') }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">description</span>
                        <span class="text-sm font-medium">브로셔 신청</span>
                    </a>
                    <!-- <a href="{{ route('co.gs-brochure.request', ['view' => 'list']) }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">campaign</span>
                        <span class="text-sm font-medium">신청 내역</span>
                    </a> -->
                    <div class="outbound-nav-group">
                        <button type="button" id="outboundNavToggle" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left">
                            <span class="material-symbols-outlined" style="font-size: 24px;">inventory</span>
                            <span class="text-sm font-medium flex-1">입출고 내역</span>
                            <span class="material-symbols-outlined outbound-chevron text-slate-400 transition-transform" style="font-size: 20px;">expand_more</span>
                        </button>
                        <div id="outboundSubmenu" class="outbound-submenu hidden pl-4 mt-0.5 space-y-0.5 border-l-2 border-slate-200 dark:border-slate-700 ml-5">
                            <a href="#" data-nav="outbound" data-scroll="warehouse" class="nav-link outbound-sub-link flex items-center gap-2 py-2 px-3 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-300 text-sm transition-colors">
                                <span class="material-symbols-outlined" style="font-size: 18px;">warehouse</span>
                                물류센터 입출고 내역
                            </a>
                            <a href="#" data-nav="outbound" data-scroll="hq" class="nav-link outbound-sub-link flex items-center gap-2 py-2 px-3 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-300 text-sm transition-colors">
                                <span class="material-symbols-outlined" style="font-size: 18px;">business</span>
                                본사 입출고 내역
                            </a>
                        </div>
                    </div>
                    <a href="#" data-nav="institutions" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">business</span>
                        <span class="text-sm font-medium">기관관리</span>
                    </a>
                    <a href="#" data-nav="settings" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 24px;">settings</span>
                        <span class="text-sm font-medium">설정</span>
                    </a>
                </nav>
            </div>
            <div class="mt-auto px-6 pb-3">
                <a href="{{ url('/') }}"
                   onclick="logout(); return false;"
                   class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-slate-600 dark:text-slate-400 bg-transparent hover:bg-primary/10 dark:hover:bg-primary/20 text-sm font-medium transition-colors">
                    <span class="material-symbols-outlined" style="font-size: 18px;">logout</span>
                    Mocchi로 돌아가기
                </a>
            </div>
            <div class="p-6 border-t border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-full size-9 bg-primary/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary" style="font-size: 20px;">person</span>
                    </div>
                    <div class="flex flex-col">
                        <p class="text-sm font-medium text-slate-900 dark:text-white" id="sidebarUsername">{{ auth()->user()?->preferredDisplayName() ?? 'Admin' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">관리자</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-h-0 overflow-y-auto overflow-x-auto min-w-0">
            <header class="w-full px-4 sm:px-8 py-4 sm:py-6 flex flex-wrap justify-between items-end gap-4 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-[#1e1e1e]/80 sticky top-0 z-10">
                <div class="flex flex-col gap-1">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white" id="pageTitle">대시보드</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm">GS 브로셔 관리 시스템</p>
                </div>
            </header>

            <div id="alert" class="mx-4 sm:mx-8 mt-4 hidden rounded-lg px-4 py-3 text-sm" role="alert"></div>

            <!-- Dashboard Section -->
            <section id="section-dashboard" class="content-section px-4 sm:px-8 py-4 sm:py-6 flex flex-col gap-6 min-w-0">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="statsGrid">
                    <!-- 동적 통계 카드 -->
                </div>
                <div class="flex flex-col lg:flex-row gap-6 flex-1 min-w-0">
                    <div class="flex-[2] min-w-0 bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">재고 추이</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">품목별 재고 현황</p>
                        <div class="overflow-x-auto -mx-px">
                            <table class="w-full text-sm min-w-[480px]">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-700">
                                        <th class="text-left py-2 px-3 text-slate-600 dark:text-slate-400 font-medium">브로셔명</th>
                                        <th class="text-right py-2 px-3 text-slate-600 dark:text-slate-400 font-medium">물류센터 재고</th>
                                        <th class="text-center py-2 px-3 text-slate-600 dark:text-slate-400 font-medium">물류 상태</th>
                                        <th class="text-right py-2 px-3 text-slate-600 dark:text-slate-400 font-medium">본사 재고</th>
                                        <th class="text-center py-2 px-3 text-slate-600 dark:text-slate-400 font-medium">본사 상태</th>
                                    </tr>
                                </thead>
                                <tbody id="stockTableBody">
                                    <!-- 품목별 재고 동적 -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="flex-[1] bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">재고 부족 알림(물류창고)</h3>
                            <a href="#" data-nav="inventory" class="text-primary text-sm font-medium hover:underline">전체 보기</a>
                        </div>
                        <div class="flex flex-col gap-4 overflow-y-auto" id="lowStockAlertsList">
                            <!-- 동적 재고 부족 목록 -->
                        </div>
                    </div>
                </div>
            </section>

            <!-- Inventory Section -->
            <section id="section-inventory" class="content-section px-4 sm:px-8 py-4 sm:py-6 hidden min-w-0">
                <div class="flex flex-col gap-4 mb-4 sm:flex-row sm:flex-wrap sm:justify-between sm:items-center">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white shrink-0">물류센터 브로셔 관리</h2>
                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                        <button type="button" onclick="showSection('outbound'); downloadStockHistoryAll();" class="flex items-center gap-2 px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex-1 sm:flex-none min-w-0">입출고 내역 다운로드</button>
                        <button type="button" onclick="openBrochureModal(null, 'warehouse')" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white transition-colors flex-1 sm:flex-none min-w-0">
                            <span class="material-symbols-outlined shrink-0" style="font-size: 18px;">add</span>
                            브로셔 추가
                        </button>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm min-w-0">
                    <div class="overflow-x-auto w-full rounded-b-xl" style="max-width: 100%;">
                        <table class="w-full text-sm min-w-[600px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                    <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300 w-16">이미지</th>
                                    <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">브로셔명</th>
                                    <th class="text-right py-3 px-4 font-medium text-slate-700 dark:text-slate-300">물류센터 재고</th>
                                    <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">물류 상태</th>
                                    <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">마지막 입고</th>
                                    <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">입고</th>
                                    <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">작업</th>
                                </tr>
                            </thead>
                            <tbody id="brochureTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Inventory Section 2 (복제) -->
            <section id="section-inventory2" class="content-section px-4 sm:px-8 py-4 sm:py-6 hidden min-w-0">
                <div class="flex flex-col gap-4 mb-4 sm:flex-row sm:flex-wrap sm:justify-between sm:items-center">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white shrink-0">본사 브로셔 관리</h2>
                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                        <button type="button" onclick="showSection('outbound'); downloadStockHistoryAll();" class="flex items-center gap-2 px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex-1 sm:flex-none min-w-0">입출고 내역 다운로드</button>
                        <button type="button" disabled class="flex items-center gap-2 px-4 py-2 bg-primary/60 rounded-lg text-sm font-medium text-white/80 cursor-not-allowed transition-colors flex-1 sm:flex-none min-w-0" title="본사에서는 물류센터에서만 브로셔를 추가할 수 있습니다">
                            <span class="material-symbols-outlined shrink-0" style="font-size: 18px;">add</span>
                            브로셔 추가
                        </button>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm min-w-0">
                    <div class="overflow-x-auto w-full rounded-b-xl" style="max-width: 100%;">
                        <table class="w-full text-sm min-w-[600px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                    <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300 w-16">이미지</th>
                                    <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">브로셔명</th>
                                    <th class="text-right py-3 px-4 font-medium text-slate-700 dark:text-slate-300">본사 재고</th>
                                    <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">본사 상태</th>
                                    <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">마지막 입고</th>
                                    <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">입고</th>
                                    <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">작업</th>
                                </tr>
                            </thead>
                            <tbody id="brochureTableBody2"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- 입출고 내역 (통합) -->
            <section id="section-outbound" class="content-section px-4 sm:px-8 py-4 sm:py-6 hidden min-w-0">
                <div class="flex flex-col gap-4 mb-4 sm:flex-row sm:flex-wrap sm:justify-between sm:items-start">
                    <div class="min-w-0">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">입출고 내역</h2>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">입고·출고·수정·이동 내역을 조회하고 Excel로 다운로드할 수 있습니다.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                        <button type="button" onclick="downloadStockHistoryFiltered()" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white transition-colors flex-1 sm:flex-none min-w-0">
                            <span class="material-symbols-outlined shrink-0" style="font-size: 18px;">cloud_download</span>
                            <span class="truncate">현재 목록 Excel 다운로드</span>
                        </button>
                        <button type="button" onclick="downloadStockHistoryAll()" class="flex items-center gap-2 px-4 py-2 border border-slate-200 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex-1 sm:flex-none min-w-0">
                            <span class="material-symbols-outlined shrink-0" style="font-size: 18px;">download</span>
                            <span class="truncate">전체 입출고 Excel 다운로드</span>
                        </button>
                    </div>
                </div>
                <!-- 입출고 내역 필터 -->
                <div class="mb-4 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">구분</label>
                            <select id="outboundFilterType" onchange="applyOutboundTypeFilter()" class="w-full min-w-[120px] px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                                <option value="">전체</option>
                                <option value="입고">입고</option>
                                <option value="출고">출고</option>
                                <option value="수정">수정</option>
                                <option value="이동">이동</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">날짜(시작)</label>
                            <input type="date" id="outboundFilterDateFrom" class="w-full min-w-[140px] px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">날짜(끝)</label>
                            <input type="date" id="outboundFilterDateTo" class="w-full min-w-[140px] px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">브로셔명</label>
                            <input type="text" id="outboundFilterBrochureName" placeholder="검색" class="w-full min-w-[160px] px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">기관명</label>
                            <input type="text" id="outboundFilterSchoolName" placeholder="검색" class="w-full min-w-[160px] px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">&nbsp;</label>
                            <div class="flex gap-2">
                                <button type="button" onclick="applyOutboundFilter()" class="px-4 py-2 rounded-lg bg-primary hover:bg-primary/90 text-white text-sm font-medium">필터 적용</button>
                                <button type="button" onclick="resetOutboundFilter()" class="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 text-sm font-medium">초기화</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- 물류센터 입출고 내역 -->
                <div id="outboundBlockWarehouse" class="mb-8 scroll-mt-4">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary" style="font-size: 22px;">warehouse</span>
                        물류센터 입출고 내역
                    </h3>
                    <div class="bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300 outbound-wh-th-type" style="display:none;">구분</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">날짜</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">브로셔명</th>
                                        <th class="text-right py-3 px-4 font-medium text-slate-700 dark:text-slate-300">수량</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">담당자</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">기관명</th>
                                        <th class="text-right py-3 px-4 font-medium text-slate-700 dark:text-slate-300">재고(전→후)</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">처리일시</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">메모</th>
                                    </tr>
                                </thead>
                                <tbody id="outboundWarehouseTableBody"></tbody>
                            </table>
                        </div>
                        <div id="outboundWarehouseEmpty" class="hidden py-12 text-center text-slate-500 dark:text-slate-400 text-sm">물류센터 입출고 내역이 없습니다.</div>
                        <div id="outboundWarehousePaginationWrap" class="hidden px-4 py-3 border-t border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                            <p id="outboundWarehousePaginationInfo" class="text-sm text-slate-600 dark:text-slate-400"></p>
                            <ul id="outboundWarehousePagination" class="flex items-center gap-2 list-none flex-wrap"></ul>
                        </div>
                    </div>
                </div>
                <!-- 본사 입출고 내역 -->
                <div id="outboundBlockHq" class="scroll-mt-4">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary" style="font-size: 22px;">business</span>
                        본사 입출고 내역
                    </h3>
                    <div class="bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300 outbound-hq-th-type" style="display:none;">구분</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">날짜</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">브로셔명</th>
                                        <th class="text-right py-3 px-4 font-medium text-slate-700 dark:text-slate-300">수량</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">담당자</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">기관명</th>
                                        <th class="text-right py-3 px-4 font-medium text-slate-700 dark:text-slate-300">재고(전→후)</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">처리일시</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">메모</th>
                                    </tr>
                                </thead>
                                <tbody id="outboundHqTableBody"></tbody>
                            </table>
                        </div>
                        <div id="outboundHqEmpty" class="hidden py-12 text-center text-slate-500 dark:text-slate-400 text-sm">본사 입출고 내역이 없습니다.</div>
                        <div id="outboundHqPaginationWrap" class="hidden px-4 py-3 border-t border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                            <p id="outboundHqPaginationInfo" class="text-sm text-slate-600 dark:text-slate-400"></p>
                            <ul id="outboundHqPagination" class="flex items-center gap-2 list-none flex-wrap"></ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Logistics (운송장 입력) Section -->
            <section id="section-logistics" class="content-section px-4 sm:px-8 py-4 sm:py-6 hidden">
                <div class="flex flex-wrap justify-between items-end gap-4 mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">운송장 입력</h2>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">엑셀 다운로드 후 신청된 브로셔의 운송장 번호를 입력하세요.</p>
                    </div>
                    <button type="button" onclick="downloadLogisticsExcel()" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white transition-colors">
                        <span class="material-symbols-outlined" style="font-size: 20px;">cloud_download</span>
                        엑셀 다운로드
                    </button>
                </div>
                <form id="logisticsForm" class="bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">신청 내역 및 운송장 번호 입력</h3>
                    <div id="rowsContainer"></div>
                    <div class="flex flex-wrap justify-center items-center gap-4 mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                        <span class="text-slate-500 dark:text-slate-400 text-sm" id="paginationInfo"></span>
                        <ul class="flex flex-wrap list-none gap-2 p-0 m-0" id="pagination"></ul>
                    </div>
                </form>
            </section>

            <!-- Institutions Section (기관 관리) -->
            <section id="section-institutions" class="content-section px-4 sm:px-8 py-4 sm:py-6 hidden min-w-0">
                <div class="space-y-6">
                    <div>
                        <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">기관 목록</h2>
                            <button type="button" onclick="openInstitutionModal()" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white transition-colors">+ 기관 추가</button>
                        </div>
                        <div class="flex flex-wrap gap-3 items-end mb-4">
                            <div class="flex flex-wrap gap-2 items-center">
                                <input type="text" id="institutionSearch" placeholder="기관명 검색" class="px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm min-w-[180px]" onkeydown="if(event.key==='Enter')applyInstitutionFilters()">
                                <select id="institutionFilter" onchange="applyInstitutionFilters()" class="px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                                    <option value="">전체</option>
                                    <option value="1">활성</option>
                                    <option value="0">비활성</option>
                                </select>
                                <button type="button" onclick="applyInstitutionFilters()" class="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-medium">검색</button>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="bulkInstitutionSetActive(true)" class="px-3 py-2 rounded-lg border border-green-600 dark:border-green-500 text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-950/30 text-sm font-medium">선택 항목 활성화</button>
                                <button type="button" onclick="bulkInstitutionSetActive(false)" class="px-3 py-2 rounded-lg border border-slate-400 dark:border-slate-500 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 text-sm font-medium">선택 항목 비활성화</button>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                            <div id="institutionsLoading" class="p-6 text-center text-slate-500 dark:text-slate-400 text-sm">불러오는 중...</div>
                            <table class="w-full text-sm hidden" id="institutionsTable">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                        <th class="w-10 py-3 px-2 text-center">
                                            <input type="checkbox" id="institutionSelectAll" title="전체 선택" class="rounded border-slate-300 dark:border-slate-600 text-primary focus:ring-primary">
                                        </th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">기관명</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">유형</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">설명</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">주소</th>
                                        <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">상태</th>
                                        <th class="text-right py-3 px-4 font-medium text-slate-700 dark:text-slate-300">정렬</th>
                                        <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">작업</th>
                                    </tr>
                                </thead>
                                <tbody id="institutionsTableBody"></tbody>
                            </table>
                            <p id="institutionsEmpty" class="hidden p-6 text-center text-slate-500 dark:text-slate-400 text-sm">등록된 기관이 없습니다.</p>
                            <div id="institutionsPagination" class="hidden flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                                <p id="institutionsPaginationInfo" class="text-sm text-slate-600 dark:text-slate-400"></p>
                                <div id="institutionsPaginationButtons" class="flex items-center gap-1"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Settings Section -->
            <section id="section-settings" class="content-section px-4 sm:px-8 py-4 sm:py-6 hidden">
                <div class="space-y-8">
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">담당자 관리</h2>
                            <button type="button" onclick="openContactModal()" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white transition-colors">+ 담당자 추가</button>
                        </div>
                        <div class="bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">담당자명</th>
                                        <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">작업</th>
                                    </tr>
                                </thead>
                                <tbody id="contactTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">관리자 계정 관리</h2>
                        <div class="mb-6 p-4 bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">새 계정 추가</h3>
                            <form id="addAdminForm" class="flex flex-wrap gap-3 items-end">
                                <div class="min-w-[180px]">
                                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">사용자명</label>
                                    <input type="text" id="newUsername" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"/>
                                </div>
                                <div class="min-w-[180px]">
                                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">비밀번호</label>
                                    <input type="password" id="newPassword" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"/>
                                </div>
                                <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white transition-colors">계정 추가</button>
                            </form>
                        </div>
                        <div class="bg-white dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">ID</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">사용자명</th>
                                        <th class="text-left py-3 px-4 font-medium text-slate-700 dark:text-slate-300">생성일</th>
                                        <th class="text-center py-3 px-4 font-medium text-slate-700 dark:text-slate-300">작업</th>
                                    </tr>
                                </thead>
                                <tbody id="adminUsersTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <!-- 데이터 초기화 (위험 구역) -->
                    <div class="p-6 rounded-xl border-2 border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-950/20">
                        <h2 class="text-xl font-bold text-red-700 dark:text-red-400 mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined" style="font-size: 24px;">warning</span>
                            데이터 초기화
                        </h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">아래 작업은 되돌릴 수 없습니다. 실행 전 반드시 확인하세요.</p>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" onclick="openResetConfirmModal('stock_history')" class="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-medium transition-colors">입출고 내역만 삭제</button>
                            <button type="button" onclick="openResetConfirmModal('requests')" class="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-medium transition-colors">신청 내역 + 운송장 삭제</button>
                            <button type="button" onclick="openResetConfirmModal('brochure_stock')" class="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-medium transition-colors">재고 수량만 0으로</button>
                            <button type="button" onclick="openResetConfirmModal('full')" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors">전체 초기화</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Reset data confirm modal (2-step) -->
    <div id="resetConfirmModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeResetConfirmModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-[#1e1e1e] rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="resetModalTitle" class="text-lg font-bold text-slate-900 dark:text-white">데이터 초기화</h3>
                    <button type="button" onclick="closeResetConfirmModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">&times;</button>
                </div>
                <div id="resetModalStep1">
                    <p id="resetModalMessage" class="text-slate-700 dark:text-slate-300 mb-6"></p>
                    <p class="text-sm text-red-600 dark:text-red-400 font-medium mb-6">이 작업은 되돌릴 수 없습니다.</p>
                    <div class="flex gap-2 justify-end">
                        <button type="button" onclick="closeResetConfirmModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">취소</button>
                        <button type="button" onclick="resetModalToStep2()" class="px-4 py-2 bg-slate-600 hover:bg-slate-700 rounded-lg text-sm font-medium text-white">다음</button>
                    </div>
                </div>
                <div id="resetModalStep2" class="hidden">
                    <p class="text-slate-700 dark:text-slate-300 mb-2">실행하려면 아래 입력란에 <strong>초기화</strong> 를 입력하세요.</p>
                    <input type="text" id="resetConfirmInput" placeholder="초기화" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white mb-6" autocomplete="off" onkeydown="if(event.key==='Enter') event.preventDefault(), executeReset();"/>
                    <div class="flex gap-2 justify-end">
                        <button type="button" onclick="resetModalToStep1()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">이전</button>
                        <button type="button" id="resetModalExecuteBtn" onclick="executeReset()" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium text-white">실행</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals (same IDs, Tailwind styled) -->
    <div id="brochureModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeBrochureModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-[#1e1e1e] rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="brochureModalTitle" class="text-lg font-bold text-slate-900 dark:text-white">브로셔 추가</h3>
                    <button type="button" onclick="closeBrochureModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">&times;</button>
                </div>
                <form id="brochureForm">
                    <input type="hidden" id="brochureId" value="">
                    <div class="form-group mb-4">
                        <label for="brochureName" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">브로셔명</label>
                        <input type="text" id="brochureName" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="brochureImageUrl" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">이미지 URL (선택)</label>
                        <input type="url" id="brochureImageUrl" placeholder="https://..." class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="brochureImageFile" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">또는 이미지 파일 업로드 (선택)</label>
                        <input type="file" id="brochureImageFile" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,image/avif" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                        <img id="brochureImagePreview" src="" alt="" class="mt-2 w-16 h-16 object-cover rounded hidden"/>
                    </div>
                    <div class="form-group mb-4 hidden" id="stockGroup">
                        <label for="brochureStock" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">초기 재고</label>
                        <input type="number" id="brochureStock" min="0" value="0" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="flex gap-2 justify-end mt-6">
                        <button type="button" onclick="closeBrochureModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">취소</button>
                        <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white">저장</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <input type="file" id="brochureRowImageFile" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,image/avif" class="hidden" aria-hidden="true"/>

    <div id="brochureImageMenuDropdown" class="hidden fixed z-50 min-w-[120px] py-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-lg"
        style="left: 0; top: 0;">
        <button type="button" id="brochureImageMenuUpload" class="w-full text-left px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 whitespace-nowrap">이미지 업로드</button>
        <button type="button" id="brochureImageMenuDelete" class="w-full text-left px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-slate-100 dark:hover:bg-slate-700 whitespace-nowrap">이미지 삭제</button>
    </div>

    <div id="institutionModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeInstitutionModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-[#1e1e1e] rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="institutionModalTitle" class="text-lg font-bold text-slate-900 dark:text-white">기관 추가</h3>
                    <button type="button" onclick="closeInstitutionModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form id="institutionForm">
                    <input type="hidden" id="institutionId" value="">
                    <div class="form-group mb-4">
                        <label for="institutionName" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">기관명</label>
                        <input type="text" id="institutionName" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="institutionType" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">유형 (선택)</label>
                        <input type="text" id="institutionType" placeholder="예: academy, kindergarten" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="institutionAddress" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">주소 (선택)</label>
                        <input type="text" id="institutionAddress" placeholder="주소를 입력하세요" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="institutionDescription" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">설명 (선택)</label>
                        <textarea id="institutionDescription" rows="3" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"></textarea>
                    </div>
                    <div class="form-group mb-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="institutionIsActive" checked class="rounded border-slate-300 dark:border-slate-600 text-primary focus:ring-primary"/>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">활성</span>
                        </label>
                    </div>
                    <div class="form-group mb-4">
                        <label for="institutionSortOrder" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">정렬 순서</label>
                        <input type="number" id="institutionSortOrder" min="0" value="0" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="flex gap-2 justify-end mt-6">
                        <button type="button" onclick="closeInstitutionModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">취소</button>
                        <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white">저장</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="contactModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeContactModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-[#1e1e1e] rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="contactModalTitle" class="text-lg font-bold text-slate-900 dark:text-white">담당자 추가</h3>
                    <button type="button" onclick="closeContactModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form id="contactForm">
                    <input type="hidden" id="contactId" value="">
                    <div class="form-group mb-4">
                        <label for="contactName" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">담당자명</label>
                        <input type="text" id="contactName" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="flex gap-2 justify-end mt-6">
                        <button type="button" onclick="closeContactModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">취소</button>
                        <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white">저장</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="passwordModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closePasswordModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-[#1e1e1e] rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">비밀번호 변경</h3>
                    <button type="button" onclick="closePasswordModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form id="changePasswordForm">
                    <input type="hidden" id="changePasswordUserId">
                    <div class="form-group mb-4">
                        <label for="currentPassword" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">현재 비밀번호</label>
                        <input type="password" id="currentPassword" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="newPasswordInput" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">새 비밀번호</label>
                        <input type="password" id="newPasswordInput" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="confirmPassword" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">새 비밀번호 확인</label>
                        <input type="password" id="confirmPassword" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="flex gap-2 justify-end mt-6">
                        <button type="button" onclick="closePasswordModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">취소</button>
                        <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white">변경</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="stockModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeStockModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-[#1e1e1e] rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 id="stockModalTitle" class="text-lg font-bold text-slate-900 dark:text-white">입고 처리</h3>
                    <button type="button" onclick="closeStockModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form id="stockForm">
                    <input type="hidden" id="stockBrochureId" value="">
                    <div class="form-group mb-4">
                        <p id="stockBrochureName" class="text-sm font-medium text-slate-700 dark:text-slate-300"></p>
                    </div>
                    <div class="form-group mb-4">
                        <label for="stockDate" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">입고 날짜</label>
                        <input type="date" id="stockDate" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="stockQuantity" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">입고 수량</label>
                        <input type="number" id="stockQuantity" min="1" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="stockContactName" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">담당자</label>
                        <input type="text" id="stockContactName" placeholder="입고 처리 담당자" value="관리자" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="stockSchoolName" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">기관명 / 입고처 (선택)</label>
                        <input type="text" id="stockSchoolName" placeholder="예: OO물류, 출판사 등" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="flex gap-2 justify-end mt-6">
                        <button type="button" onclick="closeStockModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">취소</button>
                        <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white">입고 처리</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="stockEditModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeStockEditModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-[#1e1e1e] rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">재고 수정</h3>
                    <button type="button" onclick="closeStockEditModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form id="stockEditForm">
                    <input type="hidden" id="stockEditBrochureId" value="">
                    <div class="form-group mb-4">
                        <label for="stockEditBrochureNameInput" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">브로셔명</label>
                        <input type="text" id="stockEditBrochureNameInput" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="stockEditQuantity" id="stockEditQuantityLabel" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">재고 수량</label>
                        <input type="number" id="stockEditQuantity" min="0" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="stockEditMemo" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">수정 사유 (메모)</label>
                        <textarea id="stockEditMemo" rows="3" placeholder="선택 입력" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white resize-y"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end mt-6">
                        <button type="button" onclick="closeStockEditModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">취소</button>
                        <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white">수정</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="transferToHqModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeTransferToHqModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-[#1e1e1e] rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">물류창고 → 본사 출고</h3>
                    <button type="button" onclick="closeTransferToHqModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form id="transferToHqForm">
                    <input type="hidden" id="transferToHqBrochureId" value="">
                    <div class="form-group mb-4">
                        <p id="transferToHqBrochureName" class="text-sm font-medium text-slate-700 dark:text-slate-300"></p>
                        <p id="transferToHqWarehouseStock" class="text-xs text-slate-500 dark:text-slate-400 mt-1"></p>
                    </div>
                    <div class="form-group mb-4">
                        <label for="transferToHqQuantity" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">출고 수량 (권)</label>
                        <input type="number" id="transferToHqQuantity" min="1" required class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="transferToHqDate" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">출고 일자</label>
                        <input type="date" id="transferToHqDate" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="form-group mb-4">
                        <label for="transferToHqMemo" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">메모 (선택)</label>
                        <input type="text" id="transferToHqMemo" placeholder="선택 입력" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white"/>
                    </div>
                    <div class="flex gap-2 justify-end mt-6">
                        <button type="button" onclick="closeTransferToHqModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">취소</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-medium text-white">이동</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function checkLogin() {
            const username = @json(auth()->user()?->preferredDisplayName() ?? 'Admin');
            const el = document.getElementById('sidebarUsername');
            if (el) el.textContent = username;
            return true;
        }

        var lastOutboundScroll = '';
        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(el => { el.classList.add('hidden'); });
            const section = document.getElementById('section-' + sectionId);
            if (section) section.classList.remove('hidden');
            var outboundToggle = document.getElementById('outboundNavToggle');
            var outboundSub = document.getElementById('outboundSubmenu');
            var chevrons = document.querySelectorAll('.outbound-chevron');
            if (sectionId === 'outbound') {
                document.querySelectorAll('.nav-link').forEach(a => {
                    a.classList.remove('bg-primary/10', 'text-primary');
                    a.classList.add('text-slate-600', 'dark:text-slate-400');
                });
                if (outboundToggle) {
                    outboundToggle.classList.add('bg-primary/10', 'text-primary');
                    outboundToggle.classList.remove('text-slate-600', 'dark:text-slate-400');
                }
                if (outboundSub) outboundSub.classList.remove('hidden');
                if (chevrons.length) chevrons.forEach(function(c) { c.style.transform = 'rotate(-180deg)'; });
                applyOutboundBlockVisibility();
                var warehouseSub = document.getElementById('warehouseSubmenu');
                var warehouseToggle = document.getElementById('warehouseNavToggle');
                var warehouseChevrons = document.querySelectorAll('.warehouse-chevron');
                if (warehouseSub) warehouseSub.classList.add('hidden');
                if (warehouseToggle) { warehouseToggle.classList.remove('bg-primary/10', 'text-primary'); warehouseToggle.classList.add('text-slate-600', 'dark:text-slate-400'); }
                if (warehouseChevrons.length) warehouseChevrons.forEach(function(c) { c.style.transform = ''; });
            } else if (sectionId === 'inventory' || sectionId === 'logistics') {
                var warehouseSub = document.getElementById('warehouseSubmenu');
                var warehouseToggle = document.getElementById('warehouseNavToggle');
                var warehouseChevrons = document.querySelectorAll('.warehouse-chevron');
                if (warehouseSub) warehouseSub.classList.remove('hidden');
                if (warehouseToggle) {
                    warehouseToggle.classList.add('bg-primary/10', 'text-primary');
                    warehouseToggle.classList.remove('text-slate-600', 'dark:text-slate-400');
                }
                if (warehouseChevrons.length) warehouseChevrons.forEach(function(c) { c.style.transform = 'rotate(-180deg)'; });
                if (outboundToggle) {
                    outboundToggle.classList.remove('bg-primary/10', 'text-primary');
                    outboundToggle.classList.add('text-slate-600', 'dark:text-slate-400');
                }
                if (outboundSub) outboundSub.classList.add('hidden');
                if (chevrons.length) chevrons.forEach(function(c) { c.style.transform = ''; });
                document.querySelectorAll('.nav-link').forEach(a => {
                    a.classList.remove('bg-primary/10', 'text-primary');
                    a.classList.add('text-slate-600', 'dark:text-slate-400');
                    if (a.getAttribute('data-nav') === sectionId) {
                        a.classList.add('bg-primary/10', 'text-primary');
                        a.classList.remove('text-slate-600', 'dark:text-slate-400');
                    }
                });
            } else {
                if (outboundToggle) {
                    outboundToggle.classList.remove('bg-primary/10', 'text-primary');
                    outboundToggle.classList.add('text-slate-600', 'dark:text-slate-400');
                }
                if (outboundSub) outboundSub.classList.add('hidden');
                if (chevrons.length) chevrons.forEach(function(c) { c.style.transform = ''; });
                var warehouseSub = document.getElementById('warehouseSubmenu');
                var warehouseToggle = document.getElementById('warehouseNavToggle');
                var warehouseChevrons = document.querySelectorAll('.warehouse-chevron');
                if (warehouseSub) warehouseSub.classList.add('hidden');
                if (warehouseToggle) { warehouseToggle.classList.remove('bg-primary/10', 'text-primary'); warehouseToggle.classList.add('text-slate-600', 'dark:text-slate-400'); }
                if (warehouseChevrons.length) warehouseChevrons.forEach(function(c) { c.style.transform = ''; });
                document.querySelectorAll('.nav-link').forEach(a => {
                    a.classList.remove('bg-primary/10', 'text-primary');
                    a.classList.add('text-slate-600', 'dark:text-slate-400');
                    if (a.getAttribute('data-nav') === sectionId) {
                        a.classList.add('bg-primary/10', 'text-primary');
                        a.classList.remove('text-slate-600', 'dark:text-slate-400');
                    }
                });
            }
            var pageTitleText = { dashboard: '대시보드 개요', inventory: '물류센터 브로셔 재고관리', inventory2: '본사 브로셔 재고관리', logistics: '운송장 입력', outbound: '입출고 내역', institutions: '기관 관리', settings: '설정' }[sectionId];
            if (sectionId === 'outbound' && lastOutboundScroll === 'warehouse') pageTitleText = '물류센터 입출고 내역';
            if (sectionId === 'outbound' && lastOutboundScroll === 'hq') pageTitleText = '본사 입출고 내역';
            const t = document.getElementById('pageTitle');
            if (t && pageTitleText) t.textContent = pageTitleText;
            if (window.innerWidth < 768) {
                var sb = document.getElementById('dashboardSidebar'), ov = document.getElementById('dashboardOverlay');
                if (sb) sb.classList.remove('open'); if (ov) ov.classList.add('hidden');
            }
            if (sectionId === 'logistics') loadSavedRequests();
            if (sectionId === 'outbound') loadOutboundHistory();
            if (sectionId === 'inventory2') loadBrochures();
            if (sectionId === 'institutions') loadInstitutions();
        }

        function applyOutboundBlockVisibility() {
            var blockWh = document.getElementById('outboundBlockWarehouse');
            var blockHq = document.getElementById('outboundBlockHq');
            if (!blockWh || !blockHq) return;
            if (lastOutboundScroll === 'warehouse') {
                blockWh.classList.remove('hidden');
                blockHq.classList.add('hidden');
            } else if (lastOutboundScroll === 'hq') {
                blockWh.classList.add('hidden');
                blockHq.classList.remove('hidden');
            } else {
                blockWh.classList.remove('hidden');
                blockHq.classList.remove('hidden');
            }
        }

        function scrollToOutboundBlock(which) {
            var id = (which === 'hq') ? 'outboundBlockHq' : 'outboundBlockWarehouse';
            var el = document.getElementById(id);
            if (el) setTimeout(function() { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);
        }

        (function() {
            var btn = document.getElementById('outboundNavToggle');
            if (!btn) return;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var sub = document.getElementById('outboundSubmenu');
                var section = document.getElementById('section-outbound');
                var chevrons = document.querySelectorAll('.outbound-chevron');
                if (section && !section.classList.contains('hidden')) {
                    if (sub) sub.classList.toggle('hidden');
                    if (chevrons.length) chevrons.forEach(function(c) { c.style.transform = (sub && sub.classList.contains('hidden')) ? '' : 'rotate(-180deg)'; });
                } else {
                    lastOutboundScroll = '';
                    showSection('outbound');
                }
            });
        })();

        (function() {
            var btn = document.getElementById('warehouseNavToggle');
            if (!btn) return;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var sub = document.getElementById('warehouseSubmenu');
                var sectionInv = document.getElementById('section-inventory');
                var sectionLog = document.getElementById('section-logistics');
                var isWarehouseVisible = sectionInv && !sectionInv.classList.contains('hidden') || sectionLog && !sectionLog.classList.contains('hidden');
                var chevrons = document.querySelectorAll('.warehouse-chevron');
                if (isWarehouseVisible) {
                    if (sub) sub.classList.toggle('hidden');
                    if (chevrons.length) chevrons.forEach(function(c) { c.style.transform = (sub && sub.classList.contains('hidden')) ? '' : 'rotate(-180deg)'; });
                } else {
                    showSection('inventory');
                }
            });
        })();

        document.querySelectorAll('[data-nav]').forEach(a => {
            a.addEventListener('click', function(e) {
                e.preventDefault();
                const sectionId = this.getAttribute('data-nav');
                const scrollTo = this.getAttribute('data-scroll');
                if (sectionId === 'outbound' && scrollTo) lastOutboundScroll = scrollTo;
                if (sectionId === 'reports') {
                    showSection('outbound');
                    return;
                }
                showSection(sectionId);
                if (scrollTo && sectionId === 'outbound') scrollToOutboundBlock(scrollTo);
            });
        });

        function showAlert(message, type) {
            type = type || 'success';
            const alertDiv = document.getElementById('alert');
            alertDiv.className = 'mx-8 mt-4 rounded-lg px-4 py-3 text-sm ' + (type === 'danger' ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200' : 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200');
            alertDiv.textContent = message;
            alertDiv.classList.remove('hidden');
            setTimeout(function() { alertDiv.classList.add('hidden'); }, 3000);
        }

        var pendingResetType = null;
        var resetModalMessages = {
            stock_history: '입출고 내역이 모두 삭제됩니다.',
            requests: '신청 내역과 운송장 정보가 모두 삭제됩니다.',
            brochure_stock: '모든 브로셔의 재고 수량(본사·물류)이 0으로 초기화됩니다.',
            full: '재고 수량, 입출고 내역, 신청 내역, 운송장 정보가 모두 삭제·초기화됩니다. 브로셔 종류·담당자·관리자 계정은 유지됩니다.'
        };
        function openResetConfirmModal(type) {
            pendingResetType = type;
            var msgEl = document.getElementById('resetModalMessage');
            var titleEl = document.getElementById('resetModalTitle');
            if (msgEl) msgEl.textContent = resetModalMessages[type] || '';
            if (titleEl) titleEl.textContent = (type === 'full' ? '전체 초기화' : '데이터 초기화');
            document.getElementById('resetModalStep1').classList.remove('hidden');
            document.getElementById('resetModalStep2').classList.add('hidden');
            var inputEl = document.getElementById('resetConfirmInput');
            if (inputEl) { inputEl.value = ''; inputEl.placeholder = '초기화'; }
            document.getElementById('resetConfirmModal').classList.remove('hidden');
        }
        function closeResetConfirmModal() {
            document.getElementById('resetConfirmModal').classList.add('hidden');
            pendingResetType = null;
        }
        function resetModalToStep2() {
            document.getElementById('resetModalStep1').classList.add('hidden');
            document.getElementById('resetModalStep2').classList.remove('hidden');
            var inputEl = document.getElementById('resetConfirmInput');
            if (inputEl) { inputEl.value = ''; inputEl.focus(); }
        }
        function resetModalToStep1() {
            document.getElementById('resetModalStep2').classList.add('hidden');
            document.getElementById('resetModalStep1').classList.remove('hidden');
        }
        async function executeReset() {
            var inputEl = document.getElementById('resetConfirmInput');
            var typed = inputEl ? inputEl.value.trim() : '';
            if (typed !== '초기화') {
                showAlert('입력란에 "초기화"를 정확히 입력해주세요.', 'danger');
                return;
            }
            if (!pendingResetType) return;
            var type = pendingResetType;
            var btn = document.getElementById('resetModalExecuteBtn');
            if (btn) { btn.disabled = true; btn.textContent = '처리 중...'; }
            try {
                var res = await AdminAPI.resetData(type);
                closeResetConfirmModal();
                showAlert(res.message || '초기화되었습니다.');
                if (type === 'full' || type === 'stock_history') loadOutboundHistory();
                if (type === 'full' || type === 'requests') loadSavedRequests();
                if (type === 'full' || type === 'brochure_stock') loadBrochures();
            } catch (err) {
                showAlert(err.message || '초기화 중 오류가 발생했습니다.', 'danger');
            }
            if (btn) { btn.disabled = false; btn.textContent = '실행'; }
        }

        window.ADMIN_BROCHURE_PLACEHOLDER = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Crect fill='%23cbd5e1' width='40' height='40'/%3E%3C/svg%3E";
        function brochureThumbTd(brochure) {
            var url = brochure.image_url && String(brochure.image_url).trim();
            var esc = function(s) { return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); };
            var img = url
                ? '<img src="' + esc(url) + '" alt="" class="w-10 h-10 object-cover rounded inline-block" onerror="this.src=window.ADMIN_BROCHURE_PLACEHOLDER">'
                : '<span class="w-10 h-10 rounded bg-slate-200 dark:bg-slate-700 inline-flex items-center justify-center text-slate-400 text-xs">-</span>';
            return '<td class="py-3 px-4 text-center">' + img + '</td>';
        }
        async function loadBrochures() {
            try {
                const brochures = await BrochureAPI.getAll();
                const tbody = document.getElementById('brochureTableBody');
                const tbody2 = document.getElementById('brochureTableBody2');
                if (tbody) tbody.innerHTML = '';
                if (tbody2) tbody2.innerHTML = '';
                const rowHtmlWarehouse = function(brochure) {
                    const warehouseStock = brochure.stock_warehouse ?? 0;
                    const warehouseClass = warehouseStock < 10 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-900 dark:text-white';
                    const warehouseStatus = stockStatusText(warehouseStock);
                    const lastStockQuantity = brochure.last_warehouse_stock_quantity ?? 0;
                    const lastStockDate = brochure.last_warehouse_stock_date || '-';
                    return brochureThumbTd(brochure) + '<td class="py-3 px-4">' + (brochure.name || '') + '</td><td class="py-3 px-4 text-right ' + warehouseClass + '">' + warehouseStock + '권</td><td class="py-3 px-4 text-center"><span class="font-medium ' + warehouseStatus.color + '">' + warehouseStatus.text + '</span></td><td class="py-3 px-4">' + (lastStockQuantity > 0 ? lastStockQuantity + '권 (' + lastStockDate + ')' : '-') + '</td><td class="py-3 px-4 text-center"><button type="button" onclick="openStockModal(\'' + brochure.id + '\', true)" class="px-2 py-1 rounded bg-green-600 text-white text-xs font-medium hover:bg-green-700 whitespace-nowrap">입고</button></td><td class="py-3 px-4"><div class="flex flex-wrap gap-1 justify-center">' + '<button type="button" onclick="openBrochureImageMenu(event, \'' + brochure.id + '\')" class="px-2 py-1 rounded bg-amber-600 text-white text-xs font-medium hover:bg-amber-700 whitespace-nowrap">이미지 관리</button>' + '<button type="button" onclick="openTransferToHqModal(\'' + brochure.id + '\')" class="px-2 py-1 rounded bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 whitespace-nowrap">→ 본사</button>' + '<button type="button" onclick="openStockEditModal(\'' + brochure.id + '\', true)" class="px-2 py-1 rounded bg-slate-500 text-white text-xs font-medium hover:bg-slate-600 whitespace-nowrap">재고 수정</button>' + '<button type="button" onclick="deleteBrochure(\'' + brochure.id + '\')" class="px-2 py-1 rounded bg-red-600 text-white text-xs font-medium hover:bg-red-700 whitespace-nowrap">삭제</button></div></td>';
                };
                const rowHtmlHq = function(brochure) {
                    const hqStock = brochure.stock ?? 0;
                    const hqClass = hqStock < 10 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-900 dark:text-white';
                    const hqStatus = stockStatusText(hqStock, 'hq');
                    const lastStockQuantity = brochure.last_stock_quantity ?? 0;
                    const lastStockDate = brochure.last_stock_date || '-';
                    return brochureThumbTd(brochure) + '<td class="py-3 px-4">' + (brochure.name || '') + '</td><td class="py-3 px-4 text-right ' + hqClass + '">' + hqStock + '권</td><td class="py-3 px-4 text-center"><span class="font-medium ' + hqStatus.color + '">' + hqStatus.text + '</span></td><td class="py-3 px-4">' + (lastStockQuantity > 0 ? lastStockQuantity + '권 (' + lastStockDate + ')' : '-') + '</td><td class="py-3 px-4 text-center"><button type="button" onclick="openStockModal(\'' + brochure.id + '\', false)" class="px-2 py-1 rounded bg-green-600 text-white text-xs font-medium hover:bg-green-700 whitespace-nowrap">입고</button></td><td class="py-3 px-4"><div class="flex flex-wrap gap-1 justify-center">' + '<button type="button" onclick="openBrochureImageMenu(event, \'' + brochure.id + '\')" class="px-2 py-1 rounded bg-amber-600 text-white text-xs font-medium hover:bg-amber-700 whitespace-nowrap">이미지 관리</button>' + '<button type="button" onclick="openStockEditModal(\'' + brochure.id + '\', false)" class="px-2 py-1 rounded bg-slate-500 text-white text-xs font-medium hover:bg-slate-600 whitespace-nowrap">재고 수정</button>' + '<button type="button" onclick="deleteBrochure(\'' + brochure.id + '\')" class="px-2 py-1 rounded bg-red-600 text-white text-xs font-medium hover:bg-red-700 whitespace-nowrap">삭제</button></div></td>';
                };
                brochures.forEach(brochure => {
                    const trClass = 'border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50';
                    if (tbody) {
                        const row = document.createElement('tr');
                        row.className = trClass;
                        row.innerHTML = rowHtmlWarehouse(brochure);
                        tbody.appendChild(row);
                    }
                    if (tbody2) {
                        const row2 = document.createElement('tr');
                        row2.className = trClass;
                        row2.innerHTML = rowHtmlHq(brochure);
                        tbody2.appendChild(row2);
                    }
                });
                updateStats(brochures);
            } catch (err) {
                console.error(err);
                showAlert('브로셔 목록을 불러오는 중 오류가 발생했습니다.', 'danger');
            }
        }

        async function loadContacts() {
            try {
                const contacts = await ContactAPI.getAll();
                const tbody = document.getElementById('contactTableBody');
                tbody.innerHTML = '';
                contacts.forEach(contact => {
                    const row = document.createElement('tr');
                    row.className = 'border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50';
                    row.innerHTML = '<td class="py-3 px-4">' + (contact.name || '') + '</td><td class="py-3 px-4 text-center"><button type="button" onclick="editContact(\'' + contact.id + '\')" class="px-2 py-1 rounded bg-primary text-white text-xs font-medium hover:bg-primary/90 mr-1">수정</button><button type="button" onclick="deleteContact(\'' + contact.id + '\')" class="px-2 py-1 rounded bg-red-600 text-white text-xs font-medium hover:bg-red-700">삭제</button></td>';
                    tbody.appendChild(row);
                });
            } catch (err) {
                console.error(err);
                showAlert('담당자 목록을 불러오는 중 오류가 발생했습니다.', 'danger');
            }
        }

        function formatDateTime(isoStr) {
            if (!isoStr) return '-';
            try {
                const d = new Date(isoStr);
                if (isNaN(d.getTime())) return isoStr;
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const h = String(d.getHours()).padStart(2, '0');
                const min = String(d.getMinutes()).padStart(2, '0');
                return y + '-' + m + '-' + day + ' ' + h + ':' + min;
            } catch (e) { return isoStr; }
        }

        var allStockHistoryList = [], outboundTypeFilter = '', outboundItemsPerPage = 20;
        var outboundWarehouseCurrentPage = 1, outboundHqCurrentPage = 1;

        function matchType(h) {
            if (!outboundTypeFilter) return true;
            if (outboundTypeFilter === '이동') return h.type === '이동' || h.type === '이전';
            return h.type === outboundTypeFilter;
        }
        function getOutboundWarehouseList() {
            return allStockHistoryList.filter(function (h) {
                if (h.location !== 'warehouse' && h.location != null) return false;
                return matchType(h);
            });
        }
        function getOutboundHqList() {
            return allStockHistoryList.filter(function (h) {
                if (h.location !== 'hq') return false;
                return matchType(h);
            });
        }
        function applyDateNameFilter(list) {
            var dateFrom = (document.getElementById('outboundFilterDateFrom') && document.getElementById('outboundFilterDateFrom').value) || '';
            var dateTo = (document.getElementById('outboundFilterDateTo') && document.getElementById('outboundFilterDateTo').value) || '';
            var brochureName = (document.getElementById('outboundFilterBrochureName') && document.getElementById('outboundFilterBrochureName').value || '').trim().toLowerCase();
            var schoolName = (document.getElementById('outboundFilterSchoolName') && document.getElementById('outboundFilterSchoolName').value || '').trim().toLowerCase();
            return list.filter(function (h) {
                if (dateFrom && (h.date || '') < dateFrom) return false;
                if (dateTo && (h.date || '') > dateTo) return false;
                if (brochureName && !(h.brochure_name || '').toLowerCase().includes(brochureName)) return false;
                if (schoolName && !(h.schoolname || '').toLowerCase().includes(schoolName)) return false;
                return true;
            });
        }
        function getFilteredOutboundWarehouse() { return applyDateNameFilter(getOutboundWarehouseList()); }
        function getFilteredOutboundHq() { return applyDateNameFilter(getOutboundHqList()); }
        function getFilteredOutbound() {
            return getFilteredOutboundWarehouse().concat(getFilteredOutboundHq()).sort(function (a, b) {
                var ta = (a.created_at || '').toString(), tb = (b.created_at || '').toString();
                return tb.localeCompare(ta);
            });
        }

        function applyOutboundTypeFilter() {
            var typeEl = document.getElementById('outboundFilterType');
            outboundTypeFilter = (typeEl && typeEl.value) ? typeEl.value : '';
            outboundWarehouseCurrentPage = 1;
            outboundHqCurrentPage = 1;
            renderOutboundWarehousePage();
            renderOutboundHqPage();
        }

        function renderOutboundRow(h, showTypeCol) {
            var typeDisplay = (h.type === '이전' ? '이동' : (h.type || '-'));
            var typeCell = showTypeCol ? '<td class="py-3 px-4 whitespace-nowrap">' + typeDisplay + '</td>' : '';
            var memoRaw = (h.memo && String(h.memo).trim()) ? String(h.memo).trim() : '';
            var memoEsc = memoRaw.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            var memoDisplay = memoEsc || '-';
            return typeCell + '<td class="py-3 px-4 whitespace-nowrap">' + (h.date || '-') + '</td><td class="py-3 px-4 whitespace-nowrap">' + (h.brochure_name || '-') + '</td><td class="py-3 px-4 text-right font-medium whitespace-nowrap">' + (h.quantity ?? '-') + '권</td><td class="py-3 px-4 whitespace-nowrap">' + (h.contact_name || '-') + '</td><td class="py-3 px-4 whitespace-nowrap">' + (h.schoolname || '-') + '</td><td class="py-3 px-4 text-right whitespace-nowrap">' + (h.before_stock ?? '-') + ' → ' + (h.after_stock ?? '-') + '</td><td class="py-3 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap">' + formatDateTime(h.created_at) + '</td><td class="py-3 px-4 text-slate-600 dark:text-slate-300 whitespace-nowrap" title="' + (memoRaw || '').replace(/"/g, '&quot;') + '">' + memoDisplay + '</td>';
        }

        function renderOutboundWarehousePage() {
            var tbody = document.getElementById('outboundWarehouseTableBody');
            var emptyEl = document.getElementById('outboundWarehouseEmpty');
            var paginationWrap = document.getElementById('outboundWarehousePaginationWrap');
            var paginationInfo = document.getElementById('outboundWarehousePaginationInfo');
            var pagination = document.getElementById('outboundWarehousePagination');
            var showTypeCol = outboundTypeFilter === '';
            document.querySelectorAll('.outbound-wh-th-type').forEach(function (th) { th.style.display = showTypeCol ? '' : 'none'; });
            if (!tbody) return;
            tbody.innerHTML = '';
            var filtered = getFilteredOutboundWarehouse();
            var totalItems = filtered.length;
            var totalPages = Math.max(1, Math.ceil(totalItems / outboundItemsPerPage));
            if (outboundWarehouseCurrentPage > totalPages) outboundWarehouseCurrentPage = totalPages;
            var startIndex = (outboundWarehouseCurrentPage - 1) * outboundItemsPerPage;
            var pageItems = filtered.slice(startIndex, startIndex + outboundItemsPerPage);
            if (totalItems === 0) {
                if (emptyEl) emptyEl.classList.remove('hidden');
                if (paginationWrap) paginationWrap.classList.add('hidden');
                return;
            }
            if (emptyEl) emptyEl.classList.add('hidden');
            pageItems.forEach(function (h) {
                var row = document.createElement('tr');
                row.className = 'border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50';
                row.innerHTML = renderOutboundRow(h, showTypeCol);
                tbody.appendChild(row);
            });
            if (paginationWrap) paginationWrap.classList.remove('hidden');
            if (paginationInfo) paginationInfo.textContent = totalPages <= 1 ? '총 ' + totalItems + '개' : '총 ' + totalItems + '개 중 ' + (startIndex + 1) + '-' + Math.min(startIndex + outboundItemsPerPage, totalItems) + '개 표시';
            if (pagination) pagination.innerHTML = '';
            if (totalPages <= 1) return;
            var prevLi = document.createElement('li');
            prevLi.innerHTML = '<button type="button" onclick="goToOutboundWarehousePage(' + (outboundWarehouseCurrentPage - 1) + ')" ' + (outboundWarehouseCurrentPage === 1 ? 'disabled' : '') + ' class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium ' + (outboundWarehouseCurrentPage === 1 ? 'text-slate-400 cursor-not-allowed' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">이전</button>';
            pagination.appendChild(prevLi);
            var startPage = Math.max(1, outboundWarehouseCurrentPage - 2), endPage = Math.min(totalPages, outboundWarehouseCurrentPage + 2);
            if (startPage > 1) {
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToOutboundWarehousePage(1)" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">1</button>';
                pagination.appendChild(li);
                if (startPage > 2) { var d = document.createElement('li'); d.innerHTML = '<span class="px-2 text-slate-400">...</span>'; pagination.appendChild(d); }
            }
            for (var p = startPage; p <= endPage; p++) {
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToOutboundWarehousePage(' + p + ')" class="px-3 py-1.5 rounded-lg border text-sm font-medium ' + (p === outboundWarehouseCurrentPage ? 'bg-primary border-primary text-white' : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">' + p + '</button>';
                pagination.appendChild(li);
            }
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) { var d = document.createElement('li'); d.innerHTML = '<span class="px-2 text-slate-400">...</span>'; pagination.appendChild(d); }
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToOutboundWarehousePage(' + totalPages + ')" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">' + totalPages + '</button>';
                pagination.appendChild(li);
            }
            var nextLi = document.createElement('li');
            nextLi.innerHTML = '<button type="button" onclick="goToOutboundWarehousePage(' + (outboundWarehouseCurrentPage + 1) + ')" ' + (outboundWarehouseCurrentPage === totalPages ? 'disabled' : '') + ' class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium ' + (outboundWarehouseCurrentPage === totalPages ? 'text-slate-400 cursor-not-allowed' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">다음</button>';
            pagination.appendChild(nextLi);
        }

        function renderOutboundHqPage() {
            var tbody = document.getElementById('outboundHqTableBody');
            var emptyEl = document.getElementById('outboundHqEmpty');
            var paginationWrap = document.getElementById('outboundHqPaginationWrap');
            var paginationInfo = document.getElementById('outboundHqPaginationInfo');
            var pagination = document.getElementById('outboundHqPagination');
            var showTypeCol = outboundTypeFilter === '';
            document.querySelectorAll('.outbound-hq-th-type').forEach(function (th) { th.style.display = showTypeCol ? '' : 'none'; });
            if (!tbody) return;
            tbody.innerHTML = '';
            var filtered = getFilteredOutboundHq();
            var totalItems = filtered.length;
            var totalPages = Math.max(1, Math.ceil(totalItems / outboundItemsPerPage));
            if (outboundHqCurrentPage > totalPages) outboundHqCurrentPage = totalPages;
            var startIndex = (outboundHqCurrentPage - 1) * outboundItemsPerPage;
            var pageItems = filtered.slice(startIndex, startIndex + outboundItemsPerPage);
            if (totalItems === 0) {
                if (emptyEl) emptyEl.classList.remove('hidden');
                if (paginationWrap) paginationWrap.classList.add('hidden');
                return;
            }
            if (emptyEl) emptyEl.classList.add('hidden');
            pageItems.forEach(function (h) {
                var row = document.createElement('tr');
                row.className = 'border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50';
                row.innerHTML = renderOutboundRow(h, showTypeCol);
                tbody.appendChild(row);
            });
            if (paginationWrap) paginationWrap.classList.remove('hidden');
            if (paginationInfo) paginationInfo.textContent = totalPages <= 1 ? '총 ' + totalItems + '개' : '총 ' + totalItems + '개 중 ' + (startIndex + 1) + '-' + Math.min(startIndex + outboundItemsPerPage, totalItems) + '개 표시';
            if (pagination) pagination.innerHTML = '';
            if (totalPages <= 1) return;
            var prevLi = document.createElement('li');
            prevLi.innerHTML = '<button type="button" onclick="goToOutboundHqPage(' + (outboundHqCurrentPage - 1) + ')" ' + (outboundHqCurrentPage === 1 ? 'disabled' : '') + ' class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium ' + (outboundHqCurrentPage === 1 ? 'text-slate-400 cursor-not-allowed' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">이전</button>';
            pagination.appendChild(prevLi);
            var startPage = Math.max(1, outboundHqCurrentPage - 2), endPage = Math.min(totalPages, outboundHqCurrentPage + 2);
            if (startPage > 1) {
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToOutboundHqPage(1)" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">1</button>';
                pagination.appendChild(li);
                if (startPage > 2) { var d = document.createElement('li'); d.innerHTML = '<span class="px-2 text-slate-400">...</span>'; pagination.appendChild(d); }
            }
            for (var p = startPage; p <= endPage; p++) {
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToOutboundHqPage(' + p + ')" class="px-3 py-1.5 rounded-lg border text-sm font-medium ' + (p === outboundHqCurrentPage ? 'bg-primary border-primary text-white' : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">' + p + '</button>';
                pagination.appendChild(li);
            }
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) { var d = document.createElement('li'); d.innerHTML = '<span class="px-2 text-slate-400">...</span>'; pagination.appendChild(d); }
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToOutboundHqPage(' + totalPages + ')" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">' + totalPages + '</button>';
                pagination.appendChild(li);
            }
            var nextLi = document.createElement('li');
            nextLi.innerHTML = '<button type="button" onclick="goToOutboundHqPage(' + (outboundHqCurrentPage + 1) + ')" ' + (outboundHqCurrentPage === totalPages ? 'disabled' : '') + ' class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium ' + (outboundHqCurrentPage === totalPages ? 'text-slate-400 cursor-not-allowed' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">다음</button>';
            pagination.appendChild(nextLi);
        }

        function applyOutboundFilter() {
            outboundWarehouseCurrentPage = 1;
            outboundHqCurrentPage = 1;
            renderOutboundWarehousePage();
            renderOutboundHqPage();
        }
        function resetOutboundFilter() {
            var from = document.getElementById('outboundFilterDateFrom');
            var to = document.getElementById('outboundFilterDateTo');
            var name = document.getElementById('outboundFilterBrochureName');
            var school = document.getElementById('outboundFilterSchoolName');
            var typeEl = document.getElementById('outboundFilterType');
            if (from) from.value = '';
            if (to) to.value = '';
            if (name) name.value = '';
            if (school) school.value = '';
            if (typeEl) typeEl.value = '';
            outboundTypeFilter = '';
            outboundWarehouseCurrentPage = 1;
            outboundHqCurrentPage = 1;
            renderOutboundWarehousePage();
            renderOutboundHqPage();
        }
        function goToOutboundWarehousePage(page) {
            var filtered = getFilteredOutboundWarehouse();
            var totalPages = Math.max(1, Math.ceil(filtered.length / outboundItemsPerPage));
            if (page < 1 || page > totalPages) return;
            outboundWarehouseCurrentPage = page;
            renderOutboundWarehousePage();
        }
        function goToOutboundHqPage(page) {
            var filtered = getFilteredOutboundHq();
            var totalPages = Math.max(1, Math.ceil(filtered.length / outboundItemsPerPage));
            if (page < 1 || page > totalPages) return;
            outboundHqCurrentPage = page;
            renderOutboundHqPage();
        }

        async function loadOutboundHistory() {
            var emptyWh = document.getElementById('outboundWarehouseEmpty');
            var emptyHq = document.getElementById('outboundHqEmpty');
            var paginationWh = document.getElementById('outboundWarehousePaginationWrap');
            var paginationHq = document.getElementById('outboundHqPaginationWrap');
            if (paginationWh) paginationWh.classList.add('hidden');
            if (paginationHq) paginationHq.classList.add('hidden');
            if (emptyWh) emptyWh.classList.add('hidden');
            if (emptyHq) emptyHq.classList.add('hidden');
            try {
                var history = await StockHistoryAPI.getAll();
                if (!Array.isArray(history)) history = [];
                allStockHistoryList = history;
                applyOutboundTypeFilter();
            } catch (err) {
                console.error('입출고 내역 로드 오류:', err);
                var msg = '입출고 내역을 불러오는 중 오류가 발생했습니다.';
                if (err && err.message) msg += ' (' + String(err.message).replace(/^Error:\s*/i, '') + ')';
                showAlert(msg, 'danger');
                allStockHistoryList = [];
                if (emptyWh) emptyWh.classList.remove('hidden');
                if (emptyHq) emptyHq.classList.remove('hidden');
            }
        }

        const LOW_STOCK_THRESHOLD = 900;   // 물류센터: 900권 이하 재고 부족
        const LOW_STOCK_THRESHOLD_HQ = 50;  // 본사: 50권 이하 재고 부족
        function stockStatusText(stock, type) {
            var threshold = (type === 'hq') ? LOW_STOCK_THRESHOLD_HQ : LOW_STOCK_THRESHOLD;
            if (stock <= threshold) return { text: '재고 부족', color: 'text-red-600 dark:text-red-400' };
            if (type === 'hq') return { text: '충분', color: 'text-green-600 dark:text-green-400' };
            if (stock < 1500) return { text: '보통', color: 'text-amber-500' };
            return { text: '충분', color: 'text-green-600 dark:text-green-400' };
        }
        function updateStats(brochures) {
            const totalBrochures = brochures.length;
            const lowStockCount = brochures.filter(b => (b.stock_warehouse ?? 0) <= LOW_STOCK_THRESHOLD).length;
            const statsGrid = document.getElementById('statsGrid');
            if (!statsGrid) return;
            statsGrid.innerHTML = '<div class="bg-white dark:bg-[#1e1e1e] rounded-xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm"><div class="flex items-center justify-between mb-2"><p class="text-slate-500 dark:text-slate-400 text-sm font-medium">총 브로셔 종류</p><span class="material-symbols-outlined text-primary bg-primary/10 p-1.5 rounded-lg" style="font-size: 20px;">library_books</span></div><p class="text-2xl font-bold text-slate-900 dark:text-white">' + totalBrochures + '</p></div>' +
                '<div class="bg-white dark:bg-[#1e1e1e] rounded-xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm"><div class="flex items-center justify-between mb-2"><p class="text-slate-500 dark:text-slate-400 text-sm font-medium">재고 부족 항목</p><span class="material-symbols-outlined text-orange-600 bg-orange-100 dark:bg-orange-900/30 p-1.5 rounded-lg" style="font-size: 20px;">warning</span></div><p class="text-2xl font-bold text-slate-900 dark:text-white">' + lowStockCount + '</p><p class="text-sm text-slate-500 dark:text-slate-400 mt-1">' + (lowStockCount > 0 ? '주의 필요' : '정상') + '</p></div>';
            updateStockTable();
            var lowList = document.getElementById('lowStockAlertsList');
            if (lowList) {
                var lowItems = brochures.filter(function(b) { return (b.stock_warehouse ?? 0) <= LOW_STOCK_THRESHOLD; }).sort(function(a, b) { return (a.stock_warehouse ?? 0) - (b.stock_warehouse ?? 0); });
                lowList.innerHTML = '';
                lowItems.slice(0, 10).forEach(function(b) {
                    var stock = b.stock_warehouse ?? 0;
                    var label = stock <= 0 ? '재고 없음' : (stock + '권 남음');
                    var colorClass = stock <= 0 ? 'text-red-500' : 'text-orange-500';
                    lowList.innerHTML += '<div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800/50 last:border-0"><div class="rounded-lg size-12 shrink-0 bg-primary/10 flex items-center justify-center"><span class="material-symbols-outlined text-primary" style="font-size: 24px;">menu_book</span></div><div class="flex-1 min-w-0"><p class="text-slate-900 dark:text-white text-sm font-medium truncate">' + (b.name || '') + '</p><p class="text-xs font-medium ' + colorClass + '">' + label + '</p></div><button type="button" onclick="openStockModal(\'' + b.id + '\', true); showSection(\'inventory\');" class="size-8 flex items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white transition-colors"><span class="material-symbols-outlined" style="font-size: 18px;">add_shopping_cart</span></button></div>';
                });
                if (lowItems.length === 0) lowList.innerHTML = '<p class="text-slate-500 dark:text-slate-400 text-sm">재고 부족 항목이 없습니다.</p>';
            }
        }

        async function updateStockTable() {
            try {
                const brochures = await BrochureAPI.getAll();
                const tbody = document.getElementById('stockTableBody');
                tbody.innerHTML = '';
                if (brochures.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="py-6 px-4 text-center text-slate-500 dark:text-slate-400">등록된 브로셔가 없습니다.</td></tr>';
                    return;
                }
                const sorted = brochures.slice().sort((a, b) => (a.id || 0) - (b.id || 0));
                sorted.forEach(b => {
                    const warehouseStock = b.stock_warehouse ?? 0;
                    const hqStock = b.stock || 0;
                    const warehouseStatus = stockStatusText(warehouseStock);
                    const hqStatus = stockStatusText(hqStock, 'hq');
                    const row = document.createElement('tr');
                    row.className = 'border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50';
                    row.innerHTML = '<td class="py-2 px-3">' + (b.name || '') + '</td><td class="py-2 px-3 text-right font-medium">' + warehouseStock + '권</td><td class="py-2 px-3 text-center"><span class="font-medium ' + warehouseStatus.color + '">' + warehouseStatus.text + '</span></td><td class="py-2 px-3 text-right font-medium">' + hqStock + '권</td><td class="py-2 px-3 text-center"><span class="font-medium ' + hqStatus.color + '">' + hqStatus.text + '</span></td>';
                    tbody.appendChild(row);
                });
            } catch (err) { console.error(err); }
        }

        async function openBrochureModal(id, addLocation) {
            var modal = document.getElementById('brochureModal');
            var form = document.getElementById('brochureForm');
            var title = document.getElementById('brochureModalTitle');
            var stockGroup = document.getElementById('stockGroup');
            if (id) {
                try {
                    var brochures = await BrochureAPI.getAll();
                    var brochure = brochures.find(function(b) { return b.id == id; });
                    if (brochure) {
                        document.getElementById('brochureId').value = brochure.id;
                        document.getElementById('brochureName').value = brochure.name;
                        document.getElementById('brochureImageUrl').value = brochure.image_url || '';
                        title.textContent = '브로셔 수정';
                        stockGroup.classList.add('hidden');
                    }
                } catch (e) { showAlert('브로셔 정보를 불러오는 중 오류가 발생했습니다.', 'danger'); return; }
                form.removeAttribute('data-brochure-add-location');
            } else {
                form.reset();
                document.getElementById('brochureId').value = '';
                title.textContent = '브로셔 추가';
                stockGroup.classList.remove('hidden');
                form.dataset.brochureAddLocation = addLocation === 'hq' ? 'hq' : 'warehouse';
            }
            clearBrochureImageFileInput();
            modal.classList.remove('hidden');
        }
        function clearBrochureImageFileInput() {
            var fileEl = document.getElementById('brochureImageFile');
            var previewEl = document.getElementById('brochureImagePreview');
            if (fileEl) { fileEl.value = ''; }
            if (previewEl) { previewEl.src = ''; previewEl.classList.add('hidden'); }
        }
        function closeBrochureModal() {
            document.getElementById('brochureModal').classList.add('hidden');
            document.getElementById('brochureForm').reset();
            clearBrochureImageFileInput();
        }
        document.getElementById('brochureImageFile').addEventListener('change', function() {
            var preview = document.getElementById('brochureImagePreview');
            var file = this.files && this.files[0];
            if (file) {
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('hidden');
            } else {
                preview.src = '';
                preview.classList.add('hidden');
            }
        });
        document.getElementById('brochureForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            var id = document.getElementById('brochureId').value;
            var name = document.getElementById('brochureName').value;
            var imageUrlEl = document.getElementById('brochureImageUrl');
            var imageUrl = (imageUrlEl.value || '').trim() || null;
            var imageFileEl = document.getElementById('brochureImageFile');
            var hasFile = imageFileEl.files && imageFileEl.files.length > 0;
            var initialStock = parseInt(document.getElementById('brochureStock').value, 10) || 0;
            try {
                if (id) {
                    if (hasFile) {
                        var uploadRes = await BrochureAPI.uploadImage(id, imageFileEl.files[0]);
                        imageUrl = uploadRes.image_url || imageUrl;
                        imageUrlEl.value = imageUrl || '';
                    }
                    await BrochureAPI.update(id, { name: name, image_url: imageUrl, stock: initialStock });
                    showAlert('브로셔가 수정되었습니다.');
                } else {
                    var addLocation = document.getElementById('brochureForm').dataset.brochureAddLocation || 'warehouse';
                    var createPayload = addLocation === 'hq'
                        ? { name: name, image_url: imageUrl, stock: initialStock, stock_warehouse: 0 }
                        : { name: name, image_url: imageUrl, stock: 0, stock_warehouse: initialStock };
                    var createRes = await BrochureAPI.create(createPayload);
                    if (hasFile && createRes && createRes.id) {
                        await BrochureAPI.uploadImage(createRes.id, imageFileEl.files[0]);
                    }
                    showAlert('브로셔가 추가되었습니다.');
                }
                await loadBrochures();
                closeBrochureModal();
            } catch (err) { showAlert('브로셔 저장 중 오류: ' + err.message, 'danger'); }
        });

        function openBrochureImageMenu(ev, brochureId) {
            ev.preventDefault();
            ev.stopPropagation();
            window._brochureImageMenuId = brochureId;
            var dd = document.getElementById('brochureImageMenuDropdown');
            if (!dd) return;
            var rect = ev.currentTarget.getBoundingClientRect();
            dd.style.left = rect.left + 'px';
            dd.style.top = (rect.bottom + 4) + 'px';
            dd.classList.remove('hidden');
        }
        function closeBrochureImageMenu() {
            var dd = document.getElementById('brochureImageMenuDropdown');
            if (dd) dd.classList.add('hidden');
        }
        document.addEventListener('click', function(e) {
            var dd = document.getElementById('brochureImageMenuDropdown');
            if (!dd || dd.classList.contains('hidden')) return;
            if (dd.contains(e.target)) return;
            if (e.target.closest && e.target.closest('[onclick*="openBrochureImageMenu"]')) return;
            closeBrochureImageMenu();
        });
        document.getElementById('brochureImageMenuUpload').addEventListener('click', function() {
            var id = window._brochureImageMenuId;
            closeBrochureImageMenu();
            if (id) triggerBrochureImageUpload(id);
        });
        document.getElementById('brochureImageMenuDelete').addEventListener('click', function() {
            var id = window._brochureImageMenuId;
            closeBrochureImageMenu();
            if (id) deleteBrochureImage(id);
        });
        function triggerBrochureImageUpload(brochureId) {
            window._brochureImageUploadId = brochureId;
            document.getElementById('brochureRowImageFile').value = '';
            document.getElementById('brochureRowImageFile').click();
        }
        document.getElementById('brochureRowImageFile').addEventListener('change', async function() {
            var id = window._brochureImageUploadId;
            var file = this.files && this.files[0];
            this.value = '';
            window._brochureImageUploadId = null;
            if (!id || !file) return;
            try {
                await BrochureAPI.uploadImage(id, file);
                showAlert('이미지가 업로드되었습니다.');
                await loadBrochures();
            } catch (err) { showAlert('이미지 업로드 실패: ' + err.message, 'danger'); }
        });
        async function deleteBrochureImage(brochureId) {
            if (!confirm('이 브로셔의 이미지를 삭제하시겠습니까?')) return;
            try {
                await BrochureAPI.deleteImage(brochureId);
                showAlert('이미지가 삭제되었습니다.');
                await loadBrochures();
            } catch (err) { showAlert('이미지 삭제 실패: ' + err.message, 'danger'); }
        }
        function editBrochure(id) { openBrochureModal(id); }
        async function deleteBrochure(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;
            try {
                await BrochureAPI.delete(id);
                await loadBrochures();
                showAlert('브로셔가 삭제되었습니다.');
            } catch (err) { showAlert('브로셔 삭제 중 오류: ' + err.message, 'danger'); }
        }

        var currentStockTarget = 'hq';
        async function openStockModal(id, isWarehouse) {
            try {
                var brochures = await BrochureAPI.getAll();
                var brochure = brochures.find(function(b) { return b.id == id; });
                if (brochure) {
                    document.getElementById('stockBrochureId').value = id;
                    currentStockTarget = (isWarehouse === true) ? 'warehouse' : 'hq';
                    var titleEl = document.getElementById('stockModalTitle');
                    if (titleEl) titleEl.textContent = (currentStockTarget === 'warehouse') ? '물류창고 입고 처리' : '본사 입고 처리';
                    document.getElementById('stockBrochureName').textContent = '브로셔명: ' + brochure.name;
                    document.getElementById('stockDate').value = new Date().toISOString().slice(0, 10);
                    document.getElementById('stockQuantity').value = 1;
                    var contactEl = document.getElementById('stockContactName');
                    if (contactEl) contactEl.value = '관리자';
                    var schoolEl = document.getElementById('stockSchoolName');
                    if (schoolEl) schoolEl.value = '';
                    document.getElementById('stockModal').classList.remove('hidden');
                }
            } catch (e) { showAlert('브로셔 정보를 불러오는 중 오류가 발생했습니다.', 'danger'); }
        }
        function closeStockModal() {
            document.getElementById('stockModal').classList.add('hidden');
            document.getElementById('stockForm').reset();
            currentStockTarget = 'hq';
            var titleEl = document.getElementById('stockModalTitle');
            if (titleEl) titleEl.textContent = '입고 처리';
        }
        document.getElementById('stockForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            var id = document.getElementById('stockBrochureId').value;
            var target = currentStockTarget;
            var quantity = parseInt(document.getElementById('stockQuantity').value, 10) || 0;
            var date = document.getElementById('stockDate').value;
            var contactName = (document.getElementById('stockContactName') && document.getElementById('stockContactName').value.trim()) || '관리자';
            var schoolName = (document.getElementById('stockSchoolName') && document.getElementById('stockSchoolName').value.trim()) || '';
            if (quantity <= 0 || !date) { showAlert('입고 수량과 날짜를 확인해주세요.', 'danger'); return; }
            try {
                var brochures = await BrochureAPI.getAll();
                var brochure = brochures.find(function(b) { return b.id == id; });
                if (!brochure) { showAlert('브로셔를 찾을 수 없습니다.', 'danger'); return; }
                if (target === 'warehouse') {
                    var beforeStock = brochure.stock_warehouse ?? 0;
                    await BrochureAPI.updateWarehouseStock(id, quantity, date);
                    await StockHistoryAPI.create({ type: '입고', location: 'warehouse', date: date, brochure_id: id, brochure_name: brochure.name, quantity: quantity, contact_name: contactName, schoolname: schoolName || undefined, before_stock: beforeStock, after_stock: beforeStock + quantity });
                    showAlert(quantity + '권이 물류창고에 입고되었습니다. (입고일: ' + date + ')');
                } else {
                    var beforeStock = brochure.stock || 0;
                    await BrochureAPI.updateStock(id, quantity, date);
                    await StockHistoryAPI.create({ type: '입고', location: 'hq', date: date, brochure_id: id, brochure_name: brochure.name, quantity: quantity, contact_name: contactName, schoolname: schoolName || undefined, before_stock: beforeStock, after_stock: beforeStock + quantity });
                    showAlert(quantity + '권이 본사에 입고되었습니다. (입고일: ' + date + ')');
                }
                await loadBrochures();
                closeStockModal();
            } catch (err) { showAlert('입고 처리 중 오류: ' + err.message, 'danger'); }
        });

        var currentStockEditTarget = 'hq';
        async function openStockEditModal(id, isWarehouse) {
            try {
                var brochures = await BrochureAPI.getAll();
                var brochure = brochures.find(function(b) { return b.id == id; });
                if (brochure) {
                    currentStockEditTarget = (isWarehouse === true) ? 'warehouse' : 'hq';
                    document.getElementById('stockEditBrochureId').value = id;
                    document.getElementById('stockEditBrochureNameInput').value = brochure.name || '';
                    var labelEl = document.getElementById('stockEditQuantityLabel');
                    if (labelEl) labelEl.textContent = currentStockEditTarget === 'warehouse' ? '물류창고 재고 수량' : '본사 재고 수량';
                    document.getElementById('stockEditQuantity').value = currentStockEditTarget === 'warehouse' ? (brochure.stock_warehouse ?? 0) : (brochure.stock || 0);
                    document.getElementById('stockEditMemo').value = '';
                    document.getElementById('stockEditModal').classList.remove('hidden');
                }
            } catch (e) { showAlert('브로셔 정보를 불러오는 중 오류가 발생했습니다.', 'danger'); }
        }
        function closeStockEditModal() {
            document.getElementById('stockEditModal').classList.add('hidden');
            document.getElementById('stockEditForm').reset();
            document.getElementById('stockEditMemo').value = '';
            currentStockEditTarget = 'hq';
        }
        document.getElementById('stockEditForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            var id = document.getElementById('stockEditBrochureId').value;
            var newName = (document.getElementById('stockEditBrochureNameInput') && document.getElementById('stockEditBrochureNameInput').value) ? document.getElementById('stockEditBrochureNameInput').value.trim() : '';
            var newQuantity = parseInt(document.getElementById('stockEditQuantity').value, 10) || 0;
            var memo = (document.getElementById('stockEditMemo') && document.getElementById('stockEditMemo').value) ? document.getElementById('stockEditMemo').value.trim() : '';
            if (!newName) { showAlert('브로셔명을 입력하세요.', 'danger'); return; }
            if (newQuantity < 0) { showAlert('재고 수량은 0 이상이어야 합니다.', 'danger'); return; }
            try {
                var brochures = await BrochureAPI.getAll();
                var brochure = brochures.find(function(b) { return b.id == id; });
                if (!brochure) { showAlert('브로셔를 찾을 수 없습니다.', 'danger'); return; }
                if (brochure.name !== newName) {
                    await BrochureAPI.update(id, { name: newName });
                }
                var dateStr = new Date().toISOString().slice(0, 10);
                if (currentStockEditTarget === 'warehouse') {
                    var warehouseCurrent = brochure.stock_warehouse ?? 0;
                    var diff = newQuantity - warehouseCurrent;
                    if (diff !== 0) {
                        if (warehouseCurrent + diff < 0) { showAlert('물류창고 재고는 0 미만이 될 수 없습니다.', 'danger'); return; }
                        await BrochureAPI.updateWarehouseStock(id, diff, dateStr, memo || '재고 수정');
                    }
                } else {
                    var diff = newQuantity - (brochure.stock || 0);
                    if (diff !== 0) await BrochureAPI.updateStock(id, diff, dateStr, memo);
                }
                await loadBrochures();
                showAlert('재고가 수정되었습니다.');
                closeStockEditModal();
            } catch (err) { showAlert('재고 수정 중 오류: ' + err.message, 'danger'); }
        });

        async function openTransferToHqModal(id) {
            try {
                var brochures = await BrochureAPI.getAll();
                var brochure = brochures.find(function(b) { return b.id == id; });
                if (brochure) {
                    var warehouseStock = brochure.stock_warehouse ?? 0;
                    document.getElementById('transferToHqBrochureId').value = id;
                    document.getElementById('transferToHqBrochureName').textContent = '브로셔: ' + (brochure.name || '');
                    document.getElementById('transferToHqWarehouseStock').textContent = '현재 물류창고 재고: ' + warehouseStock + '권';
                    document.getElementById('transferToHqQuantity').value = 1;
                    document.getElementById('transferToHqQuantity').max = warehouseStock;
                    document.getElementById('transferToHqQuantity').setAttribute('max', warehouseStock);
                    document.getElementById('transferToHqDate').value = new Date().toISOString().slice(0, 10);
                    document.getElementById('transferToHqMemo').value = '';
                    document.getElementById('transferToHqModal').classList.remove('hidden');
                }
            } catch (e) { showAlert('브로셔 정보를 불러오는 중 오류가 발생했습니다.', 'danger'); }
        }
        function closeTransferToHqModal() {
            document.getElementById('transferToHqModal').classList.add('hidden');
            document.getElementById('transferToHqForm').reset();
        }
        document.getElementById('transferToHqForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            var id = document.getElementById('transferToHqBrochureId').value;
            var quantity = parseInt(document.getElementById('transferToHqQuantity').value, 10) || 0;
            var date = document.getElementById('transferToHqDate').value || new Date().toISOString().slice(0, 10);
            var memo = (document.getElementById('transferToHqMemo') && document.getElementById('transferToHqMemo').value) ? document.getElementById('transferToHqMemo').value.trim() : '';
            if (quantity < 1) { showAlert('이동 수량은 1권 이상이어야 합니다.', 'danger'); return; }
            try {
                await BrochureAPI.transferToHq(id, quantity, date, memo);
                await loadBrochures();
                showAlert(quantity + '권이 물류창고에서 본사로 이동되었습니다.');
                closeTransferToHqModal();
            } catch (err) {
                var msg = err.message || (err.error || '이동 처리 중 오류가 발생했습니다.');
                showAlert(typeof msg === 'string' ? msg : (msg.error || msg), 'danger');
            }
        });

        async function openContactModal(id) {
            var modal = document.getElementById('contactModal');
            var form = document.getElementById('contactForm');
            var title = document.getElementById('contactModalTitle');
            if (id) {
                try {
                    var contacts = await ContactAPI.getAll();
                    var contact = contacts.find(function(c) { return c.id == id; });
                    if (contact) {
                        document.getElementById('contactId').value = contact.id;
                        document.getElementById('contactName').value = contact.name;
                        title.textContent = '담당자 수정';
                    }
                } catch (e) { showAlert('담당자 정보를 불러오는 중 오류가 발생했습니다.', 'danger'); return; }
            } else {
                form.reset();
                document.getElementById('contactId').value = '';
                title.textContent = '담당자 추가';
            }
            modal.classList.remove('hidden');
        }
        function closeContactModal() {
            document.getElementById('contactModal').classList.add('hidden');
            document.getElementById('contactForm').reset();
        }
        document.getElementById('contactForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            var id = document.getElementById('contactId').value;
            var name = document.getElementById('contactName').value;
            try {
                if (id) {
                    await ContactAPI.update(id, { name: name });
                    showAlert('담당자가 수정되었습니다.');
                } else {
                    await ContactAPI.create({ name: name });
                    showAlert('담당자가 추가되었습니다.');
                }
                await loadContacts();
                closeContactModal();
            } catch (err) { showAlert('담당자 저장 중 오류: ' + err.message, 'danger'); }
        });
        function editContact(id) { openContactModal(id); }
        async function deleteContact(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;
            try {
                await ContactAPI.delete(id);
                await loadContacts();
                showAlert('담당자가 삭제되었습니다.');
            } catch (err) { showAlert('담당자 삭제 중 오류: ' + err.message, 'danger'); }
        }

        function exportStockHistoryToExcel(rows, fileLabel) {
            if (typeof XLSX === 'undefined') { showAlert('엑셀 라이브러리를 불러오지 못했습니다. 페이지를 새로고침한 후 다시 시도해주세요.', 'danger'); return; }
            if (!Array.isArray(rows) || rows.length === 0) { showAlert('다운로드할 입출고 내역이 없습니다.', 'danger'); return; }
            var excelData = [['장소', '구분', '날짜', '브로셔명', '수량', '담당자', '기관명', '이전 재고', '이후 재고', '처리 시간', '메모']];
            rows.forEach(function(item) {
                var locationLabel = (item.location === 'warehouse' ? '물류센터' : (item.location === 'hq' ? '본사' : ''));
                var typeExcel = (item.type === '이전' ? '이동' : (item.type || ''));
                excelData.push([locationLabel, typeExcel, item.date || '', item.brochure_name || '', item.quantity || 0, item.contact_name || '', item.schoolname || '', item.before_stock || 0, item.after_stock || 0, item.created_at ? new Date(item.created_at).toLocaleString('ko-KR') : '', item.memo || '']);
            });
            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(excelData);
            ws['!cols'] = [{ wch: 8 }, { wch: 10 }, { wch: 12 }, { wch: 30 }, { wch: 10 }, { wch: 15 }, { wch: 20 }, { wch: 12 }, { wch: 12 }, { wch: 20 }, { wch: 30 }];
            XLSX.utils.book_append_sheet(wb, ws, '입출고 내역');
            var dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
            XLSX.writeFile(wb, '입출고내역' + (fileLabel ? '_' + fileLabel + '_' : '_') + dateStr + '.xlsx');
            showAlert('입출고 내역이 다운로드되었습니다.');
        }

        function downloadStockHistoryFiltered() {
            try {
                var filtered = getFilteredOutbound();
                exportStockHistoryToExcel(filtered, '현재목록');
            } catch (err) { showAlert('입출고 내역 다운로드 중 오류: ' + err.message, 'danger'); }
        }

        async function downloadStockHistoryAll() {
            if (typeof XLSX === 'undefined') { showAlert('엑셀 라이브러리를 불러오지 못했습니다. 페이지를 새로고침한 후 다시 시도해주세요.', 'danger'); return; }
            try {
                var history;
                if (allStockHistoryList && allStockHistoryList.length > 0) {
                    history = allStockHistoryList;
                } else {
                    history = await StockHistoryAPI.getAll();
                    if (!Array.isArray(history)) history = [];
                }
                exportStockHistoryToExcel(history, '');
            } catch (err) { showAlert('입출고 내역 다운로드 중 오류: ' + err.message, 'danger'); }
        }

        async function downloadStockHistory() {
            await downloadStockHistoryAll();
        }

        function logout() {
            sessionStorage.removeItem('admin_logged_in');
            sessionStorage.removeItem('admin_username');
            window.location.href = '{{ url("/") }}';
        }

        async function loadAdminUsers() {
            try {
                var users = await AdminAPI.getAllUsers();
                var tbody = document.getElementById('adminUsersTableBody');
                tbody.innerHTML = '';
                users.forEach(function(user) {
                    var row = document.createElement('tr');
                    row.className = 'border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50';
                    row.innerHTML = '<td class="py-3 px-4">' + user.id + '</td><td class="py-3 px-4">' + (user.username || '') + '</td><td class="py-3 px-4">' + (user.created_at ? new Date(user.created_at).toLocaleDateString('ko-KR') : '') + '</td><td class="py-3 px-4 text-center"><button type="button" onclick="openPasswordModal(' + user.id + ')" class="px-2 py-1 rounded bg-primary text-white text-xs font-medium hover:bg-primary/90 mr-1">비밀번호 변경</button><button type="button" onclick="deleteAdminUser(' + user.id + ')" ' + (users.length <= 1 ? 'disabled' : '') + ' class="px-2 py-1 rounded bg-red-600 text-white text-xs font-medium hover:bg-red-700">삭제</button></td>';
                    tbody.appendChild(row);
                });
            } catch (err) {
                console.error(err);
                showAlert('관리자 계정을 불러오는 중 오류가 발생했습니다.', 'danger');
            }
        }

        function getInstitutionFilters() {
            var searchEl = document.getElementById('institutionSearch');
            var filterEl = document.getElementById('institutionFilter');
            return {
                search: (searchEl && searchEl.value) ? searchEl.value.trim() : '',
                is_active: (filterEl && filterEl.value) !== undefined ? filterEl.value : ''
            };
        }
        function applyInstitutionFilters() {
            loadInstitutions(1);
        }
        async function bulkInstitutionSetActive(active) {
            var checkboxes = document.querySelectorAll('#institutionsTableBody .institution-row-cb:checked');
            var ids = [];
            checkboxes.forEach(function(cb) { ids.push(parseInt(cb.getAttribute('data-id'), 10)); });
            if (ids.length === 0) {
                showAlert('활성/비활성 변경할 기관을 선택해 주세요.', 'danger');
                return;
            }
            try {
                var res = await InstitutionAPI.bulkSetActive(ids, active);
                showAlert(res && res.message ? res.message : (active ? '선택 기관이 활성화되었습니다.' : '선택 기관이 비활성화되었습니다.'));
                var cur = window._institutionsPagination && window._institutionsPagination.current_page || 1;
                loadInstitutions(cur);
            } catch (err) {
                showAlert(err.message || '일괄 변경에 실패했습니다.', 'danger');
            }
        }
        async function loadInstitutions(page) {
            page = parseInt(page, 10) || 1;
            var filters = getInstitutionFilters();
            var loadingEl = document.getElementById('institutionsLoading');
            var tableEl = document.getElementById('institutionsTable');
            var tbodyEl = document.getElementById('institutionsTableBody');
            var emptyEl = document.getElementById('institutionsEmpty');
            var paginationEl = document.getElementById('institutionsPagination');
            var paginationInfoEl = document.getElementById('institutionsPaginationInfo');
            var paginationBtnsEl = document.getElementById('institutionsPaginationButtons');
            var selectAllEl = document.getElementById('institutionSelectAll');
            if (!loadingEl || !tableEl || !tbodyEl || !emptyEl) return;
            loadingEl.classList.remove('hidden');
            tableEl.classList.add('hidden');
            emptyEl.classList.add('hidden');
            if (paginationEl) paginationEl.classList.add('hidden');
            if (selectAllEl) selectAllEl.checked = false;
            try {
                var res = await InstitutionAPI.getList(page, filters);
                var list = res && res.data ? res.data : [];
                var currentPage = res && res.current_page != null ? res.current_page : 1;
                var lastPage = res && res.last_page != null ? res.last_page : 1;
                var total = res && res.total != null ? res.total : 0;
                var perPage = res && res.per_page != null ? res.per_page : 20;
                loadingEl.classList.add('hidden');
                tbodyEl.innerHTML = '';
                if (!list || list.length === 0) {
                    if (currentPage > 1 && lastPage > 1) {
                        loadInstitutions(currentPage - 1);
                        return;
                    }
                    emptyEl.classList.remove('hidden');
                    if (paginationEl) paginationEl.classList.add('hidden');
                    return;
                }
                tableEl.classList.remove('hidden');
                window._institutionsList = list;
                window._institutionsPagination = { current_page: currentPage, last_page: lastPage, total: total, per_page: perPage };
                list.forEach(function(inst) {
                    var tr = document.createElement('tr');
                    tr.className = 'border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50';
                    var typeText = (inst.type || '').trim() || '-';
                    var descText = (inst.description || '').trim() ? (inst.description.length > 50 ? inst.description.slice(0, 50) + '…' : inst.description) : '-';
                    var addrText = (inst.address || '').trim() ? (inst.address.length > 40 ? inst.address.slice(0, 40) + '…' : inst.address) : '-';
                    var statusText = inst.is_active ? '활성' : '비활성';
                    var statusClass = inst.is_active ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400';
                    var cb = '<td class="w-10 py-3 px-2 text-center"><input type="checkbox" class="institution-row-cb rounded border-slate-300 dark:border-slate-600 text-primary focus:ring-primary" data-id="' + inst.id + '"></td>';
                    tr.innerHTML = cb + '<td class="py-3 px-4 font-medium text-slate-900 dark:text-white">' + (inst.name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</td><td class="py-3 px-4 text-slate-600 dark:text-slate-300">' + (typeText === '-' ? '-' : typeText.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</td><td class="py-3 px-4 text-slate-600 dark:text-slate-300">' + (descText === '-' ? '-' : descText.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</td><td class="py-3 px-4 text-slate-600 dark:text-slate-300 max-w-[200px] truncate" title="' + (inst.address || '').replace(/"/g, '&quot;') + '">' + (addrText === '-' ? '-' : addrText.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</td><td class="py-3 px-4 text-center"><span class="' + statusClass + '">' + statusText + '</span></td><td class="py-3 px-4 text-right text-slate-600 dark:text-slate-400">' + (inst.sort_order != null ? inst.sort_order : '') + '</td><td class="py-3 px-4 text-center"><button type="button" onclick="editInstitution(' + inst.id + ')" class="px-2 py-1 rounded bg-primary text-white text-xs font-medium hover:bg-primary/90 mr-1">수정</button><button type="button" onclick="deleteInstitution(' + inst.id + ')" class="px-2 py-1 rounded bg-red-600 text-white text-xs font-medium hover:bg-red-700">삭제</button></td>';
                    tbodyEl.appendChild(tr);
                });
                if (selectAllEl) {
                    selectAllEl.onclick = function() {
                        document.querySelectorAll('#institutionsTableBody .institution-row-cb').forEach(function(cb) { cb.checked = selectAllEl.checked; });
                    };
                }
                if (paginationEl && paginationInfoEl && paginationBtnsEl && lastPage > 1) {
                    var from = (currentPage - 1) * perPage + 1;
                    var to = Math.min(currentPage * perPage, total);
                    paginationInfoEl.textContent = '총 ' + total + '개 (현재 ' + from + '–' + to + ' / 페이지당 ' + perPage + '개)';
                    var html = '';
                    if (currentPage > 1) html += '<button type="button" onclick="loadInstitutions(' + (currentPage - 1) + ')" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-medium">이전</button>';
                    var start = Math.max(1, currentPage - 2);
                    var end = Math.min(lastPage, currentPage + 2);
                    for (var p = start; p <= end; p++) {
                        if (p === currentPage) html += '<button type="button" class="px-3 py-1.5 rounded-lg bg-primary text-white text-sm font-medium">' + p + '</button>';
                        else html += '<button type="button" onclick="loadInstitutions(' + p + ')" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-medium">' + p + '</button>';
                    }
                    if (currentPage < lastPage) html += '<button type="button" onclick="loadInstitutions(' + (currentPage + 1) + ')" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-medium">다음</button>';
                    paginationBtnsEl.innerHTML = html;
                    paginationEl.classList.remove('hidden');
                } else if (total > 0 && paginationEl && paginationInfoEl) {
                    paginationInfoEl.textContent = '총 ' + total + '개 (페이지당 ' + perPage + '개)';
                    paginationEl.classList.remove('hidden');
                }
            } catch (err) {
                loadingEl.classList.add('hidden');
                console.error(err);
                showAlert('기관 목록을 불러오는 중 오류가 발생했습니다.', 'danger');
            }
        }

        function openInstitutionModal(inst) {
            var modal = document.getElementById('institutionModal');
            var title = document.getElementById('institutionModalTitle');
            document.getElementById('institutionId').value = inst ? inst.id : '';
            document.getElementById('institutionName').value = inst ? (inst.name || '') : '';
            document.getElementById('institutionType').value = inst ? (inst.type || '') : '';
            document.getElementById('institutionAddress').value = inst ? (inst.address || '') : '';
            document.getElementById('institutionDescription').value = inst ? (inst.description || '') : '';
            document.getElementById('institutionIsActive').checked = inst ? !!inst.is_active : true;
            document.getElementById('institutionSortOrder').value = inst != null && inst.sort_order != null ? inst.sort_order : 0;
            if (title) title.textContent = inst ? '기관 수정' : '기관 추가';
            if (modal) modal.classList.remove('hidden');
        }
        function closeInstitutionModal() {
            document.getElementById('institutionModal').classList.add('hidden');
        }
        async function editInstitution(id) {
            var list = window._institutionsList || [];
            var inst = list.find(function(i) { return i.id == id; });
            if (inst) { openInstitutionModal(inst); return; }
            try {
                inst = await InstitutionAPI.getOne(id);
                if (inst) openInstitutionModal(inst);
            } catch (e) { showAlert('기관 정보를 불러오는 중 오류가 발생했습니다.', 'danger'); }
        }
        document.getElementById('institutionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            var idEl = document.getElementById('institutionId');
            var id = (idEl && idEl.value) ? idEl.value.trim() : '';
            var payload = {
                name: (document.getElementById('institutionName').value || '').trim(),
                type: (document.getElementById('institutionType').value || '').trim() || null,
                address: (document.getElementById('institutionAddress').value || '').trim() || null,
                description: (document.getElementById('institutionDescription').value || '').trim() || null,
                is_active: document.getElementById('institutionIsActive').checked,
                sort_order: parseInt(document.getElementById('institutionSortOrder').value, 10) || 0
            };
            try {
                if (id) {
                    await InstitutionAPI.update(id, payload);
                    showAlert('기관이 수정되었습니다.');
                } else {
                    await InstitutionAPI.create(payload);
                    showAlert('기관이 추가되었습니다.');
                }
                closeInstitutionModal();
                loadInstitutions(window._institutionsPagination && window._institutionsPagination.current_page || 1);
            } catch (err) {
                showAlert(err.message || '저장에 실패했습니다.', 'danger');
            }
        });
        async function deleteInstitution(id) {
            if (!confirm('이 기관을 삭제하시겠습니까?')) return;
            try {
                await InstitutionAPI.delete(id);
                showAlert('기관이 삭제되었습니다.');
                loadInstitutions(window._institutionsPagination && window._institutionsPagination.current_page || 1);
            } catch (err) {
                showAlert(err.message || '삭제에 실패했습니다.', 'danger');
            }
        }

        document.getElementById('addAdminForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            var username = document.getElementById('newUsername').value;
            var password = document.getElementById('newPassword').value;
            try {
                var result = await AdminAPI.createUser(username, password);
                if (result.success) {
                    showAlert('계정이 추가되었습니다.');
                    document.getElementById('addAdminForm').reset();
                    await loadAdminUsers();
                } else { showAlert(result.error || '계정 추가 중 오류가 발생했습니다.', 'danger'); }
            } catch (err) { showAlert('계정 추가 중 오류: ' + err.message, 'danger'); }
        });
        function openPasswordModal(userId) {
            document.getElementById('changePasswordUserId').value = userId;
            document.getElementById('passwordModal').classList.remove('hidden');
        }
        function closePasswordModal() {
            document.getElementById('passwordModal').classList.add('hidden');
            document.getElementById('changePasswordForm').reset();
        }
        document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            var userId = document.getElementById('changePasswordUserId').value;
            var currentPassword = document.getElementById('currentPassword').value;
            var newPassword = document.getElementById('newPasswordInput').value;
            var confirmPassword = document.getElementById('confirmPassword').value;
            if (newPassword !== confirmPassword) { showAlert('새 비밀번호가 일치하지 않습니다.', 'danger'); return; }
            try {
                var result = await AdminAPI.changePassword(userId, currentPassword, newPassword);
                if (result.success) { showAlert('비밀번호가 변경되었습니다.'); closePasswordModal(); }
                else { showAlert(result.error || '비밀번호 변경 중 오류가 발생했습니다.', 'danger'); }
            } catch (err) { showAlert('비밀번호 변경 중 오류: ' + err.message, 'danger'); }
        });
        async function deleteAdminUser(userId) {
            if (!confirm('정말 이 계정을 삭제하시겠습니까?')) return;
            try {
                var result = await AdminAPI.deleteUser(userId);
                if (result.success) { showAlert('계정이 삭제되었습니다.'); await loadAdminUsers(); }
                else { showAlert(result.error || '계정 삭제 중 오류가 발생했습니다.', 'danger'); }
            } catch (err) { showAlert('계정 삭제 중 오류: ' + err.message, 'danger'); }
        }

        // ----- 운송장 입력 (logistics) 섹션 -----
        var logisticsRowCount = 0, logisticsCurrentPage = 1, logisticsItemsPerPage = 10, allPendingRequests = [];
        async function addRowFromData(request, requestIndex, itemIndex, requestId) {
            logisticsRowCount++;
            var rowsContainer = document.getElementById('rowsContainer');
            if (!rowsContainer) return;
            var rowDiv = document.createElement('div');
            rowDiv.className = 'border-2 border-primary/30 rounded-xl p-4 mb-4 bg-white dark:bg-slate-800/50 dark:border-slate-700';
            rowDiv.id = 'row-' + logisticsRowCount;
            rowDiv.dataset.requestIndex = requestIndex;
            rowDiv.dataset.itemIndex = itemIndex;
            rowDiv.dataset.requestId = requestId;
            rowDiv.innerHTML =
                '<div class="flex flex-wrap gap-4 items-end mb-4 pb-3 border-b border-slate-200 dark:border-slate-700">' +
                '<div class="min-w-[120px]"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">날짜</label><input type="date" id="logistics-date-' + logisticsRowCount + '" value="' + (request.date || '') + '" disabled class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 cursor-not-allowed text-sm"></div>' +
                '<div class="flex-1 min-w-[200px]"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">기관명</label><input type="text" id="logistics-schoolname-' + logisticsRowCount + '" value="' + (request.schoolname || '') + '" disabled class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white font-medium cursor-not-allowed text-sm"></div>' +
                '<div class="min-w-[120px]"><span class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">담당자</span><span class="block text-sm text-slate-900 dark:text-white">' + (request.contactName || request.contact || '-') + '</span></div></div>' +
                '<div class="flex flex-wrap gap-4 mb-4"><div class="flex-1 min-w-[200px]"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">주소</label><input type="text" id="logistics-address-' + logisticsRowCount + '" value="' + (request.address || '') + '" disabled class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 cursor-not-allowed text-sm"></div>' +
                '<div class="min-w-[140px]"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">전화번호</label><input type="tel" id="logistics-phone-' + logisticsRowCount + '" value="' + (request.phone || '') + '" disabled class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 cursor-not-allowed text-sm"></div></div>' +
                '<div class="mb-4"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">브로셔 신청 내역</label><div id="logistics-brochure-list-' + logisticsRowCount + '" class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 text-sm text-slate-900 dark:text-white">' + (request.brochures && request.brochures.length > 0 ? request.brochures.map(function(b) { return (b.brochureName || '') + ' - ' + (b.quantity || 0) + '권'; }).join('<br>') : '브로셔 정보 없음') + '</div></div>' +
                '<div class="mb-4"><label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">운송장 번호</label><div class="flex flex-wrap gap-2 items-end" id="invoice-container-' + logisticsRowCount + '"></div><button type="button" onclick="addLogisticsInvoiceField(' + logisticsRowCount + ')" class="mt-2 flex items-center gap-1 px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium">+ 운송장 번호 추가</button></div>' +
                '<div class="flex justify-end"><button type="button" onclick="saveSingleInvoice(' + logisticsRowCount + ',' + requestIndex + ',' + itemIndex + ')" class="px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium text-white">저장</button></div>';
            rowsContainer.appendChild(rowDiv);
            await new Promise(function(r) { setTimeout(r, 0); });
            var rc = logisticsRowCount;
            if (request.invoices && request.invoices.length > 0) {
                request.invoices.forEach(function(inv, idx) {
                    addLogisticsInvoiceField(rc, idx === 0);
                    var inp = document.querySelector('#invoice-container-' + rc + ' input[type="text"]:last-of-type');
                    if (inp) inp.value = inv;
                });
            } else { addLogisticsInvoiceField(rc, true); }
        }
        function addLogisticsInvoiceField(rowId, isDefault) {
            var container = document.getElementById('invoice-container-' + rowId);
            if (!container) return;
            var invoiceCount = container.querySelectorAll('.invoice-group').length;
            var invoiceId = 'invoice-' + rowId + '-' + (invoiceCount + 1);
            var invoiceGroup = document.createElement('div');
            invoiceGroup.className = 'invoice-group flex gap-2 items-end';
            invoiceGroup.id = 'invoice-group-' + invoiceId;
            var input = document.createElement('input');
            input.type = 'text';
            input.id = invoiceId;
            input.placeholder = '송장번호를 입력하세요';
            input.className = 'w-48 min-w-[120px] px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-medium';
            invoiceGroup.appendChild(input);
            if (!isDefault) {
                var deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'px-2 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium';
                deleteBtn.textContent = '삭제';
                deleteBtn.onclick = function() { invoiceGroup.remove(); };
                invoiceGroup.appendChild(deleteBtn);
            }
            container.appendChild(invoiceGroup);
        }
        function collectLogisticsInvoiceFields(rowId) {
            var container = document.getElementById('invoice-container-' + rowId);
            if (!container) return [];
            var invoices = [];
            container.querySelectorAll('input[type="text"]').forEach(function(input) { if (input.value.trim()) invoices.push(input.value.trim()); });
            return invoices;
        }
        async function saveSingleInvoice(rowId, requestIndex, itemIndex) {
            var invoices = collectLogisticsInvoiceFields(rowId).filter(function(inv) { return inv && inv.trim() !== ''; });
            if (invoices.length === 0) { showAlert('운송장 번호를 입력해주세요.', 'danger'); return; }
            try {
                var row = document.getElementById('row-' + rowId);
                var requestId = row ? row.dataset.requestId : null;
                if (!requestId) { showAlert('요청 ID를 찾을 수 없습니다.', 'danger'); return; }
                await RequestAPI.addInvoices(requestId, invoices);
                showAlert('운송장 번호가 저장되었습니다!', 'success');
                setTimeout(function() { loadSavedRequests(); }, 500);
            } catch (err) { showAlert('운송장 번호 저장 중 오류: ' + (err.message || ''), 'danger'); }
        }
        async function loadSavedRequests() {
            try {
                var allRequests = await RequestAPI.getAll();
                var rowsContainer = document.getElementById('rowsContainer');
                if (!rowsContainer) return;
                rowsContainer.innerHTML = '';
                if (allRequests.length === 0) {
                    rowsContainer.innerHTML = '<p class="text-center text-slate-500 dark:text-slate-400 py-8">저장된 신청 내역이 없습니다.</p>';
                    var pag = document.getElementById('pagination');
                    var pagInfo = document.getElementById('paginationInfo');
                    if (pag) pag.innerHTML = '';
                    if (pagInfo) pagInfo.textContent = '';
                    return;
                }
                allPendingRequests = [];
                allRequests.forEach(function(req) {
                    if (!req.invoices || req.invoices.length === 0 || req.invoices.every(function(inv) { return !inv || (typeof inv === 'string' && inv.trim() === ''); })) {
                        var request = { date: req.date, schoolname: req.schoolname, address: req.address, phone: req.phone, contact: req.contact_id, contactName: req.contact_name, brochures: (req.items || []).map(function(item) { return { brochure: item.brochure_id, brochureName: item.brochure_name, quantity: item.quantity }; }), invoices: req.invoices || [] };
                        allPendingRequests.push({ request: request, requestId: req.id });
                    }
                });
                if (allPendingRequests.length === 0) {
                    rowsContainer.innerHTML = '<p class="text-center text-slate-500 dark:text-slate-400 py-8">운송장 번호 입력이 필요한 신청 내역이 없습니다.</p>';
                    var pag = document.getElementById('pagination');
                    var pagInfo = document.getElementById('paginationInfo');
                    if (pag) pag.innerHTML = '';
                    if (pagInfo) pagInfo.textContent = '';
                    return;
                }
                logisticsCurrentPage = 1;
                displayPagedRequests();
            } catch (err) { console.error(err); showAlert('신청 내역을 불러오는 중 오류가 발생했습니다.', 'danger'); }
        }
        async function displayPagedRequests() {
            var rowsContainer = document.getElementById('rowsContainer');
            if (!rowsContainer) return;
            rowsContainer.innerHTML = '';
            var totalItems = allPendingRequests.length;
            var totalPages = Math.ceil(totalItems / logisticsItemsPerPage);
            var startIndex = (logisticsCurrentPage - 1) * logisticsItemsPerPage;
            var pageItems = allPendingRequests.slice(startIndex, startIndex + logisticsItemsPerPage);
            for (var i = 0; i < pageItems.length; i++) {
                var item = pageItems[i];
                await addRowFromData(item.request, startIndex + i, 0, item.requestId);
            }
            var pagination = document.getElementById('pagination');
            var paginationInfo = document.getElementById('paginationInfo');
            if (pagination) pagination.innerHTML = '';
            if (paginationInfo) paginationInfo.textContent = totalPages <= 1 ? '총 ' + totalItems + '개' : '총 ' + totalItems + '개 중 ' + (startIndex + 1) + '-' + Math.min(startIndex + logisticsItemsPerPage, totalItems) + '개 표시';
            if (totalPages <= 1) return;
            var prevLi = document.createElement('li');
            prevLi.innerHTML = '<button type="button" onclick="goToLogisticsPage(' + (logisticsCurrentPage - 1) + ')" ' + (logisticsCurrentPage === 1 ? 'disabled' : '') + ' class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium ' + (logisticsCurrentPage === 1 ? 'text-slate-400 cursor-not-allowed' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">이전</button>';
            pagination.appendChild(prevLi);
            var startPage = Math.max(1, logisticsCurrentPage - 2), endPage = Math.min(totalPages, logisticsCurrentPage + 2);
            if (startPage > 1) {
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToLogisticsPage(1)" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">1</button>';
                pagination.appendChild(li);
                if (startPage > 2) { var d = document.createElement('li'); d.innerHTML = '<span class="px-2 text-slate-400">...</span>'; pagination.appendChild(d); }
            }
            for (var p = startPage; p <= endPage; p++) {
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToLogisticsPage(' + p + ')" class="px-3 py-1.5 rounded-lg border text-sm font-medium ' + (p === logisticsCurrentPage ? 'bg-primary border-primary text-white' : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">' + p + '</button>';
                pagination.appendChild(li);
            }
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) { var d = document.createElement('li'); d.innerHTML = '<span class="px-2 text-slate-400">...</span>'; pagination.appendChild(d); }
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" onclick="goToLogisticsPage(' + totalPages + ')" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">' + totalPages + '</button>';
                pagination.appendChild(li);
            }
            var nextLi = document.createElement('li');
            nextLi.innerHTML = '<button type="button" onclick="goToLogisticsPage(' + (logisticsCurrentPage + 1) + ')" ' + (logisticsCurrentPage === totalPages ? 'disabled' : '') + ' class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium ' + (logisticsCurrentPage === totalPages ? 'text-slate-400 cursor-not-allowed' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800') + '">다음</button>';
            pagination.appendChild(nextLi);
        }
        async function goToLogisticsPage(page) {
            var totalPages = Math.ceil(allPendingRequests.length / logisticsItemsPerPage);
            if (page < 1 || page > totalPages) return;
            logisticsCurrentPage = page;
            await displayPagedRequests();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        function downloadLogisticsExcel() {
            if (typeof XLSX === 'undefined') { showAlert('엑셀 라이브러리를 불러오지 못했습니다. 페이지를 새로고침한 후 다시 시도해주세요.', 'danger'); return; }
            if (allPendingRequests.length === 0) { showAlert('다운로드할 신청 내역이 없습니다.', 'danger'); return; }
            var excelData = [['배송메세지1', '받는분성명', '받는분전화번호', '받는분주소(전체, 분할)', '내품코드', '내품명', '내품수량', '박스타입', '운임구분', '운송장번호', '']];
            allPendingRequests.forEach(function(item) {
                var request = item.request;
                if (request.brochures && request.brochures.length > 0) {
                    request.brochures.forEach(function(brochure) {
                        excelData.push(['브로셔', request.schoolname || '', request.phone || '', request.address || '', 'Brochure', brochure.brochureName || '', brochure.quantity || '', '', '', request.invoices && request.invoices.length > 0 ? request.invoices.join(', ') : '', '']);
                    });
                } else {
                    excelData.push(['브로셔', request.schoolname || '', request.phone || '', request.address || '', 'Brochure', '', '', '', '', request.invoices && request.invoices.length > 0 ? request.invoices.join(', ') : '', '']);
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
        var logisticsFormEl = document.getElementById('logisticsForm');
        if (logisticsFormEl) logisticsFormEl.addEventListener('submit', function(e) { e.preventDefault(); showAlert('각 건마다 개별 저장 버튼을 사용해주세요.', 'danger'); });

        (function mobileMenu() {
            var btn = document.getElementById('dashboardMenuBtn'), sb = document.getElementById('dashboardSidebar'), ov = document.getElementById('dashboardOverlay');
            if (btn && sb && ov) {
                btn.addEventListener('click', function() { sb.classList.toggle('open'); ov.classList.toggle('hidden', !sb.classList.contains('open')); });
                ov.addEventListener('click', function() { sb.classList.remove('open'); ov.classList.add('hidden'); });
                sb.querySelectorAll('.nav-link').forEach(function(a) { a.addEventListener('click', function() { sb.classList.remove('open'); ov.classList.add('hidden'); }); });
            }
        })();
        window.addEventListener('DOMContentLoaded', async function() {
            if (!checkLogin()) return;
            await loadBrochures();
            await loadContacts();
            await loadAdminUsers();
            var params = new URLSearchParams(window.location.search);
            if (params.get('section') === 'logistics') showSection('logistics');
        });
    </script>
</body>
</html>
