@php
    $coItems = [
        '기관리스트' => route('institutions.index'),
        '전체기관별자료' => '#',
        '기관연락처정보' => '#',
        '기관지침보고서' => '#',
        '잠재기관관리현황' => '#',
        '기관별계약서' => '#',
        '기관별지원내역' => '#',
        '기관별스토어판매' => '#',
        '기관별재고현황' => '#',
    ];
@endphp

<aside class="w-56 shrink-0 border-r border-gray-200 bg-gray-100 text-sm text-gray-800">
    <nav class="flex flex-col gap-0.5 py-3">
        @foreach (['People', 'Teams', 'Review', 'Goal', 'Feedback', 'Configuration', 'Setup'] as $section)
            <div class="px-3 py-1.5">
                <button type="button" class="flex w-full items-center justify-between rounded px-1 py-0.5 text-left font-medium text-gray-700 hover:bg-gray-200/80">
                    <span>{{ $section }}</span>
                    <svg class="h-3.5 w-3.5 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                </button>
            </div>
        @endforeach

        <div class="mt-2 border-t border-gray-200 pt-2">
            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">CO Team</p>
            <ul class="space-y-0.5">
                @foreach (['CS Team', 'Admin', 'TR Team', 'CO Team'] as $team)
                    <li>
                        <a href="#" class="block px-4 py-1 text-gray-600 hover:bg-gray-200/60">{{ $team }}</a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-2 max-h-[calc(100vh-20rem)] overflow-y-auto border-t border-gray-200 pt-2">
            <ul class="space-y-0.5 pb-4">
                @foreach ($coItems as $label => $href)
                    @php
                        $active = $label === '기관리스트' && request()->routeIs('institutions.index');
                    @endphp
                    <li>
                        <a
                            href="{{ $href }}"
                            @class([
                                'block px-4 py-1.5 border-l-2',
                                'border-mochi-header bg-white font-medium text-gray-900 shadow-sm' => $active,
                                'border-transparent text-gray-600 hover:bg-gray-200/50' => ! $active,
                            ])
                        >{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>
</aside>
