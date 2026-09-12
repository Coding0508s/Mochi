@php
    $observeRows = $visitForm['observe_rows'] ?? [];
    $observeAddableTypes = method_exists($this, 'visitObserveAddableTypes')
        ? $this->visitObserveAddableTypes()
        : [];
    $observeInputClass = $observeInputClass ?? 'w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500';
    $observeLabelClass = $observeLabelClass ?? 'block text-sm font-medium text-gray-700 mb-1';
    $observeTypeClass = $observeTypeClass ?? 'flex h-[34px] items-center text-sm font-semibold text-gray-800';
@endphp

<div class="space-y-3">
    @foreach($observeRows as $index => $observeRow)
        <div wire:key="visit-observe-row-{{ $observeRow['type'] ?? $index }}" class="grid grid-cols-1 gap-3 sm:grid-cols-12">
            <div class="sm:col-span-2">
                <span class="{{ $observeLabelClass }}">커리큘럼</span>
                <div class="{{ $observeTypeClass }}">{{ $observeRow['type'] ?? 'GrapeSEED' }}</div>
                <input type="hidden" wire:model="visitForm.observe_rows.{{ $index }}.type">
            </div>
            @php
                $observeFieldLabels = \App\Support\VisitObserveCurriculumRows::fieldLabels((string) ($observeRow['type'] ?? 'GrapeSEED'));
            @endphp
            <div class="sm:col-span-2">
                <label class="{{ $observeLabelClass }}">{{ $observeFieldLabels['unit'] }}</label>
                <input type="number" min="0" max="99" wire:model.blur="visitForm.observe_rows.{{ $index }}.unit"
                       class="{{ $observeInputClass }}"/>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $observeLabelClass }}">{{ $observeFieldLabels['lesson'] }}</label>
                <input type="number" min="0" max="99" wire:model.blur="visitForm.observe_rows.{{ $index }}.lesson"
                       class="{{ $observeInputClass }}"/>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $observeLabelClass }}">{{ $observeFieldLabels['class'] }}</label>
                <input type="text" wire:model.blur="visitForm.observe_rows.{{ $index }}.class"
                       class="{{ $observeInputClass }}"/>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $observeLabelClass }}">{{ $observeFieldLabels['age'] }}</label>
                <input type="text" wire:model.blur="visitForm.observe_rows.{{ $index }}.age"
                       class="{{ $observeInputClass }}"/>
            </div>
            <div class="flex items-end sm:col-span-2">
                <button type="button" wire:click="removeVisitObserveRow({{ $index }})"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                    행 삭제
                </button>
            </div>
        </div>
    @endforeach

    @if($observeAddableTypes !== [])
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs text-gray-500">참관한 커리큘럼 행 추가</span>
            @foreach($observeAddableTypes as $observeType)
                <button type="button" wire:click="addVisitObserveRow('{{ $observeType }}')"
                        class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">
                    {{ $observeType }}
                </button>
            @endforeach
        </div>
    @endif
</div>
