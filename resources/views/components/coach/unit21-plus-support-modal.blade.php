@if($showUnit21PlusModal)
    @php $viewOnly = $supportReportViewMode ?? false; @endphp
    <div class="mochi-modal-overlay {{ $viewOnly ? 'z-[70]' : 'z-[60]' }}" wire:click.self="closeUnit21PlusModal">
        <div class="mochi-modal-shell max-w-5xl max-h-[95vh] min-h-0 flex flex-col" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-blue-50/80 to-white shrink-0">
                <div>
                    <h3 class="text-lg font-semibold text-blue-700">{{ $unit21PlusConfig['modal_title'] ?? 'TR 교사지원U21' }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $unit21PlusConfig['section_title'] ?? 'Unit21+(Training+Practice Teaching)' }}</p>
                </div>
                <button type="button" wire:click="closeUnit21PlusModal"
                        class="text-gray-400 hover:text-gray-600 cursor-pointer" aria-label="닫기">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mochi-modal-body-scroll px-6 py-4">
                <div class="space-y-5 @if($viewOnly) pointer-events-none select-none @endif">
                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-blue-50 border-b border-blue-100 text-sm font-semibold text-blue-800">
                        {{ $unit21PlusConfig['section_title'] ?? 'Unit21+(Training+Practice Teaching)' }}
                    </div>
                    <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">코치명</label>
                                <input type="text" wire:model="unit21PlusForm.coach_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">기관명</label>
                                <input type="text" wire:model="unit21PlusForm.institution_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사명</label>
                                <input type="text" wire:model="unit21PlusForm.teacher_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">작성날짜</label>
                                <input type="date" wire:model="unit21PlusForm.support_date"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사경력</label>
                                <select wire:model="unit21PlusForm.teacher_experience"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    @foreach($unit21PlusConfig['teacher_experience_options'] ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><span class="text-red-500">*</span> Session/Semester</label>
                                <div class="flex gap-2">
                                    <select wire:model="unit21PlusForm.session_number"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        @foreach($unit21PlusConfig['session_options'] ?? [] as $session)
                                            <option value="{{ $session }}">{{ $session }} 차</option>
                                        @endforeach
                                    </select>
                                    <select wire:model="unit21PlusForm.semester_label"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        @foreach($unit21PlusConfig['semester_options'] ?? [] as $semester)
                                            <option value="{{ $semester }}">{{ $semester }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">면담일시 및 시간</label>
                                <div class="flex gap-2">
                                    <input type="date" wire:model="unit21PlusForm.interview_date"
                                           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <input type="time" wire:model="unit21PlusForm.interview_time"
                                           class="w-32 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">면담 방식</label>
                                <select wire:model="unit21PlusForm.method"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    @foreach($unit21PlusConfig['method_options'] ?? [] as $method)
                                        <option value="{{ $method }}">{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="border border-dashed border-blue-200 rounded-lg p-3 bg-blue-50/30">
                                <label class="block text-xs text-gray-500 mb-2">지원 진도</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-gray-600">Unit</span>
                                    <input type="number" min="0" max="99" wire:model="unit21PlusForm.progress_unit"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center focus:ring-2 focus:ring-blue-500">
                                    <span class="text-xs text-gray-600">Lesson</span>
                                    <input type="number" min="0" max="99" wire:model="unit21PlusForm.progress_lesson"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center focus:ring-2 focus:ring-blue-500">
                                    <span class="text-xs text-gray-600">Other</span>
                                    <input type="text" wire:model="unit21PlusForm.progress_other"
                                           class="flex-1 min-w-[8rem] px-2 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-blue-50 border-b border-blue-100 text-sm font-semibold text-blue-800">지원 절차</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($unit21PlusConfig['procedures'] ?? [] as $key => $procedure)
                            <div class="space-y-2">
                                <label class="flex items-start gap-2 text-sm font-medium text-gray-800 cursor-pointer">
                                    <input type="checkbox" value="{{ $key }}" wire:model="unit21PlusForm.procedures"
                                           class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span>{{ $procedure['label'] ?? $key }}</span>
                                </label>
                                @if(! empty($procedure['details']))
                                    <ul class="ml-6 space-y-1 text-xs text-gray-600">
                                        @foreach($procedure['details'] as $detail)
                                            <li>- {{ $detail }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-blue-50 border-b border-blue-100 text-sm font-semibold text-blue-800 text-center">코치 리포트</div>
                    <div class="p-4 space-y-4">
                        @php
                            $verbalSection = $unit21PlusConfig['materials']['verbal_skill_tools'] ?? [];
                            $languageSection = $unit21PlusConfig['materials']['language_arts_tools'] ?? [];
                        @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <p class="text-sm font-semibold text-blue-800 mb-2">{{ $verbalSection['label'] ?? 'Verbal Skill Tools' }}</p>
                                <div class="space-y-2">
                                    @foreach($verbalSection['items'] ?? [] as $itemKey => $itemLabel)
                                        <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input type="checkbox" value="{{ $itemKey }}" wire:model="unit21PlusForm.verbal_materials"
                                                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span>{{ $itemLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-blue-800 mb-2">{{ $languageSection['label'] ?? 'Language Arts Tools' }}</p>
                                <div class="space-y-2">
                                    @foreach($languageSection['items'] ?? [] as $itemKey => $itemLabel)
                                        <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input type="checkbox" value="{{ $itemKey }}" wire:model="unit21PlusForm.language_arts_materials"
                                                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span>{{ $itemLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2">Comments</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <textarea wire:model="unit21PlusForm.verbal_comments" rows="3"
                                          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 resize-y"></textarea>
                                <textarea wire:model="unit21PlusForm.language_arts_comments" rows="3"
                                          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 resize-y"></textarea>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2">Overall Comments</p>
                            <textarea wire:model="unit21PlusForm.overall_comments" rows="3"
                                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 resize-y"></textarea>
                        </div>
                    </div>
                </section>
            </div>

            </div>
            <div class="shrink-0 @if($viewOnly) pointer-events-auto @endif">
                @include('components.coach.partials.support-report-modal-footer', [
                    'closeAction' => 'closeUnit21PlusModal',
                    'saveAction' => 'saveUnit21PlusReport',
                    'markCompletedModel' => 'unit21PlusMarkCompleted',
                ])
            </div>
        </div>
    </div>
@endif
