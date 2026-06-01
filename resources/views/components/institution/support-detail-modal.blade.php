@props([
    'show' => false,
    'selectedSupportRecord' => null,
    'selectedInstitution' => null,
    'supportDetailEditMode' => false,
])

@php
    $isTerminated = str_contains((string) ($selectedInstitution['customer_type'] ?? ''), '해지');
    $canEdit = ! $isTerminated && (bool) ($selectedSupportRecord['can_edit'] ?? false);
    $showDelete = ! $supportDetailEditMode
        && ! $isTerminated
        && \Illuminate\Support\Facades\Gate::allows('deleteSupportRecords');
@endphp

@if($show && $selectedSupportRecord)
    <div class="mochi-modal-overlay z-[60]"
         wire:click.self="closeSupportDetailModal">
        <div class="mochi-modal-shell max-w-2xl max-h-[78vh] z-[61] flex flex-col"
             wire:click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">지원 내역 상세</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $selectedSupportRecord['support_date'] ?? '-' }} {{ $selectedSupportRecord['support_time'] ?? '-' }}
                    </p>
                </div>
                <button type="button" wire:click="closeSupportDetailModal" class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            @if ($isTerminated)
                <div class="px-6 py-2 bg-gray-50 border-b border-gray-100">
                    <p class="text-xs text-gray-500">해지 기관의 지원 내역은 수정·삭제할 수 없습니다.</p>
                </div>
            @elseif ($canEdit)
                <div class="flex items-center justify-between gap-2 px-6 py-2 bg-gray-50 border-b border-gray-100">
                    <p class="text-xs text-gray-500">
                        @if ($supportDetailEditMode)
                            수정 후 저장하면 지원/소통 이력 목록에 반영됩니다.
                        @else
                            조회·수정 전환으로 지원 내역을 관리할 수 있습니다.
                        @endif
                    </p>
                    <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5 shadow-sm" role="group" aria-label="지원 내역 보기 모드">
                        <button type="button"
                                wire:click="cancelSupportDetailEdit"
                                class="px-3 py-1 text-xs font-medium rounded-md transition-colors {{ $supportDetailEditMode ? 'text-gray-600 hover:bg-gray-50' : 'bg-blue-600 text-white shadow-sm' }}"
                                @if (! $supportDetailEditMode) aria-pressed="true" @endif>
                            조회
                        </button>
                        <button type="button"
                                wire:click="enterSupportDetailEditMode"
                                class="px-3 py-1 text-xs font-medium rounded-md transition-colors {{ $supportDetailEditMode ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}"
                                @if ($supportDetailEditMode) aria-pressed="true" @endif>
                            수정
                        </button>
                    </div>
                </div>
            @endif

            <div class="px-6 py-4 flex-1 overflow-y-auto">
                @if (! $supportDetailEditMode)
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-xs text-gray-500 mb-1">담당자(Coach)</div>
                            <div class="font-medium text-gray-900">{{ $selectedSupportRecord['tr_name'] ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 mb-1">지원방법</div>
                            <div class="font-medium text-gray-900">{{ $selectedSupportRecord['support_type'] ?? '-' }}</div>
                        </div>

                        <div class="col-span-2">
                            <div class="text-xs text-gray-500 mb-1">참석자</div>
                            <div class="font-medium text-gray-900">{{ $selectedSupportRecord['target'] ?? '-' }}</div>
                        </div>

                        <div class="col-span-2">
                            <div class="text-xs text-gray-500 mb-1">이슈</div>
                            <div class="text-left font-medium text-gray-900 whitespace-pre-wrap break-words">{{ $selectedSupportRecord['issue'] ?? '-' }}</div>
                        </div>

                        <div class="col-span-2">
                            <div class="text-xs text-gray-500 mb-1">기관과의 소통내용</div>
                            <div class="text-left font-medium text-gray-900 whitespace-pre-wrap break-words">{{ $selectedSupportRecord['to_account'] ?? '-' }}</div>
                        </div>

                        <div class="col-span-2">
                            <div class="text-xs text-gray-500 mb-1">본사/타 부서 공유 내용</div>
                            <div class="text-left font-medium text-gray-900 whitespace-pre-wrap break-words">{{ $selectedSupportRecord['to_depart'] ?? '-' }}</div>
                        </div>

                        <div class="col-span-2">
                            <div class="text-xs text-gray-500 mb-1">기타</div>
                            <div class="text-left font-medium text-gray-900 whitespace-pre-wrap break-words">{{ $selectedSupportRecord['others'] ?? '-' }}</div>
                        </div>

                        <div>
                            <div class="text-xs text-gray-500 mb-1">상태</div>
                            <div class="font-medium text-gray-900">{{ $selectedSupportRecord['status'] ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 mb-1">완료일</div>
                            <div class="font-medium text-gray-900">{{ $selectedSupportRecord['completed_date'] ?? '-' }}</div>
                        </div>
                    </div>
                @else
                    <form wire:submit="saveSupportDetailEdit" class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">담당자(Coach)</label>
                                <input type="text" value="{{ $selectedSupportRecord['tr_name'] ?? '-' }}" readonly
                                       class="w-full py-2 px-3 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">지원방법 <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="editSupportType" maxlength="100"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('editSupportType') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">지원일 <span class="text-red-500">*</span></label>
                                <input type="date" wire:model="editSupportDate"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('editSupportDate') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">지원 시간 <span class="text-red-500">*</span></label>
                                <input type="time" wire:model="editSupportTime"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('editSupportTime') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">참석자</label>
                                <input type="text" wire:model="editTarget" maxlength="255"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('editTarget') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">이슈</label>
                                <textarea wire:model="editIssue" rows="3" maxlength="5000"
                                          class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                @error('editIssue') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">기관과의 소통내용</label>
                                <textarea wire:model="editToAccount" rows="3" maxlength="5000"
                                          class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                @error('editToAccount') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">본사/타 부서 공유 내용</label>
                                <textarea wire:model="editToDepart" rows="3" maxlength="5000"
                                          class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                @error('editToDepart') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">기타</label>
                                <textarea wire:model="editOthers" rows="3" maxlength="5000"
                                          class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                @error('editOthers') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" wire:model="editCompleted"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    완료 처리
                                </label>
                                @error('editCompleted') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @error('supportDetailEdit')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-3">
                            <button type="button"
                                    wire:click="cancelSupportDetailEdit"
                                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer">
                                취소
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center rounded-lg border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 cursor-pointer">
                                저장
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            @if ($showDelete)
                <div class="flex flex-col gap-2 border-t border-gray-200 px-6 py-3 bg-gray-50/80 sm:flex-row sm:items-center sm:justify-between flex-shrink-0">
                    @error('supportDetailEdit')
                        <p class="text-sm text-red-600 sm:mr-auto">{{ $message }}</p>
                    @enderror
                    <div class="flex items-center justify-end gap-2 sm:ml-auto">
                        <button type="button"
                                wire:click="closeSupportDetailModal"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                            닫기
                        </button>
                        <button type="button"
                                wire:click="deleteSupportDetail"
                                wire:confirm="이 지원 내역을 삭제할까요? 되돌릴 수 없습니다."
                                class="inline-flex items-center rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 cursor-pointer">
                            삭제
                        </button>
                    </div>
                </div>
            @else
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-right flex-shrink-0">
                    <button type="button"
                            wire:click="closeSupportDetailModal"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                        닫기
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
