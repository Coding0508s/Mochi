@php
    $detailCustomerType = (string) ($selectedInstitution['customer_type'] ?? '');
    $detailIsTerminated = str_contains($detailCustomerType, '해지');
    $detailSkCode = trim((string) ($selectedInstitution['skcode'] ?? ''));

    $activeTab = $activeSupportTeamTab ?? 'co';
    $activeBucket = $teamSupportHistory[$activeTab] ?? ['institution' => [], 'teacher' => []];
    $unknownBucket = $teamSupportHistory['unknown'] ?? ['institution' => [], 'teacher' => []];

    $activeInstitutionCount = count($activeBucket['institution'] ?? []);
    $activeTeacherCount = count($activeBucket['teacher'] ?? []);
    $activeTotalCount = $activeInstitutionCount + $activeTeacherCount;

    $unknownInstitutionCount = count($unknownBucket['institution'] ?? []);
    $unknownTeacherCount = count($unknownBucket['teacher'] ?? []);
    $unknownTotalCount = $unknownInstitutionCount + $unknownTeacherCount;

    $grandTotal = (int) (($teamSupportHistory['totals']['institution'] ?? 0) + ($teamSupportHistory['totals']['teacher'] ?? 0));

    $teamTabs = [
        'co' => 'CO Team',
        'coach' => 'Coach Team',
        'cs' => 'CS Team',
    ];

    $teamMenuForCreate = match ($activeTab) {
        'coach' => 'coach',
        'cs' => 'cs',
        default => 'co',
    };
@endphp

