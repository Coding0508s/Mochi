@props([
    'closeAction',
    'saveAction',
    'markCompletedModel',
])

@php
    $viewOnly = $supportReportViewMode ?? false;
@endphp

<div class="shrink-0 px-6 py-4 border-t bg-gray-50 flex flex-wrap items-center justify-between gap-3">
    <button type="button"
            wire:click="{{ $closeAction }}"
            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-white cursor-pointer">
        {{ $viewOnly ? '닫기' : '취소하기' }}
    </button>
    @if(! $viewOnly)
        <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" wire:model="{{ $markCompletedModel }}" class="sr-only peer">
                <span class="relative w-11 h-6 rounded-full bg-gray-200 peer peer-checked:bg-mochi-header after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-5"></span>
                <span>완료처리</span>
            </label>
            <button type="button"
                    wire:click="{{ $saveAction }}"
                    class="cursor-pointer rounded-lg bg-mochi-header px-4 py-2 text-sm font-medium text-white hover:bg-mochi-header/90">
                저장하기
            </button>
        </div>
    @endif
</div>
