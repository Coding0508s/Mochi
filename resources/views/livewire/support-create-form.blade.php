<div class="mochi-page">
    <div class="mb-4">
        <a href="{{ \App\Support\TeamMenuContext::route('supports.index', [], null, $formTeamMenu) }}"
           class="inline-flex items-center gap-2 px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            목록으로 돌아가기
        </a>
    </div>

    <div class="mochi-table-card max-w-5xl overflow-hidden border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div class="w-full flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ \App\Support\TeamMenuContext::supportReportFormHeading(null, $formTeamMenu, $reportMode) }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ \App\Support\TeamMenuContext::supportReportFormSubtitle(null, $formTeamMenu, $reportMode) }}
                    </p>
                </div>
                <div class="inline-flex rounded-xl border border-gray-200 p-1 bg-gray-50">
                    <button type="button"
                            wire:click="setReportMode('institution')"
                            class="px-3 py-1.5 text-sm rounded-lg transition-colors {{ $reportMode === 'institution' ? 'bg-white text-gray-900 shadow-sm border border-gray-200 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                        기관 지원 보고서
                    </button>
                    @if($this->canUseTeacherReportMode())
                        <button type="button"
                                wire:click="setReportMode('teacher')"
                                class="px-3 py-1.5 text-sm rounded-lg transition-colors {{ $reportMode === 'teacher' ? 'bg-white text-gray-900 shadow-sm border border-gray-200 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                            교사 지원 보고서
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <form wire:submit="save">
            @php
                $institutionSelected = filled($formSkCode) || filled($formPotentialTargetId);
                $sfUploadEnabled = $institutionSelected && filled($formSkCode);
                $coachTypedTeacherCreate = $this->usesCoachTypedTeacherSupportCreate();
                $coachTypedTeacherSupportForm = $this->usesCoachTypedTeacherSupportForm();
                $teacherSelected = filled($formTeacherId);
                $canPickSupportType = $coachTypedTeacherCreate && $institutionSelected && $teacherSelected;
            @endphp
            <div class="px-8 py-6 space-y-5">

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
                               class="w-full py-2.5 px-3 text-sm border rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
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

                    @unless($coachTypedTeacherCreate || $coachTypedTeacherSupportForm)
                    {{-- CO명 --}}
                    <div class="w-44 shrink-0">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ \App\Support\TeamMenuContext::institutionSupportReportAssigneeLabel(null, $formTeamMenu) }}</label>
                        <input type="text"
                               wire:model="formCoName"
                               @disabled(!$institutionSelected)
                               class="w-full py-1.5 px-3 text-sm border rounded-lg
                                      {{ $institutionSelected ? 'border-gray-300 bg-white text-gray-700' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                    </div>
                    @endunless
                </div>

                @if($coachTypedTeacherCreate)
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                담당 Coach
                            </label>
                            <input type="text"
                                   wire:model="formCoName"
                                   @disabled(!$institutionSelected)
                                   class="w-full py-2.5 px-3 text-sm border rounded-xl
                                          {{ $institutionSelected ? 'border-gray-300 bg-white text-gray-700' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                교사명 <span class="text-red-500">*</span>
                            </label>
                            <select wire:model.live="formTeacherId"
                                    @disabled(!$institutionSelected || $institutionTeachers->isEmpty())
                                    class="w-full py-2.5 px-3 text-sm border rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                                           {{ $errors->has('formTeacherId') ? 'border-red-400' : '' }}
                                           {{ $institutionSelected && $institutionTeachers->isNotEmpty() ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}">
                                <option value="">교사를 선택하세요</option>
                                @foreach($institutionTeachers as $teacher)
                                    <option value="{{ $teacher->ID }}">{{ $teacher->Name }}</option>
                                @endforeach
                            </select>
                            @if($institutionSelected && $institutionTeachers->isEmpty())
                                <p class="mt-1 text-xs text-amber-600">선택한 기관에 등록된 교사가 없습니다. Coach Team 교사지원 화면에서 교사를 먼저 등록해 주세요.</p>
                            @endif
                            @error('formTeacherId')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">교사 지원 유형 선택</h3>
                        <p class="text-xs text-gray-500 mb-3">기관과 교사를 선택한 뒤, 작성할 보고서 유형을 선택하면 해당 입력 화면이 열립니다.</p>
                        <div class="space-y-2">
                            <select
                                wire:change="startCoachTeacherSupportCreate($event.target.value)"
                                wire:loading.attr="disabled"
                                wire:target="startCoachTeacherSupportCreate"
                                @disabled(!$canPickSupportType)
                                class="w-full py-2.5 px-3 text-sm border rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                                       {{ $canPickSupportType
                                           ? 'border-blue-300 text-slate-600 bg-blue-50/40'
                                           : 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed' }}"
                            >
                                <option value="">지원 유형을 선택하세요</option>
                                @foreach($coachTeacherSupportCreateTypes as $pill)
                                    @php
                                        $pillLabel = is_array($pill) ? ($pill['label'] ?? '') : (string) $pill;
                                        $pillAction = is_array($pill) ? ($pill['action'] ?? '') : '';
                                    @endphp
                                    @if($pillLabel === '' || $pillAction === '')
                                        @continue
                                    @endif
                                    <option value="{{ $pillAction }}">{{ $pillLabel }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-400">유형을 선택하면 Coach Team 교사지원 전용 입력 화면이 열립니다.</p>
                        </div>
                    </div>
                @elseif($coachTypedTeacherSupportForm)
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">담당 Coach</label>
                            <input type="text" wire:model="formCoName" readonly
                                   class="w-full py-2.5 px-3 text-sm border border-gray-200 rounded-xl bg-gray-50 text-gray-700">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">교사명</label>
                            <input type="text" wire:model="formTarget" readonly
                                   class="w-full py-2.5 px-3 text-sm border border-gray-200 rounded-xl bg-gray-50 text-gray-700">
                        </div>
                    </div>

                    <div class="rounded-xl border border-blue-100 bg-blue-50/40 px-4 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $this->coachTypedTeacherSupportCreateLabel() }} 작성</p>
                                <p class="mt-1 text-xs text-gray-600">{{ $formAccountName }} · {{ $formTarget }}</p>
                                <p class="mt-2 text-xs text-gray-500">열린 입력 창에서 보고서를 작성한 뒤 저장해 주세요.</p>
                            </div>
                            <button type="button"
                                    wire:click="resetCoachTeacherSupportCreate"
                                    class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                                유형 다시 선택
                            </button>
                        </div>
                    </div>
                @else
                {{-- ── 2행~: 나머지 필드 2열 그리드 (기관/CS 교사 지원) ── --}}
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
                            @foreach($supportTypeOptions as $supportTypeOption)
                                <option>{{ $supportTypeOption }}</option>
                            @endforeach
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            @if($reportMode === 'teacher')
                                교사명 <span class="text-red-500">*</span>
                            @else
                                참석자
                            @endif
                        </label>
                        <input type="text"
                               wire:model="formTarget"
                               @disabled(!$institutionSelected)
                               placeholder="{{ $reportMode === 'teacher' ? '예: 홍길동' : '예: 원장, 교사 2명' }}"
                               class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                      {{ $errors->has('formTarget') ? 'border-red-400' : '' }}
                                      {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                        @error('formTarget')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-3">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">
                        {{ $reportMode === 'teacher' ? '교사 이슈 및 논의 사항' : '기관 이슈 및 논의 사항' }}
                    </h3>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $reportMode === 'teacher' ? '교사와의 소통내용' : '기관과의 소통내용' }}
                        </label>
                        <textarea wire:model="formToAccount"
                                  @disabled(!$institutionSelected)
                                  rows="10"
                                  placeholder="{{ $reportMode === 'teacher' ? '교사와 나눈 주요 대화 내용을 기록해 주세요 (Enter 시 새 줄에 ▶ 추가)' : '기관과 나눈 주요 대화 내용을 기록해 주세요 (Enter 시 새 줄에 ▶ 추가)' }}"
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
                            파일 첨부는 SK 코드가 있는 정식 기관에서만 가능합니다. 계약·SK 발급이 완료된 기관을 선택해 주세요.
                        @else
                            파일을 선택하면 이름이 여기에 표시됩니다.
                        @endif
                    </div>
                    @error('sfUpload')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                @if($reportMode === 'institution')
                    <div class="border-t border-gray-100 pt-3">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">긴급 알림</h3>
                        <p class="mb-3 text-xs text-gray-500">
                            긴급 알림을 켜면 선택된 수신자에게 인앱 알림과 이메일이 즉시 발송됩니다.
                        </p>

                        <div class="rounded-xl border border-orange-200 bg-orange-50/60 px-4 py-3 space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-orange-700">이슈 긴급 알림 (담당자 알림)</p>
                                    <p class="text-xs text-orange-700/80 mt-0.5">기관 담당자(CO, TR, CS)를 자동으로 추천합니다.</p>
                                </div>
                                <button type="button"
                                        wire:click="$toggle('isUrgent')"
                                        @disabled(!$institutionSelected)
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none
                                               {{ $isUrgent ? 'bg-orange-500' : 'bg-gray-300' }}
                                               {{ $institutionSelected ? '' : 'opacity-50 cursor-not-allowed' }}">
                                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200
                                                 {{ $isUrgent ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                </button>
                            </div>

                            @if($isUrgent)
                                <div class="space-y-2">
                                    <label class="block text-xs font-medium text-gray-700">알림 수신자</label>
                                    <div class="flex flex-wrap gap-2">
                                        @forelse($urgentRecipientIds as $recipientId)
                                            @php
                                                $recipient = collect($availableRecipients)->firstWhere('id', (int) $recipientId);
                                            @endphp
                                            @if($recipient)
                                                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs
                                                             {{ $recipient['is_auto'] ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-700' }}">
                                                    {{ $recipient['name'] }}
                                                    @if(!empty($recipient['roles']))
                                                        ({{ implode('/', $recipient['roles']) }})
                                                    @endif
                                                    <button type="button"
                                                            wire:click="removeRecipient({{ $recipient['id'] }})"
                                                            class="ml-1 text-gray-400 hover:text-red-500"
                                                            aria-label="수신자 제거">
                                                        ×
                                                    </button>
                                                </span>
                                            @endif
                                        @empty
                                            <span class="text-xs text-amber-700">자동 매칭된 담당자가 없습니다. 수동으로 추가해 주세요.</span>
                                        @endforelse
                                    </div>

                                    <div class="flex items-center gap-2 max-w-md">
                                        <select wire:model="selectedUrgentRecipientId"
                                                class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                                            <option value="">수신자 추가 선택</option>
                                            @foreach($availableRecipients as $recipient)
                                                <option value="{{ $recipient['id'] }}">
                                                    {{ $recipient['name'] }}
                                                    @if(!empty($recipient['roles']))
                                                        ({{ implode('/', $recipient['roles']) }})
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="button"
                                                wire:click="addRecipient"
                                                class="shrink-0 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            + 직원 추가
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
                @endif

            </div>

            <div class="px-8 py-5 bg-gray-50 border-t border-gray-200 flex items-center justify-between rounded-b-2xl">
                @if($coachTypedTeacherCreate)
                    <p class="text-xs text-gray-500">유형을 선택하면 Coach Team 교사지원 전용 입력 화면이 열립니다.</p>
                    <a href="{{ \App\Support\TeamMenuContext::route('supports.index', [], null, $formTeamMenu) }}"
                       class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-xl hover:bg-gray-100 transition-colors">
                        취소하기
                    </a>
                @elseif($coachTypedTeacherSupportForm)
                    <p class="text-xs text-gray-500">입력 창을 닫거나 저장하면 이 화면으로 돌아옵니다.</p>
                    <a href="{{ \App\Support\TeamMenuContext::route('supports.index', [], null, $formTeamMenu) }}"
                       class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-xl hover:bg-gray-100 transition-colors">
                        목록으로
                    </a>
                @else
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
                    <a href="{{ \App\Support\TeamMenuContext::route('supports.index', [], null, $formTeamMenu) }}"
                       class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                        취소하기
                    </a>
                    <button type="submit"
                            @disabled(!$institutionSelected)
                            class="px-5 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="save">
                        <span wire:loading.remove wire:target="save">
                            {{ $isUrgent ? '저장 및 알림 발송' : '저장하기' }}
                        </span>
                        <span wire:loading wire:target="save">저장 중...</span>
                    </button>
                </div>
                @endif
            </div>
        </form>
    </div>

    @if($formTeamMenu === 'coach' && $reportMode === 'teacher')
        @include('partials.coach.support-create-typed-modals')
    @endif
</div>
