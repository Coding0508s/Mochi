<div class="space-y-4">
    <div>
        <p class="text-sm font-medium text-gray-700 mb-2">수업 참여</p>
        <div class="flex flex-wrap gap-4">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="radio"
                       wire:model="reinstateClassParticipation"
                       value="in"
                       class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                <span>참여</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="radio"
                       wire:model="reinstateClassParticipation"
                       value="out"
                       class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                <span>미참여</span>
            </label>
        </div>
    </div>

    <div>
        <p class="text-sm font-medium text-gray-700 mb-2">복직 기관</p>

        @if(filled($reinstateSkCode))
            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2.5 text-sm">
                <span class="font-medium text-gray-900">
                    <span class="text-emerald-700">[{{ $reinstateSkCode }}]</span>
                    {{ $reinstateSchoolName }}
                </span>
                <button type="button"
                        wire:click="clearReinstateInstitutionSelection"
                        class="ml-auto shrink-0 text-xs font-medium text-emerald-700 hover:text-emerald-900 underline cursor-pointer">
                    기존 기관으로 되돌리기
                </button>
            </div>
        @else
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-700 mb-2">
                기존 기관 유지:
                <span class="font-medium text-gray-900">
                    @if(filled($reinstateCurrentSkCode))
                        <span class="text-gray-500">[{{ $reinstateCurrentSkCode }}]</span>
                    @endif
                    {{ $reinstateCurrentAccountName !== '' ? $reinstateCurrentAccountName : '-' }}
                </span>
            </div>
            <input type="text"
                   wire:model.live.debounce.250ms="reinstateInstitutionKeyword"
                   placeholder="다른 기관으로 복직하려면 기관명 또는 SK 코드로 검색…"
                   autocomplete="off"
                   class="w-full py-2 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $errors->has('reinstateSkCode') ? 'border-red-400' : 'border-gray-300' }}"/>
            @if(filled(trim($reinstateInstitutionKeyword)) && $reinstateInstitutionSuggestions->isNotEmpty())
                <div class="mt-2 max-h-52 overflow-auto border border-gray-200 rounded-lg bg-white shadow-sm divide-y divide-gray-100">
                    @foreach($reinstateInstitutionSuggestions as $inst)
                        <button type="button"
                                wire:click="selectReinstateInstitution({{ json_encode($inst->SKcode) }})"
                                class="w-full px-3 py-2 text-left text-sm hover:bg-emerald-50 transition-colors cursor-pointer">
                            <span class="font-medium text-gray-900">{{ $inst->resolvedAccountName() }}</span>
                            <span class="ml-2 text-xs text-gray-500 tabular-nums">({{ $inst->SKcode }})</span>
                        </button>
                    @endforeach
                </div>
            @elseif(filled(trim($reinstateInstitutionKeyword)) && $reinstateInstitutionSuggestions->isEmpty())
                <p class="mt-2 text-xs text-gray-500">검색 결과가 없습니다. 기관명 또는 SK 코드를 확인해 주세요.</p>
            @endif
        @endif
        @error('reinstateSkCode') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        <p class="mt-1 text-xs text-gray-500">
            기관을 선택하지 않으면 기존 기관으로 복직합니다. 퇴직 당시 기관 기록은 퇴직교사 리스트에 그대로 남습니다.
        </p>
    </div>
</div>
