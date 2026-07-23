<div class="mochi-page">

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2" data-mochi-flash-dismiss="3000" role="status">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-sm flex items-center gap-2" data-mochi-flash-dismiss="3000" role="status">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-stretch gap-3 lg:items-center lg:flex-nowrap">
            <select wire:model.live="filterYear"
                    class="shrink-0 py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header max-lg:flex-1">
                <option value="">전체 년도</option>
                @foreach($years as $year)
                    <option value="{{ $year }}">{{ $year }}년</option>
                @endforeach
            </select>

            <div class="relative min-w-0 flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="기관명, SK코드, 교사명, 이슈 내용 검색..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-mochi-header"/>
            </div>

            <span class="w-full lg:w-auto shrink-0 whitespace-nowrap text-sm text-gray-500">
                이슈 <span class="font-semibold text-blue-600">{{ $issueTotal }}</span>건
                · 표시 <span class="font-semibold text-blue-600">{{ $groups->total() }}</span>줄
            </span>

            <div class="w-full lg:w-auto lg:ml-auto flex flex-wrap shrink-0 items-center justify-end gap-2 whitespace-nowrap">
                <button type="button"
                        wire:click="$toggle('filterUrgentOnly')"
                        @class([
                            'inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm transition',
                            'border border-red-400 bg-red-50 text-red-800 hover:bg-red-100' => $filterUrgentOnly,
                            'border border-red-300 bg-white text-red-700 hover:bg-red-50' => ! $filterUrgentOnly,
                        ])>
                    긴급 이슈만 보기
                </button>

                <a href="{{ \App\Support\TeamMenuContext::route('supports.create', ['report_mode' => 'issue'], null, 'cs') }}"
                   class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg border border-blue-300 bg-white px-3 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    기관 이슈 작성
                </a>
            </div>
        </div>
    </div>

    <div class="mochi-table-card">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-sm border-collapse">
                <thead class="mochi-table-head">
                <tr class="text-gray-700">
                    <th class="px-2 py-2 text-center text-xs font-semibold border border-gray-300">SK코드</th>
                    <th class="px-2 py-2 text-center text-xs font-semibold border border-gray-300">기관명</th>
                    <th class="px-2 py-2 text-left text-xs font-semibold border border-gray-300">관련 교사</th>
                    <th class="px-2 py-2 text-center text-xs font-semibold border border-gray-300">이슈</th>
                    <th class="px-2 py-2 text-center text-xs font-semibold border border-gray-300">긴급</th>
                    <th class="px-2 py-2 text-left text-xs font-semibold border border-gray-300">작성자</th>
                    <th class="px-2 py-2 text-left text-xs font-semibold border border-gray-300">최근 발생일</th>
                    <th class="px-2 py-2 text-left text-xs font-semibold border border-gray-300">최근 이슈</th>
                    <th class="px-2 py-2 text-center text-xs font-semibold border border-gray-300">상태</th>
                </tr>
                </thead>

                <tbody>
                @forelse($groups as $index => $group)
                    @php
                        /** @var \App\Models\SupportRecord $latest */
                        $latest = $group['latest'];
                        $institutionSpan = (int) ($institutionRowspans[$index] ?? 0);
                    @endphp
                    <tr wire:key="issue-group-{{ $group['group_key'] }}"
                        class="{{ $group['urgent_count'] > 0 ? 'bg-red-50/40' : '' }}">

                        @if($institutionSpan > 0)
                            <td class="px-2 py-2.5 align-middle text-center border border-gray-300"
                                rowspan="{{ $institutionSpan }}">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 font-mono">
                                    {{ $group['sk_code'] !== '' ? $group['sk_code'] : '-' }}
                                </span>
                            </td>
                            <td class="px-2 py-2.5 align-middle text-center font-medium text-gray-900 border border-gray-300 max-w-40"
                                rowspan="{{ $institutionSpan }}"
                                title="{{ $group['account_name'] }}">
                                {{ $group['account_name'] !== '' ? $group['account_name'] : '-' }}
                            </td>
                        @endif

                        <td class="px-2 py-2.5 align-middle text-xs border border-gray-300">
                            <button type="button"
                                    wire:click="openGroupDetail(@js($group['group_key']))"
                                    class="cursor-pointer text-left underline decoration-mochi-header/40 hover:decoration-mochi-header hover:bg-blue-50 rounded px-1 py-0.5 -mx-1 transition-colors
                                           {{ $group['is_institution_common'] ? 'text-amber-700 hover:text-amber-800' : 'text-mochi-header hover:text-mochi-header/80' }}">
                                {{ $group['teacher_label'] }}
                            </button>
                        </td>

                        <td class="px-2 py-2.5 text-center border border-gray-300 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                {{ $group['issue_count'] }}건
                            </span>
                        </td>

                        <td class="px-2 py-2.5 text-center border border-gray-300 whitespace-nowrap">
                            @if($group['urgent_count'] > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">
                                    긴급 {{ $group['urgent_count'] }}
                                </span>
                            @else
                                <span class="text-xs text-gray-300">-</span>
                            @endif
                        </td>

                        <td class="px-2 py-2.5 text-gray-600 text-xs border border-gray-300 whitespace-nowrap">
                            {{ $latest->TR_Name ?? '-' }}
                        </td>

                        <td class="px-2 py-2.5 text-gray-700 border border-gray-300 whitespace-nowrap">
                            {{ $latest->Support_Date?->format('Y-m-d') ?? '-' }}
                            @php
                                $latestMeetTime = $this->formatMeetTimeForDisplay($latest->Meet_Time);
                            @endphp
                            @if($latestMeetTime !== '')
                                <span class="text-xs text-gray-500">{{ $latestMeetTime }}</span>
                            @endif
                        </td>

                        <td class="px-2 py-2.5 text-gray-600 text-xs border border-gray-300 max-w-64 truncate" title="{{ $latest->Issue }}">
                            {{ $latest->Issue ?? '-' }}
                        </td>

                        <td class="px-2 py-2.5 text-center border border-gray-300 whitespace-nowrap">
                            @if($latest->isCompleted())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">완료</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">진행중</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-10 text-center text-gray-400 text-sm border border-gray-300">
                            등록된 기관 이슈가 없습니다.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($groups->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $groups->links() }}
            </div>
        @endif
    </div>

    @if($showDetailModal && $selectedGroup)
        <div class="mochi-modal-overlay" wire:click.self="closeDetailModal">
            <div class="mochi-modal-shell max-w-2xl flex flex-col max-h-[90vh]" wire:click.stop>
                <x-admin.modal-header
                    title="기관 이슈 상세"
                    :subtitle="$selectedGroup['teacher_label'].' · 이슈 '.$selectedGroup['issue_count'].'건'"
                    close-action="closeDetailModal"
                />

                <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-medium text-gray-500 mb-1">기관명</p>
                            <p class="text-sm text-gray-900 font-medium">
                                {{ $selectedGroup['account_name'] !== '' ? $selectedGroup['account_name'] : '-' }}
                            </p>
                            @if($selectedGroup['sk_code'] !== '')
                                <p class="mt-1 text-xs text-blue-600">SK: {{ $selectedGroup['sk_code'] }}</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 mb-1">관련 교사</p>
                            <p class="text-sm {{ $selectedGroup['is_institution_common'] ? 'text-amber-700' : 'text-gray-900' }}">
                                {{ $selectedGroup['teacher_label'] }}
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            이슈 목록 (최신순) · 클릭하여 펼치기
                        </p>

                        @foreach($selectedGroup['issues'] as $issue)
                            @php
                                $isExpanded = ((int) $expandedIssueId) === ((int) $issue['id']);
                                $previewSource = preg_replace('/\s+/u', ' ', (string) $issue['issue']);
                                $preview = \Illuminate\Support\Str::limit(trim((string) $previewSource), 72);
                            @endphp
                            <div wire:key="issue-detail-{{ $issue['id'] }}"
                                 class="rounded-xl border {{ $issue['is_urgent'] ? 'border-red-200 bg-red-50/40' : 'border-gray-200 bg-white' }} overflow-hidden">
                                <button type="button"
                                        wire:click="toggleExpandedIssue({{ (int) $issue['id'] }})"
                                        class="w-full px-3.5 py-2.5 text-left hover:bg-gray-50/80 transition-colors">
                                    <div class="flex items-start gap-2">
                                        <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                        </svg>
                                        <div class="min-w-0 flex-1 space-y-1">
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 leading-5">
                                                <span class="font-medium text-gray-800">
                                                    {{ $issue['support_date'] !== '' ? $issue['support_date'] : '-' }}
                                                    @if($issue['meet_time'] !== '')
                                                        {{ $issue['meet_time'] }}
                                                    @endif
                                                </span>
                                                <span>·</span>
                                                <span>작성자 {{ $issue['tr_name'] !== '' ? $issue['tr_name'] : '-' }}</span>
                                                @if($issue['is_urgent'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">긴급</span>
                                                @endif
                                                @if($issue['is_completed'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">완료</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">진행중</span>
                                                @endif
                                            </div>
                                            @if(! $isExpanded)
                                                <p class="text-sm text-gray-600 truncate">{{ $preview !== '' ? $preview : '-' }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </button>

                                @if($isExpanded)
                                    <div class="border-t border-gray-100 px-3.5 py-2.5 space-y-2 bg-white/70">
                                        <div class="text-sm text-gray-800 whitespace-pre-wrap leading-6">{{ $issue['issue'] !== '' ? $issue['issue'] : '-' }}</div>
                                        @if($issue['to_account'] !== '')
                                            <div class="border-t border-gray-100 pt-1.5">
                                                <p class="text-xs font-medium text-gray-500 mb-0.5">처리 내역</p>
                                                <p class="text-sm text-gray-700 whitespace-pre-wrap leading-6">{{ $issue['to_account'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end flex-shrink-0 rounded-b-2xl">
                    <button type="button"
                            wire:click="closeDetailModal"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                        닫기
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
