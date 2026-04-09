@props([
    'name',
    'id',
    'type' => 'text',
    'label',
    'value' => '',
    'autocomplete' => null,
    'placeholder' => '',
    'required' => false,
    'autofocus' => false,
])

@php
    $chars = $label === '' ? [] : mb_str_split($label, 1);
    $isInitiallyActive = filled($value);
@endphp

<div
    {{ $attributes->class([
        'mochi-floating-input relative',
        'mochi-floating-input--has-icon' => isset($icon),
        'is-active' => $isInitiallyActive,
    ]) }}
    data-mochi-floating-input
    @if (filled($placeholder)) data-mochi-floating-ph @endif
>
    @isset($icon)
        <span
            class="pointer-events-none absolute left-0 top-1/2 z-[3] -translate-y-1/2 text-mochi-header [&_svg]:size-[18px]"
            aria-hidden="true"
        >
            {{ $icon }}
        </span>
    @endisset

    {{-- input을 먼저 두어 :not(:placeholder-shown) / :-webkit-autofill → 형제(~) 라벨 선택자 사용 (:has보다 Chrome 자동완성에서 안정적) --}}
    <div class="@isset($icon) pl-11 @endisset relative">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $value }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            placeholder="{{ $placeholder }}"
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            data-mochi-floating-input-control
            class="mochi-floating-input__control relative z-[2] w-full border-0 border-b-2 border-mochi-header/85 bg-transparent py-2.5 text-base font-medium text-slate-900 outline-none transition-[border-color,box-shadow] duration-200 placeholder:text-transparent focus:border-mochi-excel focus:shadow-[0_1px_0_0_rgba(94,184,232,0.4)]"
        />
        <div
            class="mochi-floating-input__letters pointer-events-none absolute top-1/2 z-[1] -translate-y-1/2 select-none"
            aria-hidden="true"
        >@foreach ($chars as $index => $char)<span
                    class="mochi-floating-input__char text-sm font-medium"
                    style="--mfi-char: {{ $index }}"
                >{{ $char === ' ' ? "\u{00A0}" : $char }}</span>@endforeach
        </div>
    </div>
</div>
