@php
    $sharedTeamMenus = $sharedTeamMenus ?? [
        ['label' => '기관리스트', 'path' => '/institutions', 'route' => 'institutions', 'icon' => 'building'],
        ['label' => '교직원 연락처보기', 'path' => '/contacts', 'route' => 'contacts', 'icon' => 'phone'],
        ['label' => '기관지원보고서', 'path' => '/supports', 'route' => 'supports', 'icon' => 'document'],
    ];
    $teamMenuQuery = $teamMenuQuery ?? 'coach';
@endphp

@foreach($sharedTeamMenus as $menu)
    @php
        $menuActive = $isSidebarMenuActive($menu, $teamMenuQuery);
    @endphp
    <a href="{{ $menu['path'] }}{{ str_contains($menu['path'], '?') ? '&' : '?' }}team_menu={{ $teamMenuQuery }}"
       class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ $menuActive ? 'sidebar-subitem-active' : '' }}"
       @if($menuActive) aria-current="page" @endif>
        @include('partials.sidebar-menu-icon', ['name' => $menu['icon'], 'small' => true])
        <span class="sidebar-subitem-label">{{ $menu['label'] }}</span>
    </a>
@endforeach
