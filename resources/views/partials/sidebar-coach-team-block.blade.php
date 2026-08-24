<div>
    <button type="button"
            @click="openCoach = !openCoach; if (openCoach) { openCS = false; openCO = false }"
            class="sidebar-item sidebar-team-toggle sidebar-focusable"
            :class="openCoach ? 'sidebar-team-toggle-open' : ''"
            :aria-expanded="openCoach ? 'true' : 'false'">
        <span class="sidebar-item-lead min-w-0 flex-1 break-words text-left">
            @include('partials.sidebar-menu-icon', ['name' => 'users'])
            <span>Coach Team</span>
        </span>
        <svg class="h-3 w-3 shrink-0 text-[#98a2b3] transition-transform duration-200"
             :class="openCoach ? 'rotate-90' : ''"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
        </svg>
    </button>

    <div x-show="openCoach" class="sidebar-sublist">
        <a href="/coach/teacher-support?team_menu=coach"
           class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ request()->routeIs('coach.teacher-support.*') ? 'sidebar-subitem-active' : '' }}"
           @if(request()->routeIs('coach.teacher-support.*')) aria-current="page" @endif>
            @include('partials.sidebar-menu-icon', ['name' => 'users', 'small' => true])
            <span class="sidebar-subitem-label">교사 지원 현황</span>
        </a>
        @include('partials.sidebar-shared-team-menus', [
            'teamMenuQuery' => 'coach',
            'sharedTeamMenus' => [
                ['label' => '교사 및 기관 지원', 'path' => '/supports', 'route' => 'supports', 'icon' => 'document'],
                ['label' => '기관리스트', 'path' => '/institutions', 'route' => 'institutions', 'icon' => 'building'],
                ['label' => '교직원 연락처보기', 'path' => '/contacts', 'route' => 'contacts', 'icon' => 'phone'],
            ],
        ])
        @include('partials.sidebar-brochure-team-menus', ['teamMenuQuery' => 'coach'])
        <a href="/coach/retired-teachers?team_menu=coach"
           class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ ($retiredTeachersSidebarActive ?? (request()->routeIs('coach.retired-teachers.*') && request()->query('sidebar_context') !== 'admin')) ? 'sidebar-subitem-active' : '' }}"
           @if($retiredTeachersSidebarActive ?? (request()->routeIs('coach.retired-teachers.*') && request()->query('sidebar_context') !== 'admin')) aria-current="page" @endif>
            @include('partials.sidebar-menu-icon', ['name' => 'users', 'small' => true])
            <span class="sidebar-subitem-label">퇴직교사 리스트</span>
        </a>
        @can('viewCoachTeamKpi')
            <a href="/coach/institution-coverage?team_menu=coach"
               class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ request()->routeIs('coach.institution-coverage.*') ? 'sidebar-subitem-active' : '' }}"
               @if(request()->routeIs('coach.institution-coverage.*')) aria-current="page" @endif>
                @include('partials.sidebar-menu-icon', ['name' => 'building', 'small' => true])
                <span class="sidebar-subitem-label">지원방법·횟수</span>
            </a>
            <a href="{{ route('coach.team-kpi.index', ['team_menu' => 'coach']) }}"
               class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ request()->routeIs('coach.team-kpi.*') ? 'sidebar-subitem-active' : '' }}"
               @if(request()->routeIs('coach.team-kpi.*')) aria-current="page" @endif>
                @include('partials.sidebar-menu-icon', ['name' => 'chart', 'small' => true])
                <span class="sidebar-subitem-label">팀 지원 KPI</span>
            </a>
        @endcan
    </div>
</div>