<div class="col-span-2 mt-2">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        <h3 class="text-base font-bold text-[#1f4f8f] flex items-center gap-2">
            <span class="inline-block w-2 h-2 rounded-full bg-mochi-header"></span>
            최근 10년 지원/소통 이력
        </h3>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full">
                총 {{ $grandTotal }}건
            </span>
            @if($detailSkCode !== '' && ! $detailIsTerminated)
                <a href="{{ route('supports.create', ['sk_code' => $detailSkCode, 'return' => 'institutions', 'team_menu' => $teamMenuForCreate]) }}"
                   class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-mochi-header rounded-lg hover:bg-mochi-header/90">
                    지원보고서 작성
                </a>
            @elseif($detailSkCode !== '' && $detailIsTerminated)
                <span class="text-xs text-gray-500">해지 기관은 신규 지원보고서 작성이 제한됩니다.</span>
            @endif
        </div>
    </div>

    @if($detailSkCode !== '' && ! $detailIsTerminated)
        <p class="text-xs text-gray-500 mb-2">작성 화면으로 이동합니다. SK·기관명·팀 메뉴가 자동으로 채워집니다.</p>
    @endif

    <div class="flex flex-wrap gap-1 mb-3 border-b border-gray-200 pb-2">
        @foreach($teamTabs as $tabKey => $tabLabel)
            @php
                $tabBucket = $teamSupportHistory[$tabKey] ?? ['institution' => [], 'teacher' => []];
                $tabCount = count($tabBucket['institution'] ?? []) + count($tabBucket['teacher'] ?? []);
            @endphp
            <button type="button"
                    wire:click="$set('activeSupportTeamTab', '{{ $tabKey }}')"
                    @class([
                        'px-3 py-1.5 text-xs font-medium rounded-lg transition-colors cursor-pointer',
                        'bg-mochi-header text-white' => $activeTab === $tabKey,
                        'text-gray-600 bg-gray-100 hover:bg-gray-200' => $activeTab !== $tabKey,
                    ])>
                {{ $tabLabel }}
                <span class="ml-1 opacity-80">({{ $tabCount }})</span>
            </button>
        @endforeach
    </div>

    <p class="text-xs text-gray-500 mb-3">
        {{ $teamTabs[$activeTab] ?? '팀' }} — 기관 {{ $activeInstitutionCount }}건 · 교사 {{ $activeTeacherCount }}건
    </p>

    <div class="space-y-4">
        <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-2">기관 지원 보고서</h4>
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="max-h-40 overflow-y-auto overflow-x-auto">
                    <table class="w-full text-xs whitespace-nowrap">
                        <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                        <tr class="text-gray-600">
                            <th class="px-3 py-2 text-left">지원일</th>
                            <th class="px-3 py-2 text-left">시간</th>
                            <th class="px-3 py-2 text-left">담당자</th>
                            <th class="px-3 py-2 text-left">지원방법</th>
                            <th class="px-3 py-2 text-left">참석자</th>
                            <th class="px-3 py-2 text-left">이슈</th>
                            <th class="px-3 py-2 text-left">소통내용</th>
                            <th class="px-3 py-2 text-center">상태</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($activeBucket['institution'] ?? [] as $history)
                            <tr wire:key="institution-support-{{ $activeTab }}-{{ $history['id'] }}"
                                wire:click="openSupportDetailModal({{ $history['id'] }})"
                                class="hover:bg-blue-50 cursor-pointer transition-colors">
                                <td class="px-3 py-2">{{ $history['support_date'] }}</td>
                                <td class="px-3 py-2">{{ $history['support_time'] }}</td>
                                <td class="px-3 py-2">{{ $history['tr_name'] }}</td>
                                <td class="px-3 py-2">{{ $history['support_type'] }}</td>
                                <td class="px-3 py-2 max-w-24 truncate" title="{{ $history['target'] }}">{{ $history['target'] }}</td>
                                <td class="px-3 py-2 max-w-28 truncate" title="{{ $history['issue'] }}">{{ $history['issue'] }}</td>
                                <td class="px-3 py-2 max-w-36 truncate" title="{{ $history['to_account'] }}">{{ $history['to_account'] }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="text-[11px] {{ $history['status'] === '완료' ? 'text-green-700' : 'text-gray-600' }}">
                                        {{ $history['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-gray-400">기관 지원 보고서가 없습니다.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="mt-1 text-[11px] text-gray-400">기관 지원 행을 클릭하면 상세 내용을 볼 수 있습니다.</p>
        </div>

        <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-2">교사 지원 보고서</h4>
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="max-h-40 overflow-y-auto overflow-x-auto">
                    <table class="w-full text-xs whitespace-nowrap">
                        <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                        <tr class="text-gray-600">
                            <th class="px-3 py-2 text-left">지원일</th>
                            <th class="px-3 py-2 text-left">담당 코치</th>
                            <th class="px-3 py-2 text-left">교사명</th>
                            <th class="px-3 py-2 text-left">지원 타입</th>
                            <th class="px-3 py-2 text-center">상태</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($activeBucket['teacher'] ?? [] as $record)
                            <tr wire:key="teacher-support-{{ $activeTab }}-{{ $record['id'] }}-{{ $record['detail_key'] ?? 'none' }}"
                                @class([
                                    'transition-colors',
                                    'hover:bg-blue-50 cursor-pointer' => ! empty($record['detail_key']),
                                    'hover:bg-gray-50' => empty($record['detail_key']),
                                ])
                                @if(! empty($record['detail_key']))
                                    wire:click.stop="openTeacherSupportHistoryDetail('{{ $record['detail_key'] }}', {{ $record['teacher_id'] ?? 'null' }})"
                                @endif>
                                <td class="px-3 py-2 @if(!empty($record['detail_key'])) text-mochi-header underline @endif">{{ $record['date'] }}</td>
                                <td class="px-3 py-2">{{ $record['coach'] }}</td>
                                <td class="px-3 py-2">{{ $record['teacher'] }}</td>
                                <td class="px-3 py-2">{{ $record['type'] }}</td>
                                <td class="px-3 py-2 text-center">{{ $record['status'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-gray-400">교사 지원 보고서가 없습니다.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="mt-1 text-[11px] text-gray-400">교사 지원 행을 클릭하면 상세 내용을 볼 수 있습니다.</p>
        </div>
    </div>

    @if($unknownTotalCount > 0)
        <div class="mt-4 border border-amber-200 rounded-lg overflow-hidden">
            <button type="button"
                    wire:click="$toggle('showUnknownSupportSection')"
                    class="w-full flex items-center justify-between px-3 py-2 text-xs font-medium text-amber-800 bg-amber-50 hover:bg-amber-100 transition-colors cursor-pointer">
                <span>미분류 {{ $unknownTotalCount }}건 (기관 {{ $unknownInstitutionCount }} · 교사 {{ $unknownTeacherCount }})</span>
                <span>{{ $showUnknownSupportSection ? '접기' : '펼치기' }}</span>
            </button>

            @if($showUnknownSupportSection)
                <div class="p-3 space-y-3 bg-white">
                    @if($unknownInstitutionCount > 0)
                        <div>
                            <p class="text-xs font-semibold text-gray-600 mb-1">기관 지원</p>
                            <div class="max-h-32 overflow-y-auto border border-gray-100 rounded">
                                <table class="w-full text-xs">
                                    <tbody class="divide-y divide-gray-100">
                                    @foreach($unknownBucket['institution'] as $history)
                                        <tr wire:key="unknown-institution-{{ $history['id'] }}"
                                            wire:click="openSupportDetailModal({{ $history['id'] }})"
                                            class="hover:bg-blue-50 cursor-pointer">
                                            <td class="px-2 py-1.5">{{ $history['support_date'] }}</td>
                                            <td class="px-2 py-1.5">{{ $history['tr_name'] }}</td>
                                            <td class="px-2 py-1.5">{{ $history['support_type'] }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if($unknownTeacherCount > 0)
                        <div>
                            <p class="text-xs font-semibold text-gray-600 mb-1">교사 지원</p>
                            <div class="max-h-32 overflow-y-auto border border-gray-100 rounded">
                                <table class="w-full text-xs">
                                    <tbody class="divide-y divide-gray-100">
                                    @foreach($unknownBucket['teacher'] as $record)
                                        <tr wire:key="unknown-teacher-{{ $record['id'] }}"
                                            @class(['hover:bg-blue-50 cursor-pointer' => ! empty($record['detail_key'])])
                                            @if(! empty($record['detail_key']))
                                                wire:click.stop="openTeacherSupportHistoryDetail('{{ $record['detail_key'] }}', {{ $record['teacher_id'] ?? 'null' }})"
                                            @endif>
                                            <td class="px-2 py-1.5">{{ $record['date'] }}</td>
                                            <td class="px-2 py-1.5">{{ $record['coach'] }}</td>
                                            <td class="px-2 py-1.5">{{ $record['teacher'] }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
