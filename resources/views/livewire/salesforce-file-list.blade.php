<div class="mochi-page">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="mochi-filter-card">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="filterUser"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 사용자</option>
                @foreach($users as $user)
                    <option value="{{ $user }}">{{ $user }}</option>
                @endforeach
            </select>

            <div class="relative flex-1 min-w-52">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="파일명, 사용자, 생성일, 수정일 검색..."
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <select wire:model.live="filterSkCode"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">전체 SKcode</option>
                @foreach($skCodes as $skCode)
                    <option value="{{ $skCode }}">{{ $skCode }}</option>
                @endforeach
            </select>

            <a href="/supports"
               class="ml-auto px-4 py-2 text-sm font-medium border border-blue-200 text-blue-700 rounded-lg hover:bg-blue-50 transition-colors">
                업로드 하러 가기
            </a>
        </div>
    </div>

    <div class="mb-6">
        <div class="px-1 pb-2">
            <h2 class="text-sm font-semibold text-gray-800">레거시 Salesforce 파일 (`SF_Files`)</h2>
            <p class="text-xs text-gray-500 mt-0.5">파일명 기준으로 신규 계약서 저장소와 연결되면 미리보기/다운로드가 가능합니다.</p>
        </div>
        <div class="mochi-table-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="mochi-table-head">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">파일명</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">사용자</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">생성일</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">수정일</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 uppercase">다운로드수</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase">매칭</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase">파일 액션</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($sfFiles as $index => $doc)
                        @php
                            $linkedDocId = isset($sfLinkedDocIds[$doc->fileName]) ? (int) $sfLinkedDocIds[$doc->fileName] : null;
                            $matchType = $sfMatchTypes[$doc->fileName] ?? null;
                            $isFileAvailable = $linkedDocId ? (bool) ($fileAvailableByDocId[$linkedDocId] ?? false) : false;
                        @endphp
                        <tr wire:key="salesforce-file-{{ $doc->ID }}" class="hover:bg-blue-50/60 transition-colors">
                            <td class="px-3 py-2.5 text-xs text-gray-400">{{ $sfFiles->firstItem() + $index }}</td>
                            <td class="px-3 py-2.5 text-[12px] font-mono text-gray-700">{{ $doc->ID }}</td>
                            <td class="px-3 py-2.5 max-w-[520px] truncate text-gray-700 text-xs" title="{{ $doc->fileName }}">{{ $doc->fileName ?: '-' }}</td>
                            <td class="px-3 py-2.5 text-gray-600 text-xs">{{ $doc->User ?: '-' }}</td>
                            <td class="px-3 py-2.5 text-gray-600 text-xs">{{ $doc->created_Date ?: '-' }}</td>
                            <td class="px-3 py-2.5 text-gray-600 text-xs">{{ $doc->LastUpdate_Date ?: '-' }}</td>
                            <td class="px-3 py-2.5 text-right text-xs text-gray-500">{{ $doc->download_Cnt ?: '0' }}</td>
                            <td class="px-3 py-2.5 text-center">
                                @if($linkedDocId)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                        {{ $matchType === 'exact' ? 'bg-green-100 text-green-700' : ($matchType === 'normalized' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $matchType === 'exact' ? '정확' : ($matchType === 'normalized' ? '정규화' : '유사') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">
                                        미매칭
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center justify-center gap-1">
                                    @if($linkedDocId && $isFileAvailable)
                                        <a href="{{ route('contract-documents.preview', ['contractDocument' => $linkedDocId]) }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="px-2 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50 text-gray-700">
                                            미리보기
                                        </a>
                                        <a href="{{ route('contract-documents.download', ['contractDocument' => $linkedDocId]) }}"
                                           class="px-2 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50 text-gray-700">
                                            다운로드
                                        </a>
                                    @else
                                        <span class="px-2 py-1.5 text-xs border border-gray-200 rounded text-gray-400 cursor-not-allowed">미리보기</span>
                                        <span class="px-2 py-1.5 text-xs border border-gray-200 rounded text-gray-400 cursor-not-allowed">다운로드</span>
                                    @endif
                                </div>
                                @if($linkedDocId && ! $isFileAvailable)
                                    <p class="mt-1 text-[11px] text-amber-600 text-center">원본 파일 없음</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-16 text-center text-gray-400">
                                <p class="font-medium">SF_Files 테이블에 데이터가 없습니다.</p>
                                <p class="text-sm mt-1 text-gray-400">DB 연결/테이블 데이터를 확인해 주세요.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($sfFiles->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $sfFiles->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="mochi-table-card">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-800">신규 업로드 계약서 (`contract_documents`)</h2>
            <p class="text-xs text-gray-500 mt-0.5">현재 시스템에서 업로드한 파일 원본입니다. 미리보기/다운로드/삭제를 지원합니다.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="mochi-table-head">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">날짜</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">파일명</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">SKcode</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">기관명</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">담당자</th>
                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 uppercase">크기</th>
                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase">액션</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($contractDocuments as $index => $doc)
                    @php
                        $isFileAvailable = (bool) ($fileAvailableByDocId[$doc->id] ?? false);
                    @endphp
                    <tr wire:key="contract-doc-{{ $doc->id }}" class="hover:bg-blue-50/60 transition-colors">
                        <td class="px-3 py-2.5 text-xs text-gray-400">{{ $contractDocuments->firstItem() + $index }}</td>
                        <td class="px-3 py-2.5 text-gray-700 text-xs">
                            {{ $doc->document_date?->format('Y-m-d') ?? '-' }}
                            @if($doc->document_time)
                                <span class="text-gray-500">{{ substr((string) $doc->document_time, 0, 5) }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 max-w-[360px] truncate text-gray-700 text-xs" title="{{ $doc->original_filename }}">{{ $doc->original_filename }}</td>
                        <td class="px-3 py-2.5 text-[12px] font-mono text-gray-700">{{ $doc->sk_code }}</td>
                        <td class="px-3 py-2.5 max-w-[220px] truncate text-gray-700 text-xs" title="{{ $doc->account_name }}">{{ $doc->account_name }}</td>
                        <td class="px-3 py-2.5 text-gray-600 text-xs">{{ $doc->consultant ?: '-' }}</td>
                        <td class="px-3 py-2.5 text-right text-xs text-gray-500">
                            @if($doc->size_bytes)
                                {{ number_format($doc->size_bytes / 1024, 1) }} KB
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-center gap-1">
                                @if($isFileAvailable)
                                    <a href="{{ route('contract-documents.preview', ['contractDocument' => $doc->id]) }}"
                                       target="_blank"
                                       rel="noopener"
                                       class="px-2 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50 text-gray-700">
                                        미리보기
                                    </a>
                                    <a href="{{ route('contract-documents.download', ['contractDocument' => $doc->id]) }}"
                                       class="px-2 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50 text-gray-700">
                                        다운로드
                                    </a>
                                @else
                                    <span class="px-2 py-1.5 text-xs border border-gray-200 rounded text-gray-400 cursor-not-allowed">미리보기</span>
                                    <span class="px-2 py-1.5 text-xs border border-gray-200 rounded text-gray-400 cursor-not-allowed">다운로드</span>
                                @endif
                                <button type="button"
                                        wire:click="confirmDeleteContractDocument({{ $doc->id }})"
                                        class="px-2 py-1.5 text-xs border border-rose-200 text-rose-700 rounded hover:bg-rose-50">
                                    삭제
                                </button>
                            </div>
                            @unless($isFileAvailable)
                                <p class="mt-1 text-[11px] text-amber-600 text-center">원본 파일 없음</p>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400">
                            <p class="font-medium">신규 계약서 파일 데이터가 없습니다.</p>
                            <p class="text-sm mt-1 text-gray-400">/supports 페이지에서 업로드 후 확인해 주세요.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($contractDocuments->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $contractDocuments->links() }}
            </div>
        @endif
    </div>

    @if($showDeleteModal)
        <div class="mochi-modal-overlay z-[60]" wire:click.self="closeDeleteModal">
            <div class="mochi-modal-shell max-w-md" wire:click.stop>
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">파일 삭제 확인</h3>
                    <p class="text-sm text-gray-500 mt-1 break-all">{{ $deleteTargetFileName }}</p>
                </div>
                <div class="px-6 py-5 text-sm text-gray-600">
                    선택한 계약서 파일을 삭제하시겠습니까? 삭제 후에는 복구할 수 없습니다.
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-2">
                    <button type="button"
                            wire:click="closeDeleteModal"
                            class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100">
                        취소
                    </button>
                    <button type="button"
                            wire:click="deleteContractDocument"
                            class="px-4 py-2 text-sm text-white bg-rose-600 hover:bg-rose-700 rounded-lg"
                            wire:loading.attr="disabled"
                            wire:target="deleteContractDocument">
                        <span wire:loading.remove wire:target="deleteContractDocument">삭제</span>
                        <span wire:loading wire:target="deleteContractDocument">삭제 중...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div wire:loading.delay
         wire:target="confirmDeleteContractDocument,deleteContractDocument,gotoPage,nextPage,previousPage"
         class="fixed bottom-6 right-6 z-50">
        <div class="bg-white rounded-xl px-4 py-3 shadow-lg border border-gray-200 flex items-center gap-2 text-sm text-gray-700">
            <svg class="animate-spin w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            처리 중...
        </div>
    </div>
</div>
