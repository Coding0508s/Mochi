<div class="mochi-page" x-data @visit-support-show-alert.window="alert($event.detail.message)">
    @php
        $listRouteName = 'supports.index';
    @endphp
    <div class="mb-4">
        <a href="{{ \App\Support\TeamMenuContext::route($listRouteName, [], null, $formTeamMenu) }}"
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
                    @if($reportMode === 'issue')
                        <h2 class="text-base font-semibold text-gray-900">CS Team 기관 이슈 작성</h2>
                        <p class="text-xs text-gray-400 mt-0.5">기관에서 발생한 이슈를 짧게 기록합니다.</p>
                    @elseif($reportMode === 'teacher' && $formTeamMenu === 'coach')
                        <h2 class="text-base font-semibold text-gray-900">Coach Team 교사 지원 및 참관 보고서 작성</h2>
                        <p class="text-xs text-gray-400 mt-0.5">아래 입력 항목을 작성한 뒤 저장해 주세요.</p>
                    @else
                        <h2 class="text-base font-semibold text-gray-900">{{ \App\Support\TeamMenuContext::supportReportFormHeading(null, $formTeamMenu, $reportMode) }}</h2>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ \App\Support\TeamMenuContext::supportReportFormSubtitle(null, $formTeamMenu, $reportMode) }}
                        </p>
                    @endif
                </div>
                <div class="inline-flex rounded-xl border border-gray-200 p-1 bg-gray-50">
                    @if($this->canUseTeacherReportMode())
                        <button type="button"
                                wire:click="setReportMode('teacher')"
                                class="px-3 py-1.5 text-sm rounded-lg transition-colors {{ $reportMode === 'teacher' ? 'bg-white text-gray-900 shadow-sm border border-gray-200 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                            교사 지원 보고서
                        </button>
                    @endif
                    @if($this->canUseIssueReportMode())
                        <button type="button"
                                wire:click="setReportMode('issue')"
                                class="px-3 py-1.5 text-sm rounded-lg transition-colors {{ $reportMode === 'issue' ? 'bg-white text-gray-900 shadow-sm border border-gray-200 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                            기관 이슈
                        </button>
                    @endif
                    <button type="button"
                            wire:click="setReportMode('institution')"
                            class="px-3 py-1.5 text-sm rounded-lg transition-colors {{ $reportMode === 'institution' ? 'bg-white text-gray-900 shadow-sm border border-gray-200 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
                        기관 지원 보고서
                    </button>
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
                    <div wire:key="support-create-coach-teacher-picker" class="grid grid-cols-2 gap-3">
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
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">교사 지원 및 참관 보고서</h3>
                        <p class="text-xs text-gray-500">기관과 교사를 선택하면 11번째 교사 지원 및 참관 보고서 입력 영역이 이 페이지에 바로 표시됩니다.</p>
                    </div>
                @elseif($coachTypedTeacherSupportForm)
                    <div wire:key="support-create-coach-typed-report" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">담당 Coach</label>
                            <input type="text"
                                   wire:model="formCoName"
                                   @if($teacherSelected) readonly @else @disabled(!$institutionSelected) @endif
                                   class="w-full py-2.5 px-3 text-sm border rounded-xl
                                          {{ $teacherSelected || ! $institutionSelected ? 'border-gray-200 bg-gray-50 text-gray-700' : 'border-gray-300 bg-white text-gray-700' }}
                                          {{ $teacherSelected ? 'cursor-default' : '' }}">
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

                    <div wire:key="visit-report-basic-fields" class="grid grid-cols-1 gap-3 sm:grid-cols-2 border-t border-gray-100 pt-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">지원 날짜 <span class="text-red-500">*</span></label>
                            <input type="date" wire:model.blur="visitForm.support_date"
                                   class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            @error('visitForm.support_date')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">장소</label>
                            <input type="text" wire:model.blur="visitForm.support_location"
                                   placeholder="예: 분당 ○○어학원"
                                   class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">지원 목적 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.blur="visitForm.support_purpose"
                                   placeholder="예: 신임 교사 온보딩, 정기 수업 참관"
                                   class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            @error('visitForm.support_purpose')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">지원 방법</label>
                            <select wire:model.change.live="visitForm.meeting_type"
                                    class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @foreach(($visitConfig['method_options'] ?? []) as $method)
                                    <option value="{{ $method }}">{{ $method }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                현황 차수
                                @php
                                    $referenceYear = method_exists($this, 'supportRoundReferenceYear') ? $this->supportRoundReferenceYear() : null;
                                @endphp
                                @if($referenceYear !== null)
                                    <span class="ml-1 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                                        기준 연도 {{ $referenceYear }}
                                    </span>
                                @endif
                            </label>
                            @php
                                $roundOptions = method_exists($this, 'supportRoundOptions') ? $this->supportRoundOptions() : [];
                            @endphp
                            <select wire:model="supportRound"
                                    class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">기록 안 함</option>
                                @foreach($roundOptions as $option)
                                    <option value="{{ $option['value'] }}" @disabled((bool) ($option['disabled'] ?? false))>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('support_round')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">면담 시간</label>
                            <input type="time" wire:model.blur="visitForm.interview_time"
                                   class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                        </div>
                    </div>

                    <div wire:key="visit-report-observe-fields" class="border-t border-gray-100 pt-3">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">참관 수업 정보</h3>
                        <div class="grid grid-cols-4 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                                <input type="number" min="0" max="99" wire:model.blur="visitForm.observe_unit"
                                       class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                @error('visitForm.observe_unit')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lesson</label>
                                <input type="number" min="0" max="99" wire:model.blur="visitForm.observe_lesson"
                                       class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                @error('visitForm.observe_lesson')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">반</label>
                                <input type="text" wire:model.blur="visitForm.observe_class"
                                       class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">세</label>
                                <input type="text" wire:model.blur="visitForm.observe_age"
                                       class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Unit·Lesson 등 참관 수업 정보는 해당 없으면 비워 두셔도 됩니다.</p>
                    </div>

                    <div wire:key="visit-report-pre-request" class="border-t border-gray-100 pt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">사전 요청 및 주요 이슈</label>
                        <textarea wire:model.blur="visitForm.pre_request_notes"
                                  rows="4"
                                  placeholder="교사의 사전 요청 사항과 이번 지원이 필요했던 배경/이유를 간략히 기록 (예:학생 발화 참여율 저하, 신규 커리큘럼 도입 등)"
                                  class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"></textarea>
                    </div>

                    <div wire:key="visit-report-monitoring-feedback" class="border-t border-gray-100 pt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">세부 지원 내용(수업 모니터링 및 피드백) <span class="text-red-500">*</span></label>
                        <textarea wire:model.blur="visitForm.monitoring_feedback"
                                  rows="5"
                                  placeholder="수업 모니터링(커리큘럼 이행, 교수법, 학생 반응) 결과와 잘된 점(Strengths) 및 개선점(Areas for Improvement)을 통합하여 기록.
수업 후 교사 면담 내용, 현장 건의사항 및 애로사항, 코치의 후속 조치(Action Plan) 계획을 함께 작성."
                                  class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"></textarea>
                        @error('visitForm.monitoring_feedback')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div wire:key="visit-report-special-notes" class="border-t border-gray-100 pt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">특이사항</label>
                        <textarea wire:model.blur="visitForm.special_notes"
                                  rows="4"
                                  placeholder="상기 내용 외에 공유가 필요한 추가적인 특이점이나 보안 사항 기록"
                                  class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"></textarea>
                    </div>
                @elseif($reportMode === 'issue')
                {{-- ── 기관 이슈(경량) 입력: 발생일 → 시간 → 교사 ── --}}
                <div wire:key="support-create-issue-report" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            발생일 <span class="text-red-500">*</span>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            시간 <span class="text-red-500">*</span>
                        </label>
                        <input type="time"
                               wire:model="formSupportTime"
                               @disabled(!$institutionSelected)
                               class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                      {{ $errors->has('formSupportTime') ? 'border-red-400' : '' }}
                                      {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"/>
                        @error('formSupportTime')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            관련 교사 <span class="text-xs font-normal text-gray-400">(선택)</span>
                        </label>
                        <select wire:model.live="formTeacherId"
                                wire:key="issue-teacher-{{ $formSkCode }}"
                                @disabled(!$institutionSelected)
                                class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                       {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}">
                            <option value="">선택 안 함 (기관 공통)</option>
                            @foreach($institutionTeachers as $teacher)
                                <option value="{{ $teacher->ID }}">{{ $teacher->Name }}</option>
                            @endforeach
                        </select>
                        @if($institutionSelected && $institutionTeachers->isEmpty())
                            <p class="mt-1 text-xs text-amber-600">등록된 교사가 없어도 기관 공통으로 저장할 수 있습니다.</p>
                        @endif
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            이슈 내용 <span class="text-red-500">*</span>
                        </label>
                        <textarea wire:model="formIssue"
                                  @disabled(!$institutionSelected)
                                  rows="10"
                                  placeholder="발생한 이슈와 처리 내역을 기록해 주세요."
                                  class="w-full min-h-[260px] py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y
                                         {{ $errors->has('formIssue') ? 'border-red-400' : '' }}
                                         {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"></textarea>
                        @error('formIssue')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                @else
                {{-- ── 2행~: 나머지 필드 2열 그리드 (기관/CS 교사 지원) ── --}}
                <div wire:key="support-create-standard-report" class="grid grid-cols-2 gap-3">
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
                        <select wire:model.change.live="formSupportType"
                                @disabled(!$institutionSelected)
                                class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                                       {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}">
                            @foreach($supportTypeOptions as $supportTypeOption)
                                <option value="{{ $supportTypeOption }}">{{ $supportTypeOption }}</option>
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
                @endif

                @if($reportMode === 'issue')
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

            </div>

            <div class="px-8 py-5 bg-gray-50 border-t border-gray-200 flex items-center justify-between rounded-b-2xl">
                @if($coachTypedTeacherCreate)
                    <p class="text-xs text-gray-500">교사를 선택하면 지원 및 참관 보고서 입력 화면이 바로 열립니다.</p>
                    <a href="{{ \App\Support\TeamMenuContext::route('supports.index', [], null, $formTeamMenu) }}"
                       class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-xl hover:bg-gray-100 transition-colors">
                        취소하기
                    </a>
                @else
                <div class="flex flex-col gap-2">
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

                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ \App\Support\TeamMenuContext::route($listRouteName, [], null, $formTeamMenu) }}"
                       class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                        취소하기
                    </a>
                    @unless($crossTeamReadOnly ?? false)
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
                    @endunless
                </div>
                @endif
            </div>
        </form>
    </div>

</div>
