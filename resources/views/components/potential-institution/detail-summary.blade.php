@props([
    'selectedTarget',
    'detailEditMode' => false,
    'showMeetingCount' => false,
    'contractEditableSeparately' => false,
    'editLS' => '',
    'editGSK' => '',
    'editGSE' => '',
])

@php
    $isContract = (bool) ($selectedTarget['is_contract'] ?? false);
    $canManage = (bool) ($selectedTarget['can_manage'] ?? false);
    $computedTotal = max(0, (int) ($editLS !== '' && is_numeric($editLS) ? $editLS : 0))
        + max(0, (int) ($editGSK !== '' && is_numeric($editGSK) ? $editGSK : 0))
        + max(0, (int) ($editGSE !== '' && is_numeric($editGSE) ? $editGSE : 0));
@endphp

<div class="border border-gray-200 rounded-lg overflow-hidden mb-4">
    @if ($isContract)
        <div class="px-3 py-2 bg-gray-50 border-b border-gray-100">
            <p class="text-xs text-gray-500">계약 완료 기관은 기관리스트에서 마스터 정보를 관리합니다.</p>
        </div>
    @elseif ($canManage)
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 bg-gray-50 border-b border-gray-100">
            <p class="text-xs text-gray-500">
                @if ($detailEditMode)
                    수정 후 저장하면 목록과 미팅 이력에 반영됩니다.
                @else
                    조회·수정 전환 후 기본 정보를 관리할 수 있습니다.
                @endif
            </p>
            <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5 shadow-sm" role="group" aria-label="기본 정보 보기 모드">
                <button type="button"
                        wire:click="cancelDetailEdit"
                        class="px-3 py-1 text-xs font-medium rounded-md transition-colors {{ $detailEditMode ? 'text-gray-600 hover:bg-gray-50' : 'bg-blue-600 text-white shadow-sm' }}"
                        @if (! $detailEditMode) aria-pressed="true" @endif>
                    조회
                </button>
                <button type="button"
                        wire:click="enterDetailEditMode"
                        class="px-3 py-1 text-xs font-medium rounded-md transition-colors {{ $detailEditMode ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}"
                        @if ($detailEditMode) aria-pressed="true" @endif>
                    수정
                </button>
            </div>
        </div>
    @endif

    @if (! $detailEditMode)
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">코드</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['account_code'] ?? '-' }}</td>
                    <th class="w-32 px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">담당자</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['account_manager'] ?? '-' }}</td>
                </tr>
                <tr>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">등록일</th>
                    <td class="px-3 py-2 font-medium text-gray-900" @if($contractEditableSeparately) colspan="3" @endif>{{ $selectedTarget['created_date'] ?? '-' }}</td>
                    @unless ($contractEditableSeparately)
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">계약여부</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $isContract ? '계약' : '미계약' }}</td>
                    @endunless
                </tr>
                <tr>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">신규구분</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['type'] ?? '-' }}</td>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">컨설팅타입</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['gubun'] ?? '-' }}</td>
                </tr>
                <tr>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">기관명</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['account_name'] ?? '-' }}</td>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">원장</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['director'] ?? '-' }}</td>
                </tr>
                <tr>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">연락처</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['phone'] ?? '-' }}</td>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">가능성</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['possibility'] ?? '-' }}</td>
                </tr>
                <tr>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">LS / GS(유) / GS(초)</th>
                    <td class="px-3 py-2 font-medium text-gray-900">
                        {{ $selectedTarget['ls'] ?? 0 }} / {{ $selectedTarget['gs_k'] ?? 0 }} / {{ $selectedTarget['gs_e'] ?? 0 }}
                    </td>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">합계</th>
                    <td class="px-3 py-2 font-semibold text-gray-900">{{ $selectedTarget['total'] ?? 0 }}</td>
                </tr>
                @if ($showMeetingCount)
                <tr>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">미팅횟수</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['meeting_count'] ?? 0 }}</td>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">소개경로</th>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['connected'] ?? '-' }}</td>
                </tr>
                @else
                <tr>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">소개경로</th>
                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['connected'] ?? '-' }}</td>
                </tr>
                @endif
                <tr>
                    <th class="px-3 py-2 bg-gray-50 text-left text-xs text-gray-500 font-medium">주소</th>
                    <td colspan="3" class="px-3 py-2 font-medium text-gray-900">{{ $selectedTarget['address'] ?? '-' }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <form wire:submit="saveDetailEdit" class="p-4 space-y-3 bg-white">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">담당자</label>
                    <input type="text" wire:model="editAccountManager"
                           class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    @error('editAccountManager') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">신규구분 <span class="text-red-500">*</span></label>
                    <select wire:model="editType"
                            class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">선택</option>
                        <option value="신규(24년)">신규(24년)</option>
                        <option value="신규(25년)">신규(25년)</option>
                        <option value="신규(26년)">신규(26년)</option>
                        <option value="신규(27년)">신규(27년)</option>
                        <option value="해지">해지</option>
                    </select>
                    @error('editType') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">컨설팅타입 <span class="text-red-500">*</span></label>
                    <input type="text" list="potential-detail-consulting-type-suggestions" wire:model="editGubun"
                           class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="직접 입력하거나 목록에서 선택" />
                    <datalist id="potential-detail-consulting-type-suggestions">
                        <option value="신규기관방문"></option>
                        <option value="신규(24년)"></option>
                        <option value="신규(25년)"></option>
                        <option value="신규(26년)"></option>
                        <option value="신규(27년)"></option>
                        <option value="해지방문"></option>
                    </datalist>
                    @error('editGubun') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">기관명 <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="editAccountName"
                           class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    @error('editAccountName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">원장</label>
                    <input type="text" wire:model="editDirector"
                           class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    @error('editDirector') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">연락처</label>
                    <input type="text" wire:model="editPhone"
                           class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    @error('editPhone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">가능성</label>
                    <select wire:model="editPossibility"
                            class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">선택</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                    @error('editPossibility') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">소개경로</label>
                    <input type="text" wire:model="editConnected"
                           class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    @error('editConnected') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 grid grid-cols-4 gap-3">
                    <div class="min-w-0">
                        <label class="block text-xs font-medium text-gray-600 mb-1">LittleSEED</label>
                        <input type="number" min="0" wire:model.live="editLS"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        @error('editLS') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-0">
                        <label class="block text-xs font-medium text-gray-600 mb-1">GrapeSEED(유)</label>
                        <input type="number" min="0" wire:model.live="editGSK"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        @error('editGSK') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-0">
                        <label class="block text-xs font-medium text-gray-600 mb-1">GrapeSEED(초)</label>
                        <input type="number" min="0" wire:model.live="editGSE"
                               class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        @error('editGSE') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-0">
                        <label class="block text-xs font-medium text-gray-600 mb-1">합계</label>
                        <p class="py-1.5 px-2 text-sm font-semibold text-gray-900">{{ $computedTotal }}</p>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">주소</label>
                    <input type="text" wire:model="editAddress"
                           class="w-full py-1.5 px-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    @error('editAddress') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            @error('detailEdit')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap items-center justify-end gap-2 pt-1 border-t border-gray-100">
                <button type="button" wire:click="cancelDetailEdit"
                        class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                    취소
                </button>
                <button type="submit"
                        class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                        wire:loading.attr="disabled"
                        wire:target="saveDetailEdit">
                    <span wire:loading.remove wire:target="saveDetailEdit">저장</span>
                    <span wire:loading wire:target="saveDetailEdit">저장 중…</span>
                </button>
            </div>
        </form>
    @endif
</div>
