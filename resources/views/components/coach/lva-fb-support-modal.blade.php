@if($showLvaFbModal)
    @php $viewOnly = $supportReportViewMode ?? false; @endphp
    <div class="mochi-modal-overlay {{ $viewOnly ? 'z-[70]' : 'z-[60]' }}" wire:click.self="closeLvaFbModal">
        <div class="mochi-modal-shell max-w-5xl max-h-[95vh] min-h-0 flex flex-col" @click.stop>
            <x-admin.modal-header
                :title="$viewOnly ? 'TR 교사지원 조회' : 'TR 교사지원LVA'"
                close-action="closeLvaFbModal"
            >
                <x-slot:titleAddon>
                    <span class="inline-flex items-center rounded bg-mochi-header px-2 py-0.5 text-xs font-semibold text-white">
                        {{ $lvaFbConfig['variant_badge'] ?? 'FB' }}
                    </span>
                </x-slot:titleAddon>
            </x-admin.modal-header>

            <div class="mochi-modal-body-scroll px-6 py-4">
                <div class="space-y-5 @if($viewOnly) pointer-events-none select-none @endif">
                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">
                        교사 지원 LVA
                    </div>
                    <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">담당 코치</label>
                                <input type="text" wire:model="lvaFbForm.coach_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">기관명</label>
                                <input type="text" wire:model="lvaFbForm.institution_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사명</label>
                                <input type="text" wire:model="lvaFbForm.teacher_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">작성날짜</label>
                                <input type="date" wire:model="lvaFbForm.support_date"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">참관 내용 요약</label>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                                    <span>Unit</span>
                                    <input type="number" min="0" max="99" wire:model="lvaFbForm.observe_unit"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>Lesson</span>
                                    <input type="number" min="0" max="99" wire:model="lvaFbForm.observe_lesson"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>반</span>
                                    <input type="text" wire:model="lvaFbForm.observe_class"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>세</span>
                                    <input type="text" wire:model="lvaFbForm.observe_age"
                                           class="w-14 px-2 py-1 text-sm border border-gray-300 rounded-lg text-center">
                                    <span>Other:</span>
                                    <input type="text" wire:model="lvaFbForm.other_notes"
                                           class="flex-1 min-w-[8rem] px-2 py-1 text-sm border border-gray-300 rounded-lg">
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사경력</label>
                                <select wire:model="lvaFbForm.teacher_experience"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    @foreach($lvaFbConfig['teacher_experience_options'] ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><span class="text-red-500">*</span> Session/Semester</label>
                                <div class="flex gap-2">
                                    <select wire:model="lvaFbForm.session_number"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                        @foreach($lvaFbConfig['session_options'] ?? [] as $session)
                                            <option value="{{ $session }}">{{ $session }} 차</option>
                                        @endforeach
                                    </select>
                                    <select wire:model="lvaFbForm.semester_label"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                        @foreach($lvaFbConfig['semester_options'] ?? [] as $semester)
                                            <option value="{{ $semester }}">{{ $semester }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">면담일시 및 시간</label>
                                <div class="flex gap-2">
                                    <input type="date" wire:model="lvaFbForm.interview_date"
                                           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    <input type="time" wire:model="lvaFbForm.interview_time"
                                           class="w-32 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Session Type</label>
                                <select wire:model="lvaFbForm.method"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    @foreach($lvaFbConfig['method_options'] ?? [] as $method)
                                        <option value="{{ $method }}">{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">영상길이</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" max="999" wire:model="lvaFbForm.video_length_minutes"
                                           class="w-20 px-2 py-1.5 text-sm border border-gray-300 rounded-lg text-center">
                                    <span class="text-sm text-gray-600">분</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">지원 절차</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($lvaFbConfig['procedures'] ?? [] as $key => $label)
                            <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" value="{{ $key }}" wire:model="lvaFbForm.procedures"
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
                                @foreach($lvaFbConfig['coach_report'] ?? [] as $sectionKey => $section)
                                    <tr class="bg-blue-50/50">
                                        <td colspan="3" class="px-3 py-2 font-semibold text-blue-800 border-b border-gray-200">
                                            {{ $section['label'] }}:
                                        </td>
                                    </tr>
                                    @foreach($section['items'] ?? [] as $itemKey => $itemLabel)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                                            <td class="px-3 py-2 pl-6 text-gray-800">{{ $itemLabel }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <input type="checkbox" value="{{ $itemKey }}" wire:model="lvaFbForm.strength_areas"
                                                       class="rounded border-gray-300 text-mochi-header focus:ring-mochi-header">
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <input type="checkbox" value="{{ $itemKey }}" wire:model="lvaFbForm.growth_areas"
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
                    'closeAction' => 'closeLvaFbModal',
                    'saveAction' => 'saveLvaFbReport',
                    'markCompletedModel' => 'lvaFbMarkCompleted',
                ])
            </div>
        </div>
    </div>
@endif
