<div>
    <button type="button"
            @click="openCS = !openCS; if (openCS) { openCoach = false; openCO = false; openLogistics = false }"
            class="sidebar-item sidebar-team-toggle sidebar-focusable"
            :class="openCS ? 'sidebar-team-toggle-open' : ''"
            :aria-expanded="openCS ? 'true' : 'false'">
        <span class="sidebar-item-lead min-w-0 flex-1 break-words text-left">
            @include('partials.sidebar-menu-icon', ['name' => 'phone'])
            <span>CS Team</span>
        </span>
        <svg class="h-3 w-3 shrink-0 text-[#98a2b3] transition-transform duration-200"
             :class="openCS ? 'rotate-90' : ''"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
        </svg>
    </button>

    <div x-show="openCS" class="sidebar-sublist">
        @include('partials.sidebar-shared-team-menus', [
            'teamMenuQuery' => 'cs',
            'sharedTeamMenus' => [
                ['label' => '기관리스트', 'path' => '/institutions', 'route' => 'institutions', 'icon' => 'building'],
                ['label' => '교직원 연락처보기', 'path' => '/contacts', 'route' => 'contacts', 'icon' => 'phone'],
                ['label' => '기관지원보고서', 'path' => '/supports', 'route' => 'supports', 'icon' => 'document'],
                ['label' => '기관 이슈', 'path' => '/institution-issues', 'routeIs' => 'institution-issues.index', 'icon' => 'document'],
            ],
        ])
        @php
            $csStoreMenus = [
                [
                    'label' => '반품 현황',
                    'path' => route('store.returns.index'),
                    'route' => '',
                    'routeIs' => 'store.returns.index',
                    'icon' => 'clipboard',
                ],
            ];
        @endphp
        @foreach($csStoreMenus as $menu)
            @php
                $menuActive = ($isSidebarMenuActive ?? null) !== null
                    ? $isSidebarMenuActive($menu, 'cs')
                    : false;
            @endphp
            <a href="{{ $menu['path'] }}{{ str_contains($menu['path'], '?') ? '&' : '?' }}team_menu=cs"
               class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ $menuActive ? 'sidebar-subitem-active' : '' }}"
               @if($menuActive) aria-current="page" @endif>
                @include('partials.sidebar-menu-icon', ['name' => $menu['icon'], 'small' => true])
                <span class="sidebar-subitem-label">{{ $menu['label'] }}</span>
            </a>
        @endforeach
        @include('partials.sidebar-brochure-team-menus', ['teamMenuQuery' => 'cs'])
    </div>
</div>
