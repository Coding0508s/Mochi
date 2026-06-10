@props([
    'closeAction',
    'saveAction',
    'markCompletedModel',
])

@php
    $viewOnly = $supportReportViewMode ?? false;
    $isEditingExisting = filled($viewingSupportReportDetailKey ?? null);
    $cancelAction = ($isEditingExisting && ! $viewOnly) ? 'cancelSupportReportEdit' : $closeAction;
    $cancelLabel = match (true) {
        $isEditingExisting && ! $viewOnly => '취소',
        $viewOnly => '닫기',
        default => '취소하기',
    };
    $markCompleted = (bool) ($this->{$markCompletedModel} ?? false);
@endphp

<div class="shrink-0 px-6 py-4 border-t bg-gray-50 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        @if($viewOnly && $this->canEditViewingSupportReport())
            <button type="button"
                    wire:click="startSupportReportEdit"
                    class="cursor-pointer rounded-lg border border-amber-300 px-4 py-2 text-sm text-amber-800 hover:bg-amber-50">
                수정
            </button>
        @endif
        <button type="button"
                wire:click="{{ $cancelAction }}"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-white cursor-pointer">
            {{ $cancelLabel }}
        </button>
    </div>
    @if(! $viewOnly)
        <div class="flex flex-wrap items-center gap-4">
            @error('supportReportEdit')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('support_round')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            @if($markCompleted)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <span class="whitespace-nowrap">현황 차수</span>
                    <select wire:model="supportRound"
                            class="min-w-[7rem] rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-mochi-header">
                        <option value="">기록 안 함</option>
                        @foreach([1, 2, 3, 4] as $round)
                            <option value="{{ $round }}" @disabled(in_array($round, $supportRoundRecorded ?? [], true))>
                                {{ $round }}차@if(in_array($round, $supportRoundRecorded ?? [], true)) (기록됨)@endif
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" wire:model.live="{{ $markCompletedModel }}" class="sr-only peer">
                <span class="relative w-11 h-6 rounded-full bg-gray-200 peer peer-checked:bg-mochi-header after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-5"></span>
                <span>완료처리</span>
            </label>
            <button type="button"
                    wire:click="{{ $saveAction }}"
                    class="cursor-pointer rounded-lg bg-mochi-header px-4 py-2 text-sm font-medium text-white hover:bg-mochi-header/90">
                {{ $isEditingExisting ? '수정 저장' : '저장하기' }}
            </button>
        </div>
    @endif
</div>
