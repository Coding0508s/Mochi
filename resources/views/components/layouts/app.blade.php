<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'GrapeSEED MOCHI' }}</title>
    @include('partials.favicon-links')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="mochi-app-bg font-sans antialiased">

@php
    $isCoTeamRoute = request()->is('institutions*')
        || request()->is('contacts*')
        || request()->is('supports*')
        || request()->is('potential-institutions*')
        || request()->is('salesforce-files*')
        || request()->is('store/*')
        || request()->is('co/*')
        || request()->is('coach/*');
    $sidebarUser = auth()->user();
    $activeTeamMenu = \App\Support\TeamMenuContext::activeMenu($sidebarUser);
    $showAllTeamSidebars = \App\Support\TeamMenuContext::showAllTeamSidebars($sidebarUser);
    $crossTeamReadOnly = \App\Support\TeamMenuContext::isCrossTeamReadOnlyContext($sidebarUser);

    $topbarDisplayName = 'User';
    if ($sidebarUser !== null) {
        $topbarDisplayName = \Illuminate\Support\Facades\Cache::remember(
            'layout:topbar-display-name:'.$sidebarUser->id,
            now()->addMinutes(10),
            fn (): string => $sidebarUser->preferredDisplayName()
        );
    }

    $canSeeManagementMenus = (bool) ($sidebarUser?->hasFullAccess());
    $canSeeSetupMenus = $canSeeManagementMenus || (bool) ($sidebarUser?->can('accessSetup'));

    $peopleTeams = collect();
    $hasDepartmentTable = \Illuminate\Support\Facades\Cache::remember(
        'layout:has-department-table:v1',
        now()->addMinutes(30),
        fn (): bool => \Illuminate\Support\Facades\Schema::hasTable('department')
    );

    if ($hasDepartmentTable) {
        $cachedTeams = \Illuminate\Support\Facades\Cache::remember(
            'layout:people-teams:v1',
            now()->addMinutes(10),
            fn (): array => \App\Models\Department::query()
                ->select('DEPTNO', 'DEPTNAME')
                ->get()
                ->map(fn (\App\Models\Department $department): array => [
                    'DEPTNO' => (string) $department->DEPTNO,
                    'DEPTNAME' => (string) $department->DEPTNAME,
                ])
                ->all()
        );

        $peopleTeams = \App\Models\Department::sortForPeopleSidebar(
            collect($cachedTeams)->map(function (array $row): \App\Models\Department {
                $department = new \App\Models\Department;
                $department->forceFill($row);

                return $department;
            })
        );
    }
@endphp

