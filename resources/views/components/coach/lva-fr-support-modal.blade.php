@if($showLvaFrModal)
    @php $viewOnly = $supportReportViewMode ?? false; @endphp
    <div class="mochi-modal-overlay {{ $viewOnly ? 'z-[70]' : 'z-[60]' }}" wire:click.self="closeLvaFrModal">
        <div class="mochi-modal-shell max-w-5xl max-h-[95vh] min-h-0 flex flex-col" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-blue-50/80 to-white shrink-0">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-semibold text-blue-700">{{ $viewOnly ? 'TR 교사지원 조회' : 'TR 교사지원LVA' }}</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-600 text-white">
                        {{ $lvaFrConfig['variant_badge'] ?? 'FR' }}
                    </span>
                </div>
                <button type="button" wire:click="closeLvaFrModal"
                        class="text-gray-400 hover:text-gray-600 cursor-pointer" aria-label="닫기">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mochi-modal-body-scroll px-6 py-4">
                <div class="space-y-5 @if($viewOnly) pointer-events-none select-none @endif">
                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-blue-50 border-b border-blue-100 text-sm font-semibold text-blue-800 flex items-center gap-2">
                        교사 지원 LVA
                        <span class="px-1.5 py-0.5 text-xs bg-blue-600 text-white rounded">{{ $lvaFrConfig['variant_badge'] ?? 'FR' }}</span>
                    </div>
                    <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">코치명</label>
                                <input type="text" wire:model="lvaFrForm.coach_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">기관명</label>
                                <input type="text" wire:model="lvaFrForm.institution_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사명</label>
                                <input type="text" wire:model="lvaFrForm.teacher_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">작성날짜</label>
                                <input type="date" wire:model="lvaFrForm.support_date"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">참관 내용 요약</label>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                                    <span>Unit</span>
                                    <input type="number" min="0" max="99" wire:model="lvaFrForm.observe_unit"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>Lesson</span>
                                    <input type="number" min="0" max="99" wire:model="lvaFrForm.observe_lesson"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>반</span>
                                    <input type="text" wire:model="lvaFrForm.observe_class"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>세</span>
                                    <input type="text" wire:model="lvaFrForm.observe_age"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사경력</label>
                                <select wire:model="lvaFrForm.teacher_experience"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    @foreach($lvaFrConfig['teacher_experience_options'] ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><span class="text-red-500">*</span> Session/Semester</label>
                                <div class="flex gap-2">
                                    <select wire:model="lvaFrForm.session_number"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        @foreach($lvaFrConfig['session_options'] ?? [] as $session)
                                            <option value="{{ $session }}">{{ $session }} 차</option>
                                        @endforeach
                                    </select>
                                    <select wire:model="lvaFrForm.semester_label"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        @foreach($lvaFrConfig['semester_options'] ?? [] as $semester)
                                            <option value="{{ $semester }}">{{ $semester }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">면담일시 및 시간</label>
                                <div class="flex gap-2">
                                    <input type="date" wire:model="lvaFrForm.interview_date"
                                           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <input type="time" wire:model="lvaFrForm.interview_time"
                                           class="w-32 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Method</label>
                                <select wire:model="lvaFrForm.method"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    @foreach($lvaFrConfig['method_options'] ?? [] as $method)
                                        <option value="{{ $method }}">{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Other</label>
                                <input type="text" wire:model="lvaFrForm.other_notes"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">영상길이</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" max="999" wire:model="lvaFrForm.video_length_minutes"
                                           class="w-20 px-2 py-1.5 text-sm border border-gray-300 rounded-lg text-center">
                                    <span class="text-sm text-gray-600">분</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-blue-50 border-b border-blue-100 text-sm font-semibold text-blue-800">지원 절차</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($lvaFrConfig['procedures'] ?? [] as $key => $label)
                            <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" value="{{ $key }}" wire:model="lvaFrForm.procedures"
                                       class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-blue-50 border-b border-blue-100 text-sm font-semibold text-blue-800 text-center">코치 리포트</div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-sm border border-gray-200">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-3 py-2 text-left text-gray-600 font-medium w-1/2"></th>
                                    <th class="px-3 py-2 text-center text-gray-600 font-medium w-1/4">Strength Area</th>
                                    <th class="px-3 py-2 text-center text-gray-600 font-medium w-1/4">Growth Area</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lvaFrConfig['coach_report'] ?? [] as $sectionKey => $section)
                                    <tr class="bg-blue-50/50">
                                        <td colspan="3" class="px-3 py-2 font-semibold text-blue-800 border-b border-gray-200">
                                            {{ $section['label'] }}:
                                        </td>
                                    </tr>
                                    @foreach($section['items'] ?? [] as $itemKey => $itemLabel)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                                            <td class="px-3 py-2 pl-6 text-gray-800">{{ $itemLabel }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <input type="checkbox" value="{{ $itemKey }}" wire:model="lvaFrForm.strength_areas"
                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <input type="checkbox" value="{{ $itemKey }}" wire:model="lvaFrForm.growth_areas"
                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            </div>
            <div class="shrink-0 @if($viewOnly) pointer-events-auto @endif">
                @include('components.coach.partials.support-report-modal-footer', [
                    'closeAction' => 'closeLvaFrModal',
                    'saveAction' => 'saveLvaFrReport',
                    'markCompletedModel' => 'lvaFrMarkCompleted',
                ])
            </div>
        </div>
    </div>
@endif
