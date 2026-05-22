@props([
    'title',
    'subtitle' => null,
    'closeAction' => null,
    'closeLabel' => '닫기',
])

<div {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4']) }}>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
            @isset($titleAddon)
                {{ $titleAddon }}
            @endisset
        </div>
        @if(filled($subtitle))
            <p class="mt-0.5 text-xs text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="ml-3 flex shrink-0 items-center gap-2">
        @isset($actions)
            {{ $actions }}
        @endisset
        @if(filled($closeAction))
            <button
                type="button"
                wire:click="{{ $closeAction }}"
                class="cursor-pointer text-gray-400 transition-colors hover:text-gray-600"
                aria-label="{{ $closeLabel }}"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        @endif
    </div>
</div>
