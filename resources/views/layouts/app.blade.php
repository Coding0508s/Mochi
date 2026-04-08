<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DujjonKu 플랫폼' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-100 font-sans">

<div class="flex h-screen overflow-hidden">

    {{-- ───── 사이드바 ───── --}}
    <aside class="w-56 bg-[#1e2a3a] text-white flex flex-col flex-shrink-0">

        {{-- 로고 --}}
        <div class="h-16 flex items-center px-6 border-b border-white/10">
            <span class="text-lg font-bold tracking-wide">DujjonKu</span>
        </div>

        {{-- 메뉴 --}}
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

            <p class="px-3 text-xs text-gray-400 uppercase tracking-wider mb-2">CO 팀</p>

            <a href="/institutions"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm
                      {{ request()->is('institutions*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-white/10' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                기관 리스트
            </a>

            <a href="/contacts"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm
                      {{ request()->is('contacts*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-white/10' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                연락처 관리
            </a>

            <a href="/supports"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm
                      {{ request()->is('supports*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-white/10' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                기관 지원 내역
            </a>

        </nav>

        {{-- 하단 사용자 정보 --}}
        <div class="px-4 py-4 border-t border-white/10 text-xs text-gray-400">
            CO 팀 플랫폼 v1.0
        </div>
    </aside>

    {{-- ───── 메인 영역 ───── --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- 상단 헤더 --}}
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <h1 class="text-lg font-semibold text-gray-800">{{ $title ?? '대시보드' }}</h1>
            <div class="flex items-center gap-3 text-sm text-gray-500">
                <span>CO 팀</span>
                <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold">
                    CO
                </div>
            </div>
        </header>

        {{-- 페이지 콘텐츠 --}}
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>

    </div>
</div>

@livewireScripts
</body>
</html>
