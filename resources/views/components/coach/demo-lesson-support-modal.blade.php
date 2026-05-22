@if($showDemoLessonModal)
    @php $viewOnly = $supportReportViewMode ?? false; @endphp
    <div class="mochi-modal-overlay {{ $viewOnly ? 'z-[70]' : 'z-[60]' }}" wire:click.self="closeDemoLessonModal">
        <div class="mochi-modal-shell max-w-5xl max-h-[95vh] min-h-0 flex flex-col" @click.stop>
            <x-admin.modal-header
                :title="$viewOnly ? 'TR 교사지원 조회' : 'TR 교사지원신규작성'"
                subtitle="신규 교사 시연 수업 지원 보고서"
                close-action="closeDemoLessonModal"
            />

            <div class="mochi-modal-body-scroll px-6 py-4">
                <div class="space-y-5 @if($viewOnly) pointer-events-none select-none @endif">
                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">
                        교사 지원(신규교사)
                    </div>
                    <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">담당 코치</label>
                                <input type="text" wire:model="demoLessonForm.coach_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">기관명</label>
                                <input type="text" wire:model="demoLessonForm.institution_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">교사명</label>
                                <input type="text" wire:model="demoLessonForm.teacher_name" readonly
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">지원날짜</label>
                                <input type="date" wire:model="demoLessonForm.support_date"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">지원진도</label>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-600">Unit</span>
                                    <input type="number" min="0" max="99" wire:model="demoLessonForm.progress_unit"
                                           class="w-16 px-2 py-1.5 text-sm border border-gray-300 rounded-lg text-center focus:ring-2 focus:ring-mochi-header">
                                    <span class="text-xs text-gray-600">Lesson</span>
                                    <input type="number" min="0" max="99" wire:model="demoLessonForm.progress_lesson"
                                           class="w-16 px-2 py-1.5 text-sm border border-gray-300 rounded-lg text-center focus:ring-2 focus:ring-mochi-header">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Other</label>
                                <input type="text" wire:model="demoLessonForm.other_notes"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800">지원 절차</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($demoLessonConfig['procedures'] ?? [] as $key => $label)
                            <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer hover:text-blue-700">
                                <input type="checkbox" value="{{ $key }}" wire:model="demoLessonForm.procedures"
                                       class="mt-0.5 rounded border-gray-300 text-mochi-header focus:ring-mochi-header">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-800 text-center">
                        코치 리포트
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-3">
                                <p class="text-xs font-semibold text-blue-700 mb-2">Verbal Skill Tools</p>
                                <div class="space-y-1.5">
                                    @foreach($demoLessonConfig['verbal_tools'] ?? [] as $key => $label)
                                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input type="checkbox" value="{{ $key }}" wire:model="demoLessonForm.verbal_tools"
                                                   class="rounded border-gray-300 text-mochi-header focus:ring-mochi-header">
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-3">
                                <p class="text-xs font-semibold text-blue-700 mb-2">Language Arts Tools</p>
                                <div class="space-y-1.5">
                                    @foreach($demoLessonConfig['language_arts_tools'] ?? [] as $key => $label)
                                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input type="checkbox" value="{{ $key }}" wire:model="demoLessonForm.language_arts_tools"
                                                   class="rounded border-gray-300 text-mochi-header focus:ring-mochi-header">
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-blue-800 text-center py-2 bg-blue-50 border border-blue-100 rounded-t-lg">Comments</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 border border-t-0 border-blue-100 rounded-b-lg bg-white">
                                <textarea wire:model="demoLessonForm.comments_primary" rows="5"
                                          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header"></textarea>
                                <textarea wire:model="demoLessonForm.comments_secondary" rows="5"
                                          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-mochi-header"></textarea>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/30">
                            <p class="text-xs text-gray-600 mb-3">1) Developing &nbsp; 2) Functioning &nbsp; 3) Thriving</p>
                            <div class="space-y-2">
                                @foreach($demoLessonConfig['evaluation_criteria'] ?? [] as $key => $label)
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-sm text-gray-800">{{ $label }}</span>
                                        <select wire:model="demoLessonForm.evaluations.{{ $key }}"
                                                class="w-20 px-2 py-1 text-sm border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-mochi-header">
                                            @foreach($demoLessonConfig['evaluation_scale'] ?? [] as $value => $scaleLabel)
                                                <option value="{{ $value }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-blue-800 text-center py-2 bg-blue-50 border border-blue-100 rounded-t-lg">Overall Comments</p>
                            <textarea wire:model="demoLessonForm.overall_comments" rows="4"
                                      class="w-full px-3 py-2 text-sm border border-t-0 border-blue-100 rounded-b-lg bg-white focus:ring-2 focus:ring-mochi-header"></textarea>
                        </div>
                    </div>
                </section>
            </div>

            </div>
            <div class="shrink-0 @if($viewOnly) pointer-events-auto @endif">
                @include('components.coach.partials.support-report-modal-footer', [
                    'closeAction' => 'closeDemoLessonModal',
                    'saveAction' => 'saveDemoLessonReport',
                    'markCompletedModel' => 'demoLessonMarkCompleted',
                ])
            </div>
        </div>
    </div>
@endif
