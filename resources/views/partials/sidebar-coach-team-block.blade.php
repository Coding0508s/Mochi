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
        <a href="/coach/retired-teachers?team_menu=coach"
           class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ request()->routeIs('coach.retired-teachers.*') ? 'sidebar-subitem-active' : '' }}"
           @if(request()->routeIs('coach.retired-teachers.*')) aria-current="page" @endif>
            @include('partials.sidebar-menu-icon', ['name' => 'users', 'small' => true])
            <span class="sidebar-subitem-label">퇴직교사 리스트</span>
        </a>
        @include('partials.sidebar-shared-team-menus', ['teamMenuQuery' => 'coach'])
    </div>
</div>
