@if($showVisitModal)
    @php $viewOnly = $supportReportViewMode ?? false; @endphp
    <div class="mochi-modal-overlay {{ $viewOnly ? 'z-[70]' : 'z-[60]' }}"
         wire:click.self="closeVisitModal"
         x-data
         @visit-support-show-alert.window="alert($event.detail.message ?? $event.detail?.[0]?.message ?? '필수 입력 항목을 확인해 주세요.')">
        <div class="mochi-modal-shell max-w-5xl max-h-[95vh] min-h-0 flex flex-col" @click.stop>
            <x-admin.modal-header
                :title="$visitConfig['modal_title'] ?? '[GrapeSEED] 교사 지원 및 참관 보고서'"
                :subtitle="$visitConfig['section_title'] ?? '교사 지원 및 참관 보고서'"
                close-action="closeVisitModal"
            />

            <div class="mochi-modal-body-scroll px-6 py-4">
                <div class="space-y-5 @if($viewOnly) pointer-events-none select-none @endif">
                    <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">
                            1. 지원 개요
                        </div>
                        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">담당 코치</label>
                                <input type="text" wire:model="visitForm.coach_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">기관명</label>
                                <input type="text" wire:model="visitForm.institution_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사명</label>
                                <input type="text" wire:model="visitForm.teacher_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><span class="text-red-500">*</span> 작성 날짜</label>
                                <input type="date" wire:model="visitForm.support_date"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                @error('visitForm.support_date')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">장소</label>
                                <input type="text" wire:model="visitForm.support_location"
                                       placeholder="예: 분당 ○○어학원"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><span class="text-red-500">*</span> 지원 목적</label>
                                <input type="text" wire:model="visitForm.support_purpose"
                                       placeholder="예: 신임 교사 온보딩, 정기 수업 참관, 특정 이슈 해결등"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                @error('visitForm.support_purpose')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">지원 방법</label>
                                <select wire:model="visitForm.meeting_type"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    @foreach($visitConfig['method_options'] ?? [] as $method)
                                        <option value="{{ $method }}">{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">
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
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
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
                                <label class="block text-xs text-gray-500 mb-1">면담 시간</label>
                                <input type="time" wire:model="visitForm.interview_time"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">참관 수업 정보</label>
                                <div class="flex flex-nowrap items-center gap-2 overflow-x-auto text-xs text-gray-600">
                                    <span class="shrink-0">Unit</span>
                                    <input type="number" min="0" max="99" wire:model="visitForm.observe_unit"
                                           class="w-14 shrink-0 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span class="shrink-0">Lesson</span>
                                    <input type="number" min="0" max="99" wire:model="visitForm.observe_lesson"
                                           class="w-14 shrink-0 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <input type="text" wire:model="visitForm.observe_summary_extra"
                                           class="w-24 shrink-0 px-2 py-1 text-sm border border-gray-300 rounded-lg">
                                    <span class="shrink-0">반</span>
                                    <input type="text" wire:model="visitForm.observe_class"
                                           class="w-14 shrink-0 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span class="shrink-0">세</span>
                                    <input type="text" wire:model="visitForm.observe_age"
                                           class="w-14 shrink-0 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">
                            2. 사전 요청 및 주요 이슈
                        </div>
                        <div class="p-4">
                            <textarea wire:model="visitForm.pre_request_notes"
                                      rows="5"
                                      placeholder="교사의 사전 요청 사항과 이번 지원이 필요했던 배경/이유를 간략히 기록 (예:학생 발화 참여율 저하, 신규 커리큘럼 도입 등)"
                                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header resize-y"></textarea>
                        </div>
                    </section>

                    <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">
                            3. 세부 지원 내용 (수업 모니터링 및 피드백)
                        </div>
                        <div class="p-4 space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><span class="text-red-500">*</span> 모니터링 결과 / Strengths / 개선점</label>
                                <textarea wire:model="visitForm.monitoring_feedback"
                                          rows="6"
                                          placeholder="수업 모니터링 결과와 잘된 점, 개선점을 통합해 작성해 주세요."
                                          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header resize-y"></textarea>
                                @error('visitForm.monitoring_feedback')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">
                            4. 특이사항
                        </div>
                        <div class="p-4">
                            <textarea wire:model="visitForm.special_notes"
                                      rows="4"
                                      placeholder="추가 공유가 필요한 특이점이나 보안 사항을 기록해 주세요."
                                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header resize-y"></textarea>
                        </div>
                    </section>
                </div>
            </div>

            <div class="shrink-0 @if($viewOnly) pointer-events-auto @endif">
                @include('components.coach.partials.support-report-modal-footer', [
                    'closeAction' => 'closeVisitModal',
                    'saveAction' => 'saveVisitReport',
                    'markCompletedModel' => 'visitMarkCompleted',
                    'showRoundPicker' => false,
                ])
            </div>
        </div>
    </div>
@endif
