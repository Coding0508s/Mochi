<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'GrapeSEED DujjonKu' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="mochi-app-bg font-sans antialiased">

{{-- Alpine.js: 사이드바 아코디언(열고 닫기) 에 사용 --}}
<div class="h-screen flex flex-col overflow-hidden"
     x-data="{
         openPeople: {{ request()->routeIs('people.*') ? 'true' : 'false' }},
         openTeams: true,
         openCO: true,
         openReview: false,
         openGoal: false,
     }">

    {{-- 상단 헤더 (전체 너비) --}}
    <header class="mochi-topbar flex-shrink-0">
        <div class="mochi-topbar-inner">
            <div class="mochi-topbar-brand">GrapeSEED DujjonKu</div>

            <nav class="mochi-topbar-nav">
                @foreach(['OutLook' => '#', 'Portal' => '#', 'eCount' => '#', 'Coaching' => '#'] as $label => $href)
                    <a href="{{ $href }}">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="mochi-topbar-user">
                <span class="mochi-topbar-action" aria-hidden="true"></span>
                <div class="mochi-topbar-account">
                    <span class="w-6 h-6 rounded-full bg-[#d9e0eb] border border-white/50"></span>
                    <span class="text-[12px] text-white font-medium">Andrew Hur</span>
                    <span class="w-px h-4 bg-white/35"></span>
                    <span class="text-[12px] text-white/92 font-medium">로그아웃</span>
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
                @php
                    $isCoTeamRoute = request()->is('institutions*')
                        || request()->is('contacts*')
                        || request()->is('supports*')
                        || request()->is('potential-institutions*')
                        || request()->is('salesforce-files*')
                        || request()->is('store/*');
                @endphp
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
                                @click="openCO = !openCO"
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
                <a href="{{ route('setup.index') }}"
                   class="sidebar-item sidebar-focusable
                          {{ request()->routeIs('setup.*') ? 'sidebar-item-active' : 'sidebar-item-default' }}">
                    <span class="sidebar-item-lead">
                        @include('partials.sidebar-menu-icon', ['name' => 'cog'])
                        <span class="font-medium">Setup</span>
                    </span>
                </a>
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
