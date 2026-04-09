@props([
    'type' => 'submit',
    'filterId' => 'container-glass',
])

@php
    $baseClasses = 'relative inline-flex items-center justify-center cursor-pointer gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-[color,box-shadow] disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-mochi-header/40 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0';
    $variantClasses = 'bg-transparent hover:scale-105 duration-300 transition text-mochi-header';
    $sizeClasses = 'h-10 rounded-md px-6 has-[>svg]:px-4 min-w-[7rem]';
@endphp

<button
    type="{{ $type }}"
    data-slot="button"
    {{ $attributes->class([$baseClasses, $variantClasses, $sizeClasses]) }}
>
    <div
        class="absolute top-0 left-0 z-0 h-full w-full rounded-full shadow-[0_0_6px_rgba(0,0,0,0.03),0_2px_6px_rgba(0,0,0,0.08),inset_3px_3px_0.5px_-3px_rgba(0,0,0,0.9),inset_-3px_-3px_0.5px_-3px_rgba(0,0,0,0.85),inset_1px_1px_1px_-0.5px_rgba(0,0,0,0.6),inset_-1px_-1px_1px_-0.5px_rgba(0,0,0,0.6),inset_0_0_6px_6px_rgba(0,0,0,0.12),inset_0_0_2px_2px_rgba(0,0,0,0.06),0_0_12px_rgba(255,255,255,0.15)] transition-all dark:shadow-[0_0_8px_rgba(0,0,0,0.03),0_2px_6px_rgba(0,0,0,0.08),inset_3px_3px_0.5px_-3.5px_rgba(255,255,255,0.09),inset_-3px_-3px_0.5px_-3.5px_rgba(255,255,255,0.85),inset_1px_1px_1px_-0.5px_rgba(255,255,255,0.6),inset_-1px_-1px_1px_-0.5px_rgba(255,255,255,0.6),inset_0_0_6px_6px_rgba(255,255,255,0.12),inset_0_0_2px_2px_rgba(255,255,255,0.06),0_0_12px_rgba(0,0,0,0.15)]"
        aria-hidden="true"
    ></div>
    <div
        class="absolute top-0 left-0 isolate -z-10 h-full w-full overflow-hidden rounded-md"
        style="backdrop-filter: url('#{{ $filterId }}')"
        aria-hidden="true"
    ></div>
    <span class="pointer-events-none relative z-10 font-semibold">{{ $slot }}</span>
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
