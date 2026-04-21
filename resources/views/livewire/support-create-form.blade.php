<div class="mochi-page">
    <div class="mb-4">
        <a href="/supports"
           class="inline-flex items-center gap-2 px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            목록으로 돌아가기
        </a>
    </div>

    <div class="mochi-table-card max-w-5xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
                <h2 class="text-base font-semibold text-gray-900">CO 기관지원보고서 작성</h2>
                <p class="text-xs text-gray-400 mt-0.5">기관 지원 보고서</p>
            </div>
        </div>

        <form wire:submit="save">
            @php
                $institutionSelected = filled($formSkCode) || filled($formPotentialTargetId);
                $sfUploadEnabled = $institutionSelected && filled($formSkCode);
            @endphp
            <div class="px-6 py-4 space-y-4">

                {{-- ── 1행: 기관명 · 가능성(잠재기관) · CO명 ──────────── --}}
                <div class="flex items-start gap-3">

                    {{-- 기관명 --}}
                    <div class="flex-1 min-w-0">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            기관명 <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               wire:model.live.debounce.200ms="formInstitutionKeyword"
                               placeholder="기관명을 입력하세요 (예: 분당)"
                               class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                      {{ $errors->has('formSkCode') ? 'border-red-400' : 'border-gray-300' }}" />

                        @if(filled($formInstitutionKeyword) && blank($formSkCode) && $institutionSuggestions->isNotEmpty())
                            <div class="mt-2 max-h-44 overflow-auto border border-gray-200 rounded-lg bg-white shadow-sm">
                                @foreach($institutionSuggestions as $inst)
                                    <button type="button"
                                            wire:click="selectInstitution('{{ $inst->SKcode }}', {{ $inst->is_potential ? 'true' : 'false' }}, {{ $inst->potential_target_id ?? 'null' }})"
                                            class="w-full px-3 py-1.5 text-left text-sm hover:bg-blue-50 transition-colors">
                                        <span class="font-medium text-gray-900">{{ $inst->AccountName }}</span>
                                        @if($inst->is_potential)
                                            <span class="ml-2 inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700">
                                                잠재기관
                                            </span>
                                        @endif
                                        <span class="ml-2 text-xs text-gray-500">({{ $inst->SKcode }})</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @if(filled($formSkCode))
                            <p class="mt-1 text-xs text-blue-600">
                                선택된 기관: {{ $formAccountName }} ({{ $formSkCode }})
                                @if($formIsPotential)
                                    <span class="ml-1 inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700 align-middle">
                                        잠재기관
                                    </span>
                                @endif
                            </p>
                        @elseif($formIsPotential && filled($formPotentialTargetId))
                            <p class="mt-1 text-xs text-blue-600">
                                선택된 기관: {{ $formAccountName }} (SK 미발급)
                                <span class="ml-1 inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700 align-middle">
                                    잠재기관
                                </span>
                            </p>
                        @endif

                        @error('formSkCode')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                        @unless($institutionSelected)
                            <p class="mt-1 text-xs text-gray-500">기관을 먼저 선택하면 아래 입력 항목이 활성화됩니다.</p>
                        @endunless
                    </div>

                    {{-- 가능성 (잠재기관일 때만) --}}
                    @if($formIsPotential)
                    <div class="w-32 shrink-0">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            가능성
                            <span class="inline-flex items-center rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700">잠재</span>
                        </label>
                        <select wire:model="formPossibility"
                                class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">-</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                        <p class="mt-1 text-[10px] text-gray-400 leading-tight">저장 시 잠재기관에도 반영</p>
                    </div>
                    @endif

                    {{-- CO명 --}}
                    <div class="w-44 shrink-0">
                        <label class="block text-sm font-medium text-gray-700 mb-1">CO명</label>
                        <input type="text"
                               wire:model="formCoName"
                               @disabled(!$institutionSelected)
                               class="w-full py-1.5 px-3 text-sm border rounded-lg
                                      {{ $institutionSelected ? 'border-gray-300 bg-white text-gray-700' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                    </div>
                </div>

                {{-- ── 2행~: 나머지 필드 2열 그리드 ──────────────────── --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            지원 날짜 <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               wire:model="formSupportDate"
                               @disabled(!$institutionSelected)
                               class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                      {{ $errors->has('formSupportDate') ? 'border-red-400' : '' }}
                                      {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                        @error('formSupportDate')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">지원 방법</label>
                        <select wire:model="formSupportType"
                                @disabled(!$institutionSelected)
                                class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                       {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}">
                            <option>전화</option>
                            <option>대면</option>
                            <option>화상</option>
                            <option>이메일</option>
                            <option>문자</option>
                            <option>기타</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            지원 시간 <span class="text-red-500">*</span>
                        </label>
                        <input type="time"
                               wire:model="formSupportTime"
                               @disabled(!$institutionSelected)
                               class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                      {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">참석자</label>
                        <input type="text"
                               wire:model="formTarget"
                               @disabled(!$institutionSelected)
                               placeholder="예: 원장, 교사 2명"
                               class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                      {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-3">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">기관 이슈 및 논의 사항</h3>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">기관과의 소통내용</label>
                        <textarea wire:model="formToAccount"
                                  @disabled(!$institutionSelected)
                                  rows="10"
                                  placeholder="기관과 나눈 주요 대화 내용을 기록해 주세요 (Enter 시 새 줄에 ▶ 추가)"
                                  x-on:keydown.enter="mochiSupportEnterTriangle($event)"
                                  class="w-full min-h-[260px] py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y
                                         {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">본사/타 부서 공유 내용</label>
                        <textarea wire:model="formToDepart"
                                  @disabled(!$institutionSelected)
                                  rows="5"
                                  placeholder="타 부서와 공유할 내용을 기록해 주세요 (Enter 시 새 줄에 ▶ 추가)"
                                  x-on:keydown.enter="mochiSupportEnterTriangle($event)"
                                  class="w-full min-h-[120px] py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y
                                         {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"></textarea>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-3">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">SF 파일 업로드 (선택)</h3>
                    <p class="mb-3 text-xs text-gray-500">
                        저장 시 보고서와 함께 계약문서/`SF_Files` 메타데이터가 동시에 등록됩니다.
                    </p>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="file"
                               id="sf-upload-input"
                               wire:model="sfUpload"
                               @disabled(!$sfUploadEnabled)
                               class="hidden"
                               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/*" />

                        <label for="sf-upload-input"
                               class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors
                                      {{ $sfUploadEnabled
                                          ? 'cursor-pointer bg-blue-600 hover:bg-blue-700'
                                          : 'cursor-not-allowed bg-gray-400' }}">
                            파일 선택
                        </label>

                        <button type="button"
                                wire:click="clearSfUpload"
                                @disabled(!$sfUploadEnabled || !$sfUpload)
                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 transition-colors
                                       hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40">
                            선택 해제
                        </button>

                        <span wire:loading wire:target="sfUpload" class="text-xs text-blue-600">파일 처리 중…</span>
                    </div>

                    <div class="mt-2 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                        @if($sfUpload)
                            선택된 파일: <span class="font-medium text-gray-800 break-all">{{ $sfUpload->getClientOriginalName() }}</span>
                        @elseif($institutionSelected && !$sfUploadEnabled)
                            SK코드 발급 전(미계약 잠재기관)에는 파일 업로드 없이 보고서만 저장됩니다.
                        @else
                            파일을 선택하면 이름이 여기에 표시됩니다.
                        @endif
                    </div>
                    @error('sfUpload')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between rounded-b-2xl">
                <label class="flex items-center gap-3 cursor-pointer">
                    <span class="text-sm font-medium text-gray-700">완료처리</span>
                    <button type="button"
                            wire:click="$toggle('formCompleted')"
                            @disabled(!$institutionSelected)
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent
                                   transition-colors duration-200 focus:outline-none
                                   {{ $formCompleted ? 'bg-green-500' : 'bg-gray-300' }}
                                   {{ $institutionSelected ? '' : 'opacity-50 cursor-not-allowed' }}">
                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200
                                     {{ $formCompleted ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                    <span class="text-xs {{ $formCompleted ? 'text-green-600 font-medium' : 'text-gray-400' }}">
                        {{ $formCompleted ? '완료됨' : '진행중' }}
                    </span>
                </label>

                <div class="flex items-center gap-3">
                    <a href="/supports"
                       class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                        취소하기
                    </a>
                    <button type="submit"
                            @disabled(!$institutionSelected)
                            class="px-5 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="save">
                        <span wire:loading.remove wire:target="save">저장하기</span>
                        <span wire:loading wire:target="save">저장 중...</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
