@props([
    'type' => 'submit',
    'filterId' => 'container-glass',
    'pill' => false,
    /** neutral: 밝은 배경용 어두운 글라스 / mochi-blue: 모키 블루 그라데이션 + 라이트 글라스 */
    'variant' => 'neutral',
])

@php
    $round = $pill ? 'rounded-full' : 'rounded-md';
    $baseClasses = 'relative isolate inline-flex items-center justify-center cursor-pointer gap-2 whitespace-nowrap text-sm font-medium transition-[color,box-shadow,transform] motion-reduce:transition-[color,box-shadow] motion-reduce:hover:scale-100 disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:ring-2 focus-visible:ring-offset-2 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 ' . $round;

    $variantClasses = match ($variant) {
        'mochi-blue' => 'border border-white/25 bg-gradient-to-b from-[#4a9ae8] via-mochi-header to-[#1e5696] text-white shadow-[0_8px_28px_rgba(43,120,197,0.38),0_2px_8px_rgba(30,75,156,0.22)] hover:scale-[1.02] hover:shadow-[0_12px_36px_rgba(43,120,197,0.45),0_4px_12px_rgba(30,75,156,0.28)] duration-300 focus-visible:ring-mochi-excel/70 focus-visible:ring-offset-white',
        default => 'bg-transparent hover:scale-[1.02] duration-300 transition text-mochi-header focus-visible:ring-mochi-header/40',
    };

    $depthClasses = match ($variant) {
        'mochi-blue' => 'opacity-100 shadow-[0_0_10px_rgba(43,120,197,0.2),0_2px_10px_rgba(43,120,197,0.12),inset_2px_2px_0.5px_-2px_rgba(255,255,255,0.45),inset_-2px_-2px_0.5px_-2px_rgba(15,60,120,0.18),inset_1px_1px_1px_-0.5px_rgba(255,255,255,0.35),inset_-1px_-1px_1px_-0.5px_rgba(30,90,160,0.12),inset_0_0_8px_8px_rgba(255,255,255,0.08),inset_0_0_2px_2px_rgba(230,242,252,0.35),0_0_20px_rgba(94,184,232,0.22)]',
        default => 'shadow-[0_0_6px_rgba(0,0,0,0.03),0_2px_6px_rgba(0,0,0,0.08),inset_3px_3px_0.5px_-3px_rgba(0,0,0,0.9),inset_-3px_-3px_0.5px_-3px_rgba(0,0,0,0.85),inset_1px_1px_1px_-0.5px_rgba(0,0,0,0.6),inset_-1px_-1px_1px_-0.5px_rgba(0,0,0,0.6),inset_0_0_6px_6px_rgba(0,0,0,0.12),inset_0_0_2px_2px_rgba(0,0,0,0.06),0_0_12px_rgba(255,255,255,0.15)] dark:shadow-[0_0_8px_rgba(0,0,0,0.03),0_2px_6px_rgba(0,0,0,0.08),inset_3px_3px_0.5px_-3.5px_rgba(255,255,255,0.09),inset_-3px_-3px_0.5px_-3.5px_rgba(255,255,255,0.85),inset_1px_1px_1px_-0.5px_rgba(255,255,255,0.6),inset_-1px_-1px_1px_-0.5px_rgba(255,255,255,0.6),inset_0_0_6px_6px_rgba(255,255,255,0.12),inset_0_0_2px_2px_rgba(255,255,255,0.06),0_0_12px_rgba(0,0,0,0.15)]',
    };

    $blurOpacity = $variant === 'mochi-blue' ? 'opacity-28' : '';

    $sizeClasses = $pill
        ? 'h-12 w-full px-8 has-[>svg]:px-6'
        : 'h-10 min-w-[7rem] px-6 has-[>svg]:px-4';
@endphp

<button
    type="{{ $type }}"
    data-slot="button"
    {{ $attributes->class([$baseClasses, $variantClasses, $sizeClasses]) }}
>
    <div
        class="absolute inset-0 z-0 {{ $round }} transition-opacity {{ $depthClasses }}"
        aria-hidden="true"
    ></div>
    <div
        class="absolute inset-0 -z-10 h-full w-full overflow-hidden {{ $round }} {{ $blurOpacity }}"
        style="backdrop-filter: url('#{{ $filterId }}'); -webkit-backdrop-filter: url('#{{ $filterId }}')"
        aria-hidden="true"
    ></div>
    <span class="pointer-events-none relative z-10 inline-flex w-full items-center justify-center gap-2 font-semibold">{{ $slot }}</span>
    <svg class="pointer-events-none absolute h-px w-px overflow-hidden opacity-0" aria-hidden="true" focusable="false">
        <defs>
            <filter
                id="{{ $filterId }}"
                x="0%"
                y="0%"
                width="100%"
                height="100%"
                color-interpolation-filters="sRGB"
            >
                <feTurbulence
                    type="fractalNoise"
                    baseFrequency="0.05 0.05"
                    numOctaves="1"
                    seed="1"
                    result="turbulence"
                />
                <feGaussianBlur in="turbulence" stdDeviation="2" result="blurredNoise" />
                <feDisplacementMap
                    in="SourceGraphic"
                    in2="blurredNoise"
                    scale="70"
                    xChannelSelector="R"
                    yChannelSelector="B"
                    result="displaced"
                />
                <feGaussianBlur in="displaced" stdDeviation="4" result="finalBlur" />
                <feComposite in="finalBlur" in2="finalBlur" operator="over" />
            </filter>
        </defs>
    </svg>
</button>
