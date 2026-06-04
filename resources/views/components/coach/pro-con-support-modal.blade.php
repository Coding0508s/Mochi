@if($showProConModal)
    @php $viewOnly = $supportReportViewMode ?? false; @endphp
    <div class="mochi-modal-overlay {{ $viewOnly ? 'z-[70]' : 'z-[60]' }}" wire:click.self="closeProConModal">
        <div class="mochi-modal-shell max-w-5xl max-h-[95vh] min-h-0 flex flex-col" @click.stop>
            <x-admin.modal-header
                :title="$proConConfig['modal_title'] ?? 'TR 교사지원LSPConsulting'"
                :subtitle="$proConConfig['section_title'] ?? 'LittleSEED Consulting'"
                close-action="closeProConModal"
            />

            <div class="mochi-modal-body-scroll px-6 py-4">
                <div class="space-y-5 @if($viewOnly) pointer-events-none select-none @endif">
                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">
                        {{ $proConConfig['section_title'] ?? 'LittleSEED Consulting' }}
                    </div>
                    <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">담당 코치</label>
                                <input type="text" wire:model="proConForm.coach_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">기관명</label>
                                <input type="text" wire:model="proConForm.institution_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사명</label>
                                <input type="text" wire:model="proConForm.teacher_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">작성날짜</label>
                                <input type="date" wire:model="proConForm.support_date"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사경력</label>
                                <select wire:model="proConForm.teacher_experience"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    @foreach($proConConfig['teacher_experience_options'] ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"><span class="text-red-500">*</span> Session/Semester</label>
                                <div class="flex gap-2">
                                    <select wire:model="proConForm.session_number"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                        <option value="">차수 선택</option>
                                        @foreach($proConConfig['session_options'] ?? [] as $session)
                                            <option value="{{ $session }}">{{ $session }} 차</option>
                                        @endforeach
                                    </select>
                                    <select wire:model="proConForm.semester_label"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                        @foreach($proConConfig['semester_options'] ?? [] as $semester)
                                            <option value="{{ $semester }}">{{ $semester }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">면담일시 및 시간</label>
                                <div class="flex gap-2">
                                    <input type="date" wire:model="proConForm.interview_date"
                                           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    <input type="time" wire:model="proConForm.interview_time"
                                           class="w-32 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">면담 방식</label>
                                <select wire:model="proConForm.method"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                                    @foreach($proConConfig['method_options'] ?? [] as $method)
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
                        @foreach($proConConfig['procedures'] ?? [] as $key => $label)
                            <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" value="{{ $key }}" wire:model="proConForm.procedures"
                                       class="mt-0.5 rounded border-gray-300 text-mochi-header focus:ring-mochi-header">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">코치 리포트</div>
                    <div class="p-4 space-y-4">
                        @foreach($proConConfig['coach_report_fields'] ?? [] as $fieldKey => $fieldLabel)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $fieldLabel }}</label>
                                <textarea wire:model="proConForm.{{ $fieldKey }}" rows="3"
                                          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header resize-y"></textarea>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            </div>
            <div class="shrink-0 @if($viewOnly) pointer-events-auto @endif">
                @include('components.coach.partials.support-report-modal-footer', [
                    'closeAction' => 'closeProConModal',
                    'saveAction' => 'saveProConReport',
                    'markCompletedModel' => 'proConMarkCompleted',
                ])
            </div>
        </div>
    </div>
@endif
