@if($showOnsiteModal)
    @php $viewOnly = $supportReportViewMode ?? false; @endphp
    <div class="mochi-modal-overlay {{ $viewOnly ? 'z-[70]' : 'z-[60]' }}" wire:click.self="closeOnsiteModal">
        <div class="mochi-modal-shell max-w-5xl max-h-[95vh] min-h-0 flex flex-col" @click.stop>
            <x-admin.modal-header
                :title="$onsiteConfig['modal_title'] ?? 'TR 교사지원OnSite'"
                :subtitle="$onsiteConfig['section_title'] ?? '교사 지원 On-Site'"
                close-action="closeOnsiteModal"
            />

            <div class="mochi-modal-body-scroll px-6 py-4">
                <div class="space-y-5 @if($viewOnly) pointer-events-none select-none @endif">
                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">
                        {{ $onsiteConfig['section_title'] ?? '교사 지원 On-Site' }}
                    </div>
                    <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">담당 코치</label>
                                <input type="text" wire:model="onsiteForm.coach_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">기관명</label>
                                <input type="text" wire:model="onsiteForm.institution_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사명</label>
                                <input type="text" wire:model="onsiteForm.teacher_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">작성날짜</label>
                                <input type="date" wire:model="onsiteForm.support_date"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">참관 내용 요약</label>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                                    <span>Unit</span>
                                    <input type="number" min="0" max="99" wire:model="onsiteForm.observe_unit"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>Lesson</span>
                                    <input type="number" min="0" max="99" wire:model="onsiteForm.observe_lesson"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <input type="text" wire:model="onsiteForm.observe_summary_extra"
                                           class="w-24 px-2 py-1 text-sm border border-gray-300 rounded-lg">
                                    <span>반</span>
                                    <input type="text" wire:model="onsiteForm.observe_class"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>세</span>
                                    <input type="text" wire:model="onsiteForm.observe_age"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>Other:</span>
                                    <input type="text" wire:model="onsiteForm.other_notes"
                                           class="flex-1 min-w-[8rem] px-2 py-1 text-sm border border-gray-300 rounded-lg">
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사경력</label>
                                <select wire:model="onsiteForm.teacher_experience"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    @foreach($onsiteConfig['teacher_experience_options'] ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><span class="text-red-500">*</span> Session/Semester</label>
                                <div class="flex gap-2">
                                    <select wire:model="onsiteForm.session_number"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                        <option value="">차수 선택</option>
                                        @foreach($onsiteConfig['session_options'] ?? [] as $session)
                                            <option value="{{ $session }}">{{ $session }} 차</option>
                                        @endforeach
                                    </select>
                                    <select wire:model="onsiteForm.semester_label"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                        @foreach($onsiteConfig['semester_options'] ?? [] as $semester)
                                            <option value="{{ $semester }}">{{ $semester }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">면담일시 및 시간</label>
                                <div class="flex gap-2">
                                    <input type="date" wire:model="onsiteForm.interview_date"
                                           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    <input type="time" wire:model="onsiteForm.interview_time"
                                           class="w-32 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Meeting Type</label>
                                <select wire:model="onsiteForm.method"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    @foreach($onsiteConfig['method_options'] ?? [] as $method)
                                        <option value="{{ $method }}">{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">지원 절차</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($onsiteConfig['procedures'] ?? [] as $key => $label)
                            <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" value="{{ $key }}" wire:model="onsiteForm.procedures"
                                       class="mt-0.5 rounded border-gray-300 text-mochi-header focus:ring-mochi-header">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800 text-center">코치 리포트</div>
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
                                @foreach($onsiteConfig['coach_report'] ?? [] as $sectionKey => $section)
                                    <tr class="bg-blue-50/50">
                                        <td colspan="3" class="px-3 py-2 font-semibold text-blue-800 border-b border-gray-200">
                                            {{ $section['label'] }}:
                                        </td>
                                    </tr>
                                    @foreach($section['items'] ?? [] as $itemKey => $itemLabel)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                                            <td class="px-3 py-2 pl-6 text-gray-800">{{ $itemLabel }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <input type="checkbox" value="{{ $itemKey }}" wire:model="onsiteForm.strength_areas"
                                                       class="rounded border-gray-300 text-mochi-header focus:ring-mochi-header">
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <input type="checkbox" value="{{ $itemKey }}" wire:model="onsiteForm.growth_areas"
                                                       class="rounded border-gray-300 text-mochi-header focus:ring-mochi-header">
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
                    'closeAction' => 'closeOnsiteModal',
                    'saveAction' => 'saveOnsiteReport',
                    'markCompletedModel' => 'onsiteMarkCompleted',
                ])
            </div>
        </div>
    </div>
@endif
