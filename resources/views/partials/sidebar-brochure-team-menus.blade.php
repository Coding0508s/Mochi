@php
    $teamMenuQuery = $teamMenuQuery ?? 'co';
    $activeTeamMenu = request()->query('team_menu');
    if (! is_string($activeTeamMenu) || $activeTeamMenu === '') {
        $activeTeamMenu = \App\Support\TeamMenuContext::activeMenu(auth()->user());
    }
    $teamMenuMatches = $activeTeamMenu === $teamMenuQuery;
    $isBrochureRequestActive = $teamMenuMatches && (
        request()->routeIs('co.gs-brochure.request')
        || request()->routeIs('co.gs-brochure.request.success')
    );
@endphp

<a href="{{ route('co.gs-brochure.request', ['team_menu' => $teamMenuQuery]) }}"
   class="sidebar-subitem sidebar-subitem-row sidebar-focusable {{ $isBrochureRequestActive ? 'sidebar-subitem-active' : '' }}"
   @if($isBrochureRequestActive) aria-current="page" @endif>
    @include('partials.sidebar-menu-icon', ['name' => 'document', 'small' => true])
    <span class="sidebar-subitem-label">브로셔 신청</span>
</a>
