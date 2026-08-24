@php
    $coach = $coachRow['coach'];
    $periodColumns = $periodColumns ?? [];
    $colspan = 2 + count($periodColumns);
@endphp
<div wire:key="coach-matrix-{{ $coach }}"
     x-data="{ open: false }"
     class="mochi-table-card overflow-hidden">
    <button type="button"
            class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50/80"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-label="{{ $coach }}, 총 {{ $coachRow['total'] }}회, 월별 표 펼치기">
        <span class="font-medium text-gray-900">{{ $coach }}</span>
        <span class="text-sm text-gray-500">
            총 {{ $coachRow['total'] }}회
            <span class="text-gray-300">·</span>
            기관 {{ $coachRow['institution_total'] ?? 0 }}
            <span class="text-gray-300">·</span>
            교사 {{ $coachRow['teacher_total'] ?? 0 }}
        </span>
        <span class="ml-auto text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
    </button>

    <div x-show="open" x-cloak class="border-t border-gray-100 overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="mochi-table-head">
            <tr class="text-gray-700">
                <th class="px-3 py-2 text-left sticky left-0 bg-[#e8e6d9] z-10">유형</th>
                @foreach($periodColumns as $col)
                    <th @class([
                        'px-2 py-2 text-right',
                        'bg-amber-50/60' => $col['is_spillover'],
                    ])>{{ $col['label'] }}</th>
                @endforeach
                <th class="px-3 py-2 text-right">합계</th>
            </tr>
            </thead>
            <tbody>
            @php $currentGroup = null; @endphp
            @foreach($matrixRows as $matrixRow)
                @if($currentGroup !== $matrixRow['group'])
                    @php $currentGroup = $matrixRow['group']; @endphp
                    <tr @class([
                        'bg-mochi-header/15' => $currentGroup === 'institution',
                        'bg-emerald-50' => $currentGroup === 'teacher',
                    ])>
                        <td @class([
                            'px-3 py-1.5 text-center text-[14px] font-bold',
                            'text-mochi-header' => $currentGroup === 'institution',
                            'text-emerald-800' => $currentGroup === 'teacher',
                        ]) colspan="{{ $colspan }}">
                            {{ $currentGroup === 'institution' ? '기관지원' : '교사지원' }}
                        </td>
                    </tr>
                @endif
                @php
                    $rowKey = $matrixRow['key'];
                    $monthCounts = $coachRow['rows'][$rowKey] ?? [];
                    $rowTotal = array_sum($monthCounts);
                @endphp
                <tr class="mochi-table-row-hover border-t border-gray-50">
                    <td class="px-3 py-2 sticky left-0 bg-white z-10">
                        @if($rowTotal > 0)
                            <button type="button"
                                    wire:click="openCellModal(@js($coach), @js($rowKey))"
                                    class="text-left font-medium text-mochi-header underline hover:text-mochi-header/80 cursor-pointer">
                                {{ $matrixRow['label'] }}
                            </button>
                        @else
                            <span class="text-gray-500">{{ $matrixRow['label'] }}</span>
                        @endif
                    </td>
                    @foreach($periodColumns as $col)
                        @php $count = (int) ($monthCounts[$col['key']] ?? 0); @endphp
                        <td @class([
                            'px-2 py-2 text-right tabular-nums',
                            'bg-amber-50/40' => $col['is_spillover'],
                        ])>
                            @if($count > 0)
                                <button type="button"
                                        wire:click="openCellModal(@js($coach), @js($rowKey), @js((string) $col['key']))"
                                        class="text-mochi-header underline hover:text-mochi-header/80 cursor-pointer font-medium">
                                    {{ $count }}
                                </button>
                            @else
                                <span class="text-gray-300">0</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="px-3 py-2 text-right tabular-nums font-semibold text-gray-800">
                        @if($rowTotal > 0)
                            <button type="button"
                                    wire:click="openCellModal(@js($coach), @js($rowKey))"
                                    class="text-mochi-header underline hover:text-mochi-header/80 cursor-pointer">
                                {{ $rowTotal }}
                            </button>
                        @else
                            <span class="text-gray-400">0</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr class="border-t border-gray-200 bg-gray-50/80 font-semibold text-gray-800">
                <td class="px-3 py-2 sticky left-0 bg-gray-50 z-10">월 합계</td>
                @foreach($periodColumns as $col)
                    <td @class([
                        'px-2 py-2 text-right tabular-nums',
                        'bg-amber-50/40' => $col['is_spillover'],
                    ])>{{ $coachRow['months'][$col['key']] ?? 0 }}</td>
                @endforeach
                <td class="px-3 py-2 text-right tabular-nums">{{ $coachRow['total'] }}</td>
            </tr>
            </tfoot>
        </table>

        <div class="border-t border-gray-100 px-4 py-2 text-right">
            <a href="{{ $this->teacherSupportUrl($coach) }}"
               class="text-xs text-gray-500 hover:text-mochi-header underline">
                교사 지원 현황에서 보기
            </a>
        </div>
    </div>
</div>