{{-- Alpine.js: 사이드바 아코디언(열고 닫기) 에 사용 --}}
<div class="h-screen flex flex-col overflow-hidden"
     x-data="{
        sidebarOpen: false,
         openPeople: {{ request()->routeIs('people.*') ? 'true' : 'false' }},
         openScheduleManagement: {{ request()->routeIs('schedules.*', 'shared-supplies.*', 'vehicle-usage-history.*') ? 'true' : 'false' }},
         openTeams: true,
        openCS: {{ $isCoTeamRoute && ($activeTeamMenu === 'cs' || ($activeTeamMenu === null && $sidebarUser?->isCsTeam())) ? 'true' : 'false' }},
        openCoach: {{ $isCoTeamRoute && ($activeTeamMenu === 'coach' || ($activeTeamMenu === null && $sidebarUser?->isCoachTeam())) ? 'true' : 'false' }},
        openCO: {{ $isCoTeamRoute && ($activeTeamMenu === 'co' || ($activeTeamMenu === null && ! $sidebarUser?->isCsTeam() && ! $sidebarUser?->isCoachTeam())) ? 'true' : 'false' }},
         openReview: false,
         openGoal: false,
         openSetup: {{ request()->routeIs('setup.*') ? 'true' : 'false' }},
    }"
    @keydown.escape.window="sidebarOpen = false">

    {{-- 상단 헤더 (전체 너비) --}}
    <header class="mochi-topbar flex-shrink-0">
        <div class="mochi-topbar-inner">
            <div class="mochi-topbar-brand-wrap">
                <button type="button"
                        class="mochi-topbar-menu-trigger md:hidden"
                        @click="sidebarOpen = true"
                        aria-label="메뉴 열기">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <div class="mochi-topbar-brand">GrapeSEED MOCHI</div>
            </div>

            <nav class="mochi-topbar-nav">
                {{-- liquid-glass-button과 동일 feDisplacementMap 필터 (호버 시 링크에만 적용) --}}
                <svg class="pointer-events-none absolute h-px w-px overflow-hidden opacity-0" aria-hidden="true" focusable="false">
                    <defs>
                        <filter
                            id="mochi-topbar-glass-filter"
                            x="0%"
                            y="0%"
                            width="100%"
                            height="100%"
                            color-interpolation-filters="sRGB"
                        >
                            <feTurbulence type="fractalNoise" baseFrequency="0.05 0.05" numOctaves="1" seed="1" result="turbulence" />
                            <feGaussianBlur in="turbulence" stdDeviation="2" result="blurredNoise" />
                            <feDisplacementMap
                                in="SourceGraphic"
                                in2="blurredNoise"
                                scale="70"
                                xChannelSelector="R"
                                yChannelSelector="B"
                                result="displaced"
                            />
                            <feGaussianBlur in="displaced" stdDeviation="4" result="finalBlur" />
                            <feComposite in="finalBlur" in2="finalBlur" operator="over" />
                        </filter>
                    </defs>
                </svg>
                @foreach(['OutLook' => '#', 'Portal' => 'https://portal.grapeseed.com/', 'eCount' => 'https://login.ecount.com/Login/KR/', 'Coaching' => 'https://www.gskcoaching.com/'] as $label => $href)
                    <a href="{{ $href }}"
                       class="mochi-topbar-glass-link"
                       @if(str_starts_with($href, 'http'))
                           target="_blank"
                           rel="noopener noreferrer"
                       @endif>
                        <span class="mochi-topbar-glass-link__depth" aria-hidden="true"></span>
                        <span
                            class="mochi-topbar-glass-link__blur"
                            style="backdrop-filter: url('#mochi-topbar-glass-filter'); -webkit-backdrop-filter: url('#mochi-topbar-glass-filter')"
                            aria-hidden="true"
                        ></span>
                        <span class="mochi-topbar-glass-link__label">{{ $label }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mochi-topbar-user">
                @auth
                    <livewire:inbound-notification-bell />
                @endauth
                {{-- 프로필 (편집 페이지로 이동) --}}
                <a href="{{ route('profile.edit') }}" class="mochi-topbar-profile">
                    <span class="mochi-topbar-profile__depth" aria-hidden="true"></span>
                    <span
                        class="mochi-topbar-profile__blur"
                        style="backdrop-filter: url('#mochi-topbar-glass-filter'); -webkit-backdrop-filter: url('#mochi-topbar-glass-filter')"
                        aria-hidden="true"
                    ></span>
                    <span class="mochi-topbar-profile__content">
                        <span class="w-6 h-6 rounded-full bg-[#d9e0eb] border border-white/50 flex-shrink-0" aria-hidden="true"></span>
                        <span class="text-[12px] text-white font-medium truncate max-w-[10rem]" title="{{ $topbarDisplayName }}">{{ $topbarDisplayName }}</span>
                    </span>
                </a>
                {{-- 로그아웃 (별도 필) --}}
                <div class="mochi-topbar-logout">
                    <span class="mochi-topbar-logout__depth" aria-hidden="true"></span>
                    <span
                        class="mochi-topbar-logout__blur"
                        style="backdrop-filter: url('#mochi-topbar-glass-filter'); -webkit-backdrop-filter: url('#mochi-topbar-glass-filter')"
                        aria-hidden="true"
                    ></span>
                    <div class="mochi-topbar-logout__content">
                        <form method="POST" action="{{ route('logout') }}" class="m-0 inline-flex items-center justify-center leading-none">
                            @csrf
                            <button
                                type="submit"
                                class="m-0 inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-sm border-0 bg-transparent p-0 text-[12px] font-medium text-white/92 transition-colors hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/50 md:gap-0"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[18px] shrink-0 md:hidden" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                                </svg>
                                <span class="whitespace-nowrap max-md:sr-only">로그아웃</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div x-cloak
         x-show="sidebarOpen"
         class="mochi-sidebar-backdrop md:hidden"
         @click="sidebarOpen = false"
         aria-hidden="true"></div>

    <div class="flex flex-1 overflow-hidden">

    {{-- ══════════════════════════════════════════
         사이드바
    ══════════════════════════════════════════ --}}
    <aside class="mochi-sidebar flex flex-col flex-shrink-0 overflow-y-auto"
           :class="{ 'sidebar-open': sidebarOpen }">

        {{-- 메뉴 전체 (브랜드는 상단바에만 표시) --}}
        <nav class="sidebar-nav flex-1"
             @click="if (window.innerWidth < 768 && $event.target.closest('a')) { sidebarOpen = false }">

            {{-- ── People ── --}}
            <div class="sidebar-group">
                @php
                    $activePeopleTeam = (string) request()->query('team', '');
                    $activePeopleStatus = (string) request()->query('status', '');
                @endphp

                <button type="button"
                        @click="openPeople = !openPeople"
                        class="sidebar-item sidebar-focusable
                               {{ request()->routeIs('people.*') ? 'sidebar-item-active' : 'sidebar-item-default' }}">
                    <span class="sidebar-item-lead">
                        @include('partials.sidebar-menu-icon', ['name' => 'users'])
                        <span class="font-medium">People</span>
                    </span>
                    <svg class="sidebar-chevron transition-transform duration-200"
                         :class="openPeople ? 'rotate-90' : ''"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <div x-show="openPeople" class="sidebar-sublist">
                    <a href="{{ route('people.index') }}"
                       class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                              {{ request()->routeIs('people.*') && $activePeopleTeam === '' && $activePeopleStatus === ''
                                  ? 'sidebar-subitem-active'
                                  : '' }}">
                        <span class="sidebar-subitem-label">전체 Employees</span>
                    </a>

                    @foreach($peopleTeams as $team)
                        @if($team->showsInactiveEmployeesInSidebar())
                            @continue
                        @endif
                        <a href="{{ route('people.index', ['team' => $team->DEPTNO]) }}"
                           class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                                  {{ request()->routeIs('people.*') && $activePeopleTeam === (string) $team->DEPTNO && $activePeopleStatus === ''
                                      ? 'sidebar-subitem-active'
                                      : '' }}">
                            <span class="sidebar-subitem-label">{{ $team->displayName() }}</span>
                        </a>
                    @endforeach

                    <a href="{{ route('people.index', ['status' => '0']) }}"
                       class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                              {{ request()->routeIs('people.*') && $activePeopleStatus === '0'
                                  ? 'sidebar-subitem-active'
                                  : '' }}">
                        <span class="sidebar-subitem-label">비활성화 직원</span>
                    </a>
                </div>
            </div>

            {{-- ── 일정 관리 ── --}}
            @php
                $scheduleMenuEnabled = $canSeeManagementMenus;
            @endphp
            <div class="sidebar-group">
                <button type="button"
                        @if($scheduleMenuEnabled)
                            @click="openScheduleManagement = !openScheduleManagement"
                        @endif
                        class="sidebar-item sidebar-focusable
                               {{ request()->routeIs('schedules.*', 'shared-supplies.*', 'vehicle-usage-history.*') ? 'sidebar-item-active' : 'sidebar-item-default' }}
                               {{ $scheduleMenuEnabled ? '' : 'cursor-not-allowed opacity-50' }}"
                        @if(! $scheduleMenuEnabled) aria-disabled="true" @endif>
                    <span class="sidebar-item-lead">
                        @include('partials.sidebar-menu-icon', ['name' => 'calendar'])
                        <span class="font-medium">일정 관리</span>
                    </span>
                    <svg class="sidebar-chevron transition-transform duration-200"
                         :class="openScheduleManagement ? 'rotate-90' : ''"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <div x-show="openScheduleManagement" class="sidebar-sublist">
                    @if($scheduleMenuEnabled)
                        <a href="{{ route('schedules.index') }}"
                           class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                                  {{ request()->routeIs('schedules.*') ? 'sidebar-subitem-active' : '' }}"
                           @if(request()->routeIs('schedules.*')) aria-current="page" @endif>
                            <span class="sidebar-subitem-label">일정 캘린더</span>
                        </a>
                        <a href="{{ route('shared-supplies.index') }}"
                           class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                                  {{ request()->routeIs('shared-supplies.*') ? 'sidebar-subitem-active' : '' }}"
                           @if(request()->routeIs('shared-supplies.*')) aria-current="page" @endif>
                            <span class="sidebar-subitem-label">공용품 관리</span>
                        </a>
                        <a href="{{ route('vehicle-usage-history.index') }}"
                           class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                                  {{ request()->routeIs('vehicle-usage-history.*') ? 'sidebar-subitem-active' : '' }}"
                           @if(request()->routeIs('vehicle-usage-history.*')) aria-current="page" @endif>
                            <span class="sidebar-subitem-label">차량별 사용 내역</span>
                        </a>
                    @else
                        <span class="sidebar-subitem sidebar-subitem-row opacity-50 cursor-not-allowed">
                            <span class="sidebar-subitem-label">일정 캘린더</span>
                        </span>
                        <span class="sidebar-subitem sidebar-subitem-row opacity-50 cursor-not-allowed">
                            <span class="sidebar-subitem-label">공용품 관리</span>
                        </span>
                        <span class="sidebar-subitem sidebar-subitem-row opacity-50 cursor-not-allowed">
                            <span class="sidebar-subitem-label">차량별 사용 내역</span>
                        </span>
                    @endif
                </div>
            </div>

            {{-- ── Teams (열고 닫기 가능) ── --}}
            <div class="sidebar-group">
                <button type="button"
                        @click="openTeams = !openTeams"
                        class="sidebar-item sidebar-focusable"
                        :class="(openTeams && (openCS || openCoach || openCO)) ? 'sidebar-item-active' : 'sidebar-item-default'">
                    <span class="sidebar-item-lead">
                        @include('partials.sidebar-menu-icon', ['name' => 'user-group'])
                        <span class="font-medium">Teams</span>
                    </span>
                    <svg class="sidebar-chevron transition-transform duration-200"
                         :class="openTeams ? 'rotate-90' : ''"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <div x-show="openTeams" class="sidebar-sublist">

                    @php
                        $isSidebarMenuActive = function (array $menu, ?string $expectedTeamMenu = null) use ($sidebarUser): bool {
                            if (! empty($menu['routeIs'] ?? null)) {
                                $active = request()->routeIs($menu['routeIs']);
                            } elseif (! empty($menu['route'] ?? null)) {
                                $active = request()->routeIs($menu['route'].'.*');
                            } else {
                                $active = false;
                            }

                            if (! $active || $expectedTeamMenu === null) {
                                return $active;
                            }

                            $activeTeamMenu = request()->query('team_menu');
                            if (! is_string($activeTeamMenu) || $activeTeamMenu === '') {
                                $activeTeamMenu = \App\Support\TeamMenuContext::activeMenu($sidebarUser);
                            }

                            return $activeTeamMenu === $expectedTeamMenu;
                        };
                    @endphp

                    @if($showAllTeamSidebars)
                        @include('partials.sidebar-cs-team-block')

                        @if($canSeeManagementMenus)
                            {{-- Admin --}}
                            <button type="button" class="sidebar-item sidebar-focusable sidebar-item-default">
                                <span class="sidebar-item-lead min-w-0 flex-1 break-words text-left">
                                    @include('partials.sidebar-menu-icon', ['name' => 'cog'])
                                    <span>Admin</span>
                                </span>
                                <svg class="h-3 w-3 shrink-0 text-[#98a2b3]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                </svg>
                            </button>
                        @endif

                        @include('partials.sidebar-coach-team-block')
                    @endif

                    @if($showAllTeamSidebars)
                    {{-- CO Team (하위 메뉴 포함) --}}
                    <div>
                        <button type="button"
                                @click="openCO = !openCO; if (openCO) { openSetup = false; openCS = false; openCoach = false }"
                                class="sidebar-item sidebar-team-toggle sidebar-focusable"
                                :class="openCO ? 'sidebar-team-toggle-open' : ''"
                                :aria-expanded="openCO ? 'true' : 'false'">
                            <span class="sidebar-item-lead">
                                @include('partials.sidebar-menu-icon', ['name' => 'briefcase'])
                                <span>CO Team</span>
                            </span>
                            <svg class="sidebar-chevron transition-transform duration-200"
                                 :class="openCO ? 'rotate-90' : ''"
                                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>

                        {{-- CO Team 하위 메뉴 --}}
                        <div x-show="openCO" class="sidebar-sublist">

                            @php
                                $coMenus = [
                                    // ['label' => '전체기관리스트', 'href' => '/institutions', 'route' => 'institutions', 'icon' => 'building'],
                                    ['label' => '기관리스트',     'path' => '/institutions', 'route' => 'institutions', 'icon' => 'building'],
                                    ['label' => '교직원 연락처보기', 'path' => '/contacts',     'route' => 'contacts',     'icon' => 'phone'],
                                    ['label' => '기관지원보고서', 'path' => '/supports',     'route' => 'supports',     'icon' => 'document'],
                                    ['label' => '잠재기관 등록하기', 'path' => route('potential-institutions.index'), 'route' => '', 'routeIs' => 'potential-institutions.index', 'icon' => 'calendar'],
                                    ['label' => '잠재기관 목록보기',   'path' => route('potential-institutions.view'), 'route' => '', 'routeIs' => 'potential-institutions.view', 'icon' => 'eye'],
                                    ['label' => 'GS Brochure', 'path' => route('co.gs-brochure'), 'route' => '', 'routeIs' => 'co.gs-brochure*', 'icon' => 'document'],
                                    ['label' => 'Store 재고',  'path' => route('store.inventory.index'), 'route' => '', 'routeIs' => 'store.inventory.index', 'icon' => 'cart'],
                                    ['label' => 'Store판매내역',  'path' => route('store.sales.index'), 'route' => '', 'routeIs' => 'store.sales.index', 'icon' => 'cart'],
                                    ['label' => 'Salesforce파일', 'path' => route('salesforce-files.index'), 'route' => '', 'routeIs' => 'salesforce-files.index', 'icon' => 'server'],
                                    //['label' => '계약물건',       'href' => '#',             'route' => '',             'icon' => 'clipboard'],
                                    //['label' => '평가기관리스트', 'href' => '#',             'route' => '',             'icon' => 'chart'],
                                ];
                            @endphp

                            @foreach($coMenus as $menu)
                                <a href="{{ $menu['path'] }}{{ str_contains($menu['path'], '?') ? '&' : '?' }}team_menu=co"
                                   @if(! empty($menu['blank'] ?? false))
                                       target="_blank" rel="noopener noreferrer"
                                   @endif
                                   class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ $isSidebarMenuActive($menu, 'co') ? 'sidebar-subitem-active' : '' }}"
                                   @if($isSidebarMenuActive($menu, 'co')) aria-current="page" @endif>
                                    @include('partials.sidebar-menu-icon', ['name' => $menu['icon'], 'small' => true])
                                    <span class="sidebar-subitem-label">{{ $menu['label'] }}</span>
                                </a>
                            @endforeach

                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- <div class="sidebar-divider"></div> --}}

            @if($canSeeSetupMenus)
                {{-- ── Review · Goal · Feedback (메뉴 숨김 처리, 다시 노출하려면 @if(false)를 제거) ── --}}
                @if(false)
                <div class="sidebar-group">
                    <button type="button"
                            @click="openReview = !openReview"
                            class="sidebar-item sidebar-focusable sidebar-item-default">
                        <span class="sidebar-item-lead">
                            @include('partials.sidebar-menu-icon', ['name' => 'chat'])
                            <span class="font-medium">Review</span>
                        </span>
                        <svg class="sidebar-chevron transition-transform" :class="openReview ? 'rotate-90' : ''"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

                {{-- ── Goal ── --}}
                <div class="sidebar-group">
                    <button type="button"
                            @click="openGoal = !openGoal"
                            class="sidebar-item sidebar-focusable sidebar-item-default">
                        <span class="sidebar-item-lead">
                            @include('partials.sidebar-menu-icon', ['name' => 'flag'])
                            <span class="font-medium">Goal</span>
                        </span>
                        <svg class="sidebar-chevron transition-transform" :class="openGoal ? 'rotate-90' : ''"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

                {{-- ── Feedback ── --}}
                <div class="sidebar-group">
                    <button type="button" class="sidebar-item sidebar-focusable sidebar-item-default">
                        <span class="sidebar-item-lead">
                            @include('partials.sidebar-menu-icon', ['name' => 'chat'])
                            <span class="font-medium">Feedback</span>
                        </span>
                        <svg class="sidebar-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
                @endif

                {{-- ── Configuration ── --}}
                <div class="sidebar-group">
                    <button type="button" class="sidebar-item sidebar-focusable sidebar-item-default">
                        <span class="sidebar-item-lead">
                            @include('partials.sidebar-menu-icon', ['name' => 'cog'])
                            <span class="font-medium">Configuration</span>
                        </span>
                        <svg class="sidebar-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

                {{-- ── Setup ── --}}
                <div class="sidebar-group">
                    <button type="button"
                            @click="openSetup = !openSetup; if (openSetup) { openCO = false }"
                            class="sidebar-item sidebar-focusable
                                   {{ request()->routeIs('setup.*') ? 'sidebar-item-active' : 'sidebar-item-default' }}">
                        <span class="sidebar-item-lead">
                            @include('partials.sidebar-menu-icon', ['name' => 'cog'])
                            <span class="font-medium">Setup</span>
                        </span>
                        <svg class="sidebar-chevron transition-transform duration-200"
                             :class="openSetup ? 'rotate-90' : ''"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <div x-show="openSetup" class="sidebar-sublist">
                        <a href="{{ route('setup.index') }}"
                           class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                                  {{ request()->routeIs('setup.index') ? 'sidebar-subitem-active' : '' }}">
                            <span class="sidebar-subitem-label">SetUp 홈</span>
                        </a>
                        <a href="{{ route('setup.team') }}"
                           class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                                  {{ request()->routeIs('setup.team') ? 'sidebar-subitem-active' : '' }}">
                            <span class="sidebar-subitem-label">팀 관리</span>
                        </a>
                    </div>
                </div>
            @endif

        </nav>
    </aside>

    {{-- ══════════════════════════════════════════
         오른쪽 메인 영역
    ══════════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- 페이지 타이틀 바 --}}
        <div class="bg-white border-b border-gray-200 px-6 py-3 flex-shrink-0">
            <h1 class="text-[15px] font-semibold text-[#2b78c5]">{{ $title ?? '기관 리스트' }}</h1>
        </div>

        {{-- 페이지 콘텐츠 --}}
        <main class="mochi-content-wrap flex-1 overflow-y-auto">
            @if($crossTeamReadOnly ?? false)
                <div class="mx-4 mt-4 mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                    타 팀 메뉴에서는 조회만 가능합니다.
                </div>
            @endif
            {{ $slot }}
        </main>

    </div>
</div>
</div>

@livewireScripts
</body>
</html>
