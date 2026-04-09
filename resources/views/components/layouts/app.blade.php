<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'GrapeSEED MOCHI' }}</title>
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
        || request()->is('store/*');
@endphp

{{-- Alpine.js: 사이드바 아코디언(열고 닫기) 에 사용 --}}
<div class="h-screen flex flex-col overflow-hidden"
     x-data="{
         openPeople: {{ request()->routeIs('people.*') ? 'true' : 'false' }},
         openTeams: true,
         openCO: {{ $isCoTeamRoute ? 'true' : 'false' }},
         openReview: false,
         openGoal: false,
         openSetup: {{ request()->routeIs('setup.*') ? 'true' : 'false' }},
     }">

    {{-- 상단 헤더 (전체 너비) --}}
    <header class="mochi-topbar flex-shrink-0">
        <div class="mochi-topbar-inner">
            <div class="mochi-topbar-brand">GrapeSEED MOCHI</div>

            <nav class="mochi-topbar-nav">
                @foreach(['OutLook' => '#', 'Portal' => '#', 'eCount' => '#', 'Coaching' => '#'] as $label => $href)
                    <a href="{{ $href }}">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="mochi-topbar-user">
                <span class="mochi-topbar-action" aria-hidden="true"></span>
                <div class="mochi-topbar-account">
                    <span class="w-6 h-6 rounded-full bg-[#d9e0eb] border border-white/50 flex-shrink-0" aria-hidden="true"></span>
                    <span class="text-[12px] text-white font-medium truncate max-w-[10rem]" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</span>
                    <span class="w-px h-4 bg-white/35 flex-shrink-0" aria-hidden="true"></span>
                    <form method="POST" action="{{ route('logout') }}" class="inline m-0 leading-none">
                        @csrf
                        <button type="submit" class="bg-transparent border-0 p-0 m-0 text-[12px] text-white/92 font-medium cursor-pointer hover:text-white hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/50 rounded-sm">
                            로그아웃
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">

    {{-- ══════════════════════════════════════════
         사이드바
    ══════════════════════════════════════════ --}}
    <aside class="mochi-sidebar flex flex-col flex-shrink-0 overflow-y-auto">

        {{-- 메뉴 전체 (브랜드는 상단바에만 표시) --}}
        <nav class="sidebar-nav flex-1">

            {{-- ── People ── --}}
            <div class="sidebar-group">
                @php
                    $peopleTeams = \Illuminate\Support\Facades\Schema::hasTable('department')
                        ? \App\Models\Department::query()
                            ->select('DEPTNO', 'DEPTNAME')
                            ->orderBy('DEPTNO')
                            ->get()
                        : collect();
                    $activePeopleTeam = (string) request()->query('team', '');
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
                       class="sidebar-subitem sidebar-focusable
                              {{ request()->routeIs('people.*') && $activePeopleTeam === ''
                                  ? 'sidebar-subitem-active'
                                  : '' }}">
                        전체 Employees
                    </a>

                    @foreach($peopleTeams as $team)
                        <a href="{{ route('people.index', ['team' => $team->DEPTNO]) }}"
                           class="sidebar-subitem sidebar-focusable
                                  {{ request()->routeIs('people.*') && $activePeopleTeam === (string) $team->DEPTNO
                                      ? 'sidebar-subitem-active'
                                      : '' }}">
                            {{ $team->DEPTNAME ?: $team->DEPTNO }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ── Teams (열고 닫기 가능) ── --}}
            <div class="sidebar-group">
                <button type="button"
                        @click="openTeams = !openTeams"
                        class="sidebar-item sidebar-focusable {{ $isCoTeamRoute ? 'sidebar-item-active' : 'sidebar-item-default' }}">
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

                    {{-- CS Team --}}
                    <button type="button" class="sidebar-subitem sidebar-focusable flex w-full items-center justify-between gap-1 text-left">
                        <span>CS Team</span>
                        <svg class="h-3 w-3 shrink-0 text-[#98a2b3]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                        </svg>
                    </button>

                    {{-- Admin --}}
                    <button type="button" class="sidebar-subitem sidebar-focusable flex w-full items-center justify-between gap-1 text-left">
                        <span>Admin</span>
                        <svg class="h-3 w-3 shrink-0 text-[#98a2b3]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                        </svg>
                    </button>

                    {{-- TR Team --}}
                    <button type="button" class="sidebar-subitem sidebar-focusable flex w-full items-center justify-between gap-1 text-left">
                        <span>TR Team</span>
                        <svg class="h-3 w-3 shrink-0 text-[#98a2b3]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                        </svg>
                    </button>

                    {{-- CO Team (하위 메뉴 포함) --}}
                    <div>
                        <button type="button"
                                @click="openCO = !openCO; if (openCO) { openSetup = false }"
                                class="sidebar-item sidebar-focusable {{ $isCoTeamRoute ? 'sidebar-item-active' : 'sidebar-item-default' }}">
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
                                    ['label' => '기관리스트',     'href' => '/institutions', 'route' => 'institutions', 'icon' => 'building'],
                                    ['label' => '기관연락처보기', 'href' => '/contacts',     'route' => 'contacts',     'icon' => 'phone'],
                                    ['label' => '기관지원보고서', 'href' => '/supports',     'route' => 'supports',     'icon' => 'document'],
                                    ['label' => '잠재기관리스트', 'href' => '/potential-institutions', 'route' => 'potential-institutions', 'icon' => 'calendar'],
                                    ['label' => 'Store판매내역',  'href' => '#',             'route' => '',             'icon' => 'cart'],
                                    ['label' => '잠재기관보기',   'href' => '#',             'route' => '',             'icon' => 'eye'],
                                    ['label' => 'Salesforce파일', 'href' => '/salesforce-files', 'route' => 'salesforce-files', 'icon' => 'server'],
                                    ['label' => '계약물건',       'href' => '#',             'route' => '',             'icon' => 'clipboard'],
                                    ['label' => '평가기관리스트', 'href' => '#',             'route' => '',             'icon' => 'chart'],
                                ];
                            @endphp

                            @foreach($coMenus as $menu)
                                <a href="{{ $menu['href'] }}"
                                   class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                                          {{ ($menu['route'] && request()->is($menu['route'].'*'))
                                             ? 'sidebar-subitem-active'
                                             : '' }}">
                                    @include('partials.sidebar-menu-icon', ['name' => $menu['icon'], 'small' => true])
                                    <span class="sidebar-subitem-label truncate">{{ $menu['label'] }}</span>
                                </a>
                            @endforeach

                        </div>
                    </div>
                </div>
            </div>

            {{-- <div class="sidebar-divider"></div> --}}

            {{-- ── Review ── --}}
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
                        <span class="sidebar-subitem-label truncate">SetUp 홈</span>
                    </a>
                    <a href="{{ route('setup.team') }}"
                       class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                              {{ request()->routeIs('setup.team') ? 'sidebar-subitem-active' : '' }}">
                        <span class="sidebar-subitem-label truncate">팀 관리</span>
                    </a>
                    <a href="{{ route('setup.common-codes') }}"
                       class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                              {{ request()->routeIs('setup.common-codes') ? 'sidebar-subitem-active' : '' }}">
                        <span class="sidebar-subitem-label truncate">공통코드</span>
                    </a>
                    <a href="{{ route('setup.roles') }}"
                       class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                              {{ request()->routeIs('setup.roles') ? 'sidebar-subitem-active' : '' }}">
                        <span class="sidebar-subitem-label truncate">역할·권한</span>
                    </a>
                    @can('manageEmployeeDepartment')
                        <a href="{{ route('setup.employees.create') }}"
                           class="sidebar-subitem sidebar-subitem-row sidebar-focusable
                                  {{ request()->routeIs('setup.employees.create') ? 'sidebar-subitem-active' : '' }}">
                            <span class="sidebar-subitem-label truncate">직원 등록</span>
                        </a>
                    @endcan
                </div>
            </div>

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
            {{ $slot }}
        </main>

    </div>
</div>
</div>

@livewireScripts
</body>
</html>
