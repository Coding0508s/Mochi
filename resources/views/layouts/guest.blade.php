<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Chromium: 동일 출처 전환 시 카드(authcard) 연속성 + 루트 크로스페이드 (MPA View Transitions) --}}
        <style>
            @media (prefers-reduced-motion: no-preference) {
                @view-transition {
                    navigation: auto;
                }
            }

            @supports (view-transition-name: none) {
                @media (prefers-reduced-motion: no-preference) {
                    .mochi-auth-card-view-transition {
                        view-transition-name: authcard;
                    }

                    ::view-transition-old(authcard),
                    ::view-transition-new(authcard) {
                        animation-duration: 450ms;
                        animation-timing-function: cubic-bezier(0.25, 0.46, 0.45, 0.94);
                    }

                    ::view-transition-old(root),
                    ::view-transition-new(root) {
                        animation-duration: 280ms;
                        animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
                    }
                }
            }
        </style>
    </head>
    <body
        class="min-h-screen antialiased text-slate-900"
        style="font-family: Inter, ui-sans-serif, system-ui, sans-serif"
    >
        <div
            class="min-h-screen flex flex-col items-center justify-center px-4 py-10 sm:py-12
                   bg-gradient-to-br from-slate-50 via-blue-50 to-mochi-excel/20"
        >
            {{ $slot }}
        </div>
    </body>
</html>
