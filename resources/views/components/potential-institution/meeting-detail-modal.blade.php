@props([
    'show' => false,
    'selectedMeeting' => null,
    'selectedTarget' => null,
    'meetingDetailEditMode' => false,
    'deletePolicy' => 'admin', // admin only
])

@php
    $isContract = (bool) ($selectedTarget['is_contract'] ?? false);
    $canManage = (bool) ($selectedTarget['can_manage'] ?? false);
    $canEdit = ! $isContract && $canManage && \Illuminate\Support\Facades\Gate::allows('managePotentialInstitutions');
    $showDelete = $deletePolicy === 'admin'
        && ! $meetingDetailEditMode
        && ! $isContract
        && \Illuminate\Support\Facades\Gate::allows('deletePotentialMeetingDetails');
@endphp

@if($show && $selectedMeeting)
    <div class="mochi-modal-overlay z-[60]" wire:click.self="closeMeetingDetailModal">
        <div class="mochi-modal-shell max-w-3xl h-[70vh] max-h-[70vh] flex flex-col" wire:click.stop>
            <x-admin.modal-header
                title="미팅/컨설팅 상세"
                :subtitle="($selectedMeeting['account_name'] ?? '-').' · '.($selectedMeeting['meeting_date'] ?? '-')"
                close-action="closeMeetingDetailModal"
            />

            @if ($isContract)
                <div class="px-6 py-2 bg-gray-50 border-b border-gray-100">
                    <p class="text-xs text-gray-500">계약 완료 기관은 미팅/컨설팅 이력을 수정할 수 없습니다.</p>
                </div>
            @elseif ($canEdit)
                <div class="flex items-center justify-between gap-2 px-6 py-2 bg-gray-50 border-b border-gray-100">
                    <p class="text-xs text-gray-500">
                        @if ($meetingDetailEditMode)
                            수정 후 저장하면 미팅/컨설팅 목록에 반영됩니다.
                        @else
                            조회·수정 전환으로 미팅/컨설팅 이력을 관리할 수 있습니다.
                        @endif
                    </p>
                    <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5 shadow-sm" role="group" aria-label="미팅/컨설팅 보기 모드">
                        <button type="button"
                                wire:click="cancelMeetingDetailEdit"
                                class="px-3 py-1 text-xs font-medium rounded-md transition-colors {{ $meetingDetailEditMode ? 'text-gray-600 hover:bg-gray-50' : 'bg-blue-600 text-white shadow-sm' }}"
                                @if (! $meetingDetailEditMode) aria-pressed="true" @endif>
                            조회
                        </button>
                        <button type="button"
                                wire:click="enterMeetingDetailEditMode"
                                class="px-3 py-1 text-xs font-medium rounded-md transition-colors {{ $meetingDetailEditMode ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}"
                                @if ($meetingDetailEditMode) aria-pressed="true" @endif>
                            수정
                        </button>
                    </div>
                </div>
            @endif

            <div class="px-6 py-5 flex-1 overflow-y-auto">
                @if (! $meetingDetailEditMode)
                    <div class="border border-gray-200 rounded-lg overflow-hidden mb-4">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관명</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['account_name'] ?? '-' }}</td>
                                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당자</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['account_manager'] ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">일자</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['meeting_date'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">시간</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">
                                        {{ $selectedMeeting['meeting_time'] ?? '-' }} ~ {{ $selectedMeeting['meeting_time_end'] ?? '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">컨설팅타입</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['consulting_type'] ?? '-' }}</td>
                                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">가능성</th>
                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedMeeting['possibility'] ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">미팅내용</h4>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 leading-6 whitespace-pre-wrap break-words">
                            {{ $selectedMeeting['description'] ?? '-' }}
                        </div>
                    </div>
                @else
                    <form wire:submit="saveMeetingDetailEdit" class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">기관명</label>
                                <input type="text" value="{{ $selectedMeeting['account_name'] ?? '-' }}" readonly
                                       class="w-full py-2 px-3 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">담당자</label>
                                <input type="text" wire:model="editMeetingAccountManager" maxlength="100"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('editMeetingAccountManager') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">미팅일 <span class="text-red-500">*</span></label>
                                <input type="date" wire:model="editMeetingDate"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('editMeetingDate') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">컨설팅타입 <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="editConsultingType" maxlength="100"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('editConsultingType') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">시작 시간</label>
                                <input type="time" wire:model="editMeetingTime"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('editMeetingTime') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">종료 시간</label>
                                <input type="time" wire:model="editMeetingTimeEnd"
                                       class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                @error('editMeetingTimeEnd') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">가능성</label>
                                <select wire:model="editPossibility"
                                        class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">선택</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                                @error('editPossibility') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">미팅내용</label>
                                <textarea wire:model="editDescription" rows="6" maxlength="2000"
                                          class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                @error('editDescription') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @error('meetingDetailEdit')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-3">
                            <button type="button"
                                    wire:click="cancelMeetingDetailEdit"
                                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                취소
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center rounded-lg border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                                저장
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            @if ($showDelete)
                <div class="flex flex-col gap-2 border-t border-gray-200 px-6 py-4 bg-gray-50/80 sm:flex-row sm:items-center sm:justify-between">
                    @error('deleteMeeting')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-end sm:ml-auto">
                        <button type="button"
                                wire:click="deleteMeetingDetail({{ (int) ($selectedMeeting['id'] ?? 0) }})"
                                wire:confirm="이 미팅/컨설팅 이력을 삭제할까요? 되돌릴 수 없습니다."
                                class="inline-flex items-center rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 cursor-pointer">
                            삭제
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
