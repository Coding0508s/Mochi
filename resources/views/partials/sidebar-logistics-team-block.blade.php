@php
    $teamMenuQuery = 'logistics';
    $logisticsMenus = [
        [
            'label' => 'Store 재고',
            'path' => route('store.inventory.index'),
            'route' => '',
            'routeIs' => 'store.inventory.index',
            'icon' => 'cart',
        ],
        [
            'label' => 'Store 판매내역',
            'path' => route('store.sales.index'),
            'route' => '',
            'routeIs' => 'store.sales.index',
            'icon' => 'cart',
        ],
        [
            'label' => '반품 등록',
            'path' => route('store.returns.index'),
            'route' => '',
            'routeIs' => 'store.returns.index',
            'icon' => 'clipboard',
        ],
        [
            'label' => '반품 등록 품목',
            'path' => route('store.returns.products.index'),
            'route' => '',
            'routeIs' => 'store.returns.products.index',
            'icon' => 'cube',
            'adminOnly' => true,
        ],
        [
            'label' => 'GS Brochure',
            'path' => route('co.gs-brochure.admin.dashboard', ['section' => 'inventory']),
            'route' => '',
            'routeIs' => 'co.gs-brochure.admin.dashboard',
            'section' => 'inventory',
            'icon' => 'cube',
        ],
        [
            'label' => '운송장 입력',
            'path' => route('co.gs-brochure.admin.dashboard', ['section' => 'logistics']),
            'route' => '',
            'routeIs' => 'co.gs-brochure.admin.dashboard',
            'section' => 'logistics',
            'icon' => 'document',
        ],
    ];
@endphp

<div>
    <button type="button"
            @click="openLogistics = !openLogistics; if (openLogistics) { openCS = false; openCoach = false; openCO = false; openAdmin = false }"
            class="sidebar-item sidebar-team-toggle sidebar-focusable"
            :class="openLogistics ? 'sidebar-team-toggle-open' : ''"
            :aria-expanded="openLogistics ? 'true' : 'false'">
        <span class="sidebar-item-lead min-w-0 flex-1 break-words text-left">
            @include('partials.sidebar-menu-icon', ['name' => 'cube'])
            <span>Logistics Team</span>
        </span>
        <svg class="h-3 w-3 shrink-0 text-[#98a2b3] transition-transform duration-200"
             :class="openLogistics ? 'rotate-90' : ''"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
        </svg>
    </button>

    <div x-show="openLogistics" class="sidebar-sublist">
        @foreach($logisticsMenus as $menu)
            @if(($menu['adminOnly'] ?? false) && ! auth()->user()?->can('manageStoreReturnProducts'))
                @continue
            @endif
            @php
                $menuActive = ($isSidebarMenuActive ?? null) !== null
                    ? $isSidebarMenuActive($menu, $teamMenuQuery)
                    : false;
            @endphp
            <a href="{{ $menu['path'] }}{{ str_contains($menu['path'], '?') ? '&' : '?' }}team_menu={{ $teamMenuQuery }}"
               class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ $menuActive ? 'sidebar-subitem-active' : '' }}"
               @if($menuActive) aria-current="page" @endif>
                @include('partials.sidebar-menu-icon', ['name' => $menu['icon'], 'small' => true])
                <span class="sidebar-subitem-label">{{ $menu['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
