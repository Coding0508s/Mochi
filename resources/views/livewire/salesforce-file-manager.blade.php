<div class="mochi-page space-y-4">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-xl border border-blue-100 bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3">
        <h2 class="text-sm font-semibold text-blue-900">Salesforce 파일 관리</h2>
        <p class="mt-1 text-xs text-blue-700">
            좌측에서 기관 또는 미분류 파일을 선택하면 우측에서 파일 검색/미리보기/다운로드를 처리할 수 있습니다.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="xl:col-span-5 rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-3">
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1">
                    <button type="button"
                            wire:click="switchMasterTab('accounts')"
                            class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors {{ $masterTab === 'accounts' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600 hover:text-gray-800' }}">
                        기관 목록
                    </button>
                    <button type="button"
                            wire:click="switchMasterTab('unlinked')"
                            class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors {{ $masterTab === 'unlinked' ? 'bg-white text-amber-700 shadow-sm' : 'text-gray-600 hover:text-gray-800' }}">
                        미분류 파일
                    </button>
                </div>
            </div>

            <div class="p-4">
                <div class="relative">
                    <input type="text"
                           wire:model.live.debounce.300ms="masterSearch"
                           placeholder="{{ $masterTab === 'accounts' ? '기관명, account_ID, GSKR_Contract__c 검색' : '파일명, 사용자, 상태 검색' }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                </div>
                <div class="mt-2 text-[11px] text-gray-500 font-medium">
                    총 {{ number_format($masterTab === 'accounts' ? $accountRows->total() : $unlinkedRows->total()) }}개의 {{ $masterTab === 'accounts' ? '기관' : '미분류 파일' }}
                </div>
            </div>

            @if($masterTab === 'accounts')
                <div class="overflow-y-auto overflow-x-auto max-h-[650px] relative border-t border-gray-100">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 sticky top-0 shadow-sm z-10">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">account_ID</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">기관명</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">GSKR_Contract__c</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">GSKR_Gts_Type__c</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($accountRows as $index => $account)
                            <tr wire:key="account-{{ $account->ID }}"
                                class="cursor-pointer transition-colors hover:bg-blue-50 {{ (int) $selectedAccountId === (int) $account->ID ? 'bg-blue-50/80' : '' }}"
                                wire:click="selectAccount({{ (int) $account->ID }})">
                                <td class="px-3 py-2 text-xs text-gray-400">{{ ($accountRows->firstItem() ?? 1) + $index }}</td>
                                <td class="max-w-[180px] truncate px-3 py-2 font-mono text-xs text-gray-700" title="{{ $account->account_ID }}">{{ $account->account_ID ?: '-' }}</td>
                                <td class="max-w-[190px] truncate px-3 py-2 text-xs text-gray-700" title="{{ $account->Name }}">{{ $account->Name ?: '-' }}</td>
                                <td class="max-w-[180px] truncate px-3 py-2 font-mono text-xs text-gray-700" title="{{ $account->GSKR_Contract__c }}">{{ $account->GSKR_Contract__c ?: '-' }}</td>
                                <td class="max-w-[180px] truncate px-3 py-2 text-xs text-gray-700" title="{{ $account->GSKR_Gts_Type__c }}">{{ $account->GSKR_Gts_Type__c ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">표시할 기관 데이터가 없습니다.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                    @if($accountRows->hasMorePages())
                        <div x-intersect="$wire.loadMoreMaster()" class="h-4 flex items-center justify-center">
                            <span class="text-xs text-gray-400">불러오는 중...</span>
                        </div>
                    @endif
                </div>
            @else
                <div class="overflow-y-auto overflow-x-auto max-h-[650px] relative border-t border-gray-100">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 sticky top-0 shadow-sm z-10">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">상태</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">파일명</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($unlinkedRows as $index => $row)
                            <tr wire:key="unlinked-{{ $row['id'] }}"
                                class="cursor-pointer transition-colors hover:bg-amber-50 {{ (int) $selectedUnlinkedSfId === (int) $row['id'] ? 'bg-amber-50/80' : '' }}"
                                wire:click="selectUnlinkedSfFile({{ (int) $row['id'] }})">
                                <td class="px-3 py-2 text-xs text-gray-400">{{ ($unlinkedRows->firstItem() ?? 1) + $index }}</td>
                                <td class="px-3 py-2">
                                    @if(($row['status'] ?? '') === 'parse_failed')
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">ID 파싱 실패</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">계정 없음</span>
                                    @endif
                                </td>
                                <td class="max-w-[260px] truncate px-3 py-2 text-xs text-gray-700" title="{{ $row['file_name'] ?? '' }}">{{ $row['file_name'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-10 text-center text-sm text-gray-400">미분류 파일이 없습니다.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                    @if($unlinkedRows->hasMorePages())
                        <div x-intersect="$wire.loadMoreMaster()" class="h-4 flex items-center justify-center">
                            <span class="text-xs text-gray-400">불러오는 중...</span>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="xl:col-span-7 rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-3">
                @if($masterTab === 'accounts')
                    @if($selectedAccount)
                        <h3 class="text-sm font-semibold text-gray-800">{{ $selectedAccount->Name ?: '기관명 없음' }}</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            account_ID: <span class="font-mono">{{ $selectedAccount->account_ID ?: '-' }}</span>
                            · GSKR_Contract__c: <span class="font-mono">{{ $selectedAccount->GSKR_Contract__c ?: '-' }}</span>
                        </p>
                    @else
                        <h3 class="text-sm font-semibold text-gray-800">기관을 선택해 주세요</h3>
                    @endif
                @else
                    @if($selectedUnlinked)
                        <h3 class="text-sm font-semibold text-gray-800">미분류 SF 파일 상세</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            레코드 ID: <span class="font-mono">{{ $selectedUnlinked->ID }}</span>
                        </p>
                    @else
                        <h3 class="text-sm font-semibold text-gray-800">미분류 파일을 선택해 주세요</h3>
                    @endif
                @endif
            </div>

            <div class="p-4">
                <input type="text"
                       wire:model.live.debounce.300ms="detailSearch"
                       placeholder="파일명, 사용자, 계약ID, 담당자 검색"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">No</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">출처</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">파일명</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">사용자</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">생성일</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">담당자</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase">상태</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($detailRows as $index => $row)
                        @php
                            $docId = isset($row['contract_document_id']) ? (int) $row['contract_document_id'] : null;
                            $isFileAvailable = $docId ? (bool) ($fileAvailableByDocId[$docId] ?? false) : false;
                        @endphp
                        <tr wire:key="{{ $row['row_key'] ?? 'detail-'.$index }}" class="hover:bg-blue-50/40 transition-colors">
                            <td class="px-3 py-2 text-xs text-gray-400">{{ ($detailRows->firstItem() ?? 1) + $index }}</td>
                            <td class="px-3 py-2 text-xs">
                                @if(($row['source'] ?? '') === 'sf_file')
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-700">SF 원본</span>
                                @elseif(($row['source'] ?? '') === 'contract_only')
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-medium text-purple-700">내부 업로드</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">미분류 SF</span>
                                @endif
                            </td>
                            <td class="max-w-[300px] truncate px-3 py-2 text-xs text-gray-700" title="{{ $row['file_name'] ?? '' }}">{{ $row['file_name'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-700">{{ ($row['user'] ?? '') !== '' ? $row['user'] : '-' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-700">{{ ($row['created_date'] ?? '') !== '' ? $row['created_date'] : '-' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-700">{{ ($row['consultant'] ?? '') !== '' ? $row['consultant'] : '-' }}</td>
                            <td class="px-3 py-2 text-center">
                                @if($docId && $isFileAvailable)
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">다운로드 가능</span>
                                @elseif($docId)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">원본 파일 없음</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">파일 없음</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <div class="inline-flex items-center gap-1">
                                    @if($docId && $isFileAvailable)
                                        <button type="button"
                                                wire:click="openPreviewModal({{ $docId }})"
                                                class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                                            미리보기
                                        </button>
                                        <a href="{{ route('contract-documents.download', ['contractDocument' => $docId]) }}"
                                           class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                                            다운로드
                                        </a>
                                    @elseif($docId)
                                        <span class="cursor-not-allowed rounded border border-gray-200 px-2 py-1 text-xs text-gray-400">미리보기 불가</span>
                                        <span class="cursor-not-allowed rounded border border-gray-200 px-2 py-1 text-xs text-gray-400">다운로드 불가</span>
                                    @else
                                        <span class="cursor-not-allowed rounded border border-gray-200 px-2 py-1 text-xs text-gray-400">미리보기 불가</span>
                                        <span class="cursor-not-allowed rounded border border-gray-200 px-2 py-1 text-xs text-gray-400">다운로드 불가</span>
                                    @endif

                                    @if($docId)
                                        <button type="button"
                                                wire:click="openDocumentEditModal({{ $docId }})"
                                                class="rounded border border-blue-200 px-2 py-1 text-xs text-blue-700 hover:bg-blue-50">
                                            수정
                                        </button>
                                        <button type="button"
                                                wire:click="deleteDocument({{ $docId }})"
                                                onclick="return confirm('선택한 파일을 삭제하시겠습니까?')"
                                                class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">
                                            삭제
                                        </button>
                                    @else
                                        <span class="cursor-not-allowed rounded border border-gray-200 px-2 py-1 text-xs text-gray-400">수정 불가</span>
                                        <span class="cursor-not-allowed rounded border border-gray-200 px-2 py-1 text-xs text-gray-400">삭제 불가</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">
                                선택한 조건에서 표시할 파일이 없습니다.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($detailRows->hasPages())
                <div class="border-t border-gray-100 px-3 py-2">
                    {{ $detailRows->links() }}
                </div>
            @endif
        </section>
    </div>

    @if($showPreviewModal && $previewDocId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             wire:click.self="closePreviewModal"
             wire:keydown.escape.window="closePreviewModal">
            <div class="flex h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">
                            {{ $previewFileName !== '' ? $previewFileName : '파일 미리보기' }}
                        </p>
                        <p class="text-xs text-gray-500">브라우저에서 렌더링이 안 되는 형식은 다운로드를 이용해 주세요.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('contract-documents.download', ['contractDocument' => $previewDocId]) }}"
                           class="rounded border border-gray-300 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                            다운로드
                        </a>
                        <button type="button"
                                wire:click="closePreviewModal"
                                class="rounded border border-gray-300 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                            닫기
                        </button>
                    </div>
                </div>
                <iframe src="{{ route('contract-documents.preview', ['contractDocument' => $previewDocId]) }}"
                        class="h-full w-full border-0"
                        title="파일 미리보기"></iframe>
            </div>
        </div>
    @endif

    @if($showEditModal && $editingDocumentId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             wire:click.self="closeDocumentEditModal"
             wire:keydown.escape.window="closeDocumentEditModal">
            <div class="w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">등록 파일 수정</p>
                        <p class="text-xs text-gray-500">메타데이터 변경 및 실제 파일 교체를 지원합니다.</p>
                    </div>
                    <button type="button"
                            wire:click="closeDocumentEditModal"
                            class="rounded border border-gray-300 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                        닫기
                    </button>
                </div>

                <form wire:submit="saveDocumentEdit" class="space-y-4 px-4 py-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">SK 코드</label>
                            <input type="text" wire:model="editSkCode"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            @error('editSkCode') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">기관명</label>
                            <input type="text" wire:model="editAccountName"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">변경 기관명</label>
                            <input type="text" wire:model="editChangedAccountName"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">사업자번호</label>
                            <input type="text" wire:model="editBusinessNumber"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">문서 날짜</label>
                            <input type="date" wire:model="editDocumentDate"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            @error('editDocumentDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">문서 시간</label>
                            <input type="time" wire:model="editDocumentTime"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            @error('editDocumentTime') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600">담당자</label>
                            <input type="text" wire:model="editConsultant"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600">파일명</label>
                            <input type="text" wire:model="editOriginalFilename"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            @error('editOriginalFilename') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="rounded-lg border border-dashed border-gray-300 p-3">
                        <label class="mb-2 block text-xs font-medium text-gray-600">실제 파일 교체 (선택)</label>
                        <input type="file" wire:model="editReplacementUpload"
                               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/*"
                               class="w-full text-sm">
                        <div wire:loading wire:target="editReplacementUpload" class="mt-2 text-xs text-blue-600">파일 처리 중…</div>
                        @error('editReplacementUpload') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        <p class="mt-2 text-xs text-gray-500">
                            @if($editReplacementUpload)
                                교체 예정 파일: {{ $editReplacementUpload->getClientOriginalName() }}
                            @else
                                새 파일을 선택하지 않으면 기존 실제 파일은 유지됩니다.
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-3">
                        <button type="button"
                                wire:click="closeDocumentEditModal"
                                class="rounded border border-gray-300 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                            취소
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="saveDocumentEdit,editReplacementUpload"
                                class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveDocumentEdit">수정 저장</span>
                            <span wire:loading wire:target="saveDocumentEdit">저장 중...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
