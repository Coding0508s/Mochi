<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}"/>
    <title>@yield('title', 'GrapeSEED Brochure')</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#590091",
                        secondary: "#28a745",
                        "background-light": "#F9FAFB",
                        "background-dark": "#111827",
                        "surface-light": "#FFFFFF",
                        "surface-dark": "#1F2937",
                        "border-light": "#E5E7EB",
                        "border-dark": "#374151",
                    },
                    fontFamily: {
                        display: ["Inter", "sans-serif"],
                        sans: ["Inter", "sans-serif"],
                    },
                    borderRadius: {
                        DEFAULT: "0.5rem",
                        lg: "0.75rem",
                        xl: "1rem",
                    },
                },
            },
        };
    </script>
    <script src="https://t1.kakaocdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
    {{-- Tailwind CDN은 Blade 조건부에만 등장하는 hover 유틸을 누락하는 경우가 있어 사이드바 호버는 명시 CSS로 처리 --}}
    <style>
        .shell-sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.25rem;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
            color: rgb(75 85 99);
        }
        html.dark .shell-sidebar-link {
            color: rgb(156 163 175);
        }
        .shell-sidebar-link:hover {
            background-color: rgb(249 250 251);
        }
        html.dark .shell-sidebar-link:hover {
            background-color: rgb(31 41 55);
        }
        .shell-sidebar-link.shell-sidebar-link--active {
            background-color: rgba(89, 0, 145, 0.1);
            color: #590091;
        }
        html.dark .shell-sidebar-link.shell-sidebar-link--active {
            background-color: rgba(89, 0, 145, 0.22);
            color: rgb(196 181 253);
        }
        .shell-sidebar-link.shell-sidebar-link--active:hover {
            background-color: rgba(89, 0, 145, 0.16);
        }
        html.dark .shell-sidebar-link.shell-sidebar-link--active:hover {
            background-color: rgba(89, 0, 145, 0.32);
        }
    </style>
    @stack('styles')
</head>
<body class="bg-background-light dark:bg-background-dark text-gray-900 dark:text-gray-100 font-sans transition-colors duration-200">
    @php
        $path = request()->path();
        $navRequest = ($path === 'requestbrochure' || $path === 'requestbrochure-v2');
        $navList = ($path === 'requestbrochure-list');
        $navListV2 = ($path === 'requestbrochure-list-v2' || $path === 'co/gs-brochure/requests');
        $navLogistics = ($path === 'requestbrochure-logistics');
        $navAdmin = (str_starts_with($path, 'admin'));
        $navMain = ($path === '' || $path === '/');
    @endphp
    <div class="flex min-h-screen">
        <aside class="w-64 bg-surface-light dark:bg-surface-dark border-r border-border-light dark:border-border-dark hidden md:flex flex-col fixed h-full z-10">
            <div class="p-6 border-b border-border-light dark:border-border-dark flex justify-center">
                <img src="{{ asset('images/grapeseed-logo.png') }}" alt="GrapeSEED" class="max-h-16 w-auto object-contain" />
            </div>
            <nav class="shell-sidebar-nav flex-1 overflow-y-auto py-6 px-4 space-y-1">
                <!-- <a class="flex items-center gap-3 px-4 py-3 text-sm font-medium {{ $navRequest ? 'bg-primary/10 text-primary rounded-lg dark:bg-primary/20' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors' }}" href="{{ url('requestbrochure-v2') }}">
                    <span class="material-icons text-xl">description</span>
                    브로셔 신청
                </a> -->
                <!-- <a class="flex items-center gap-3 px-4 py-3 text-sm font-medium {{ $navList ? 'bg-primary/10 text-primary rounded-lg dark:bg-primary/20' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors' }}" href="{{ url('requestbrochure-list') }}">
                    <span class="material-icons text-xl">history</span>
                    신청 내역 조회
                </a> -->
                <a class="shell-sidebar-link {{ $navListV2 ? 'shell-sidebar-link--active' : '' }}" href="{{ url('requestbrochure-list') }}">
                    <span class="material-icons text-xl">person_search</span>
                    신청 내역 조회 (전체)
                </a>
                <!-- <a class="flex items-center gap-3 px-4 py-3 text-sm font-medium {{ $navLogistics ? 'bg-primary/10 text-primary rounded-lg dark:bg-primary/20' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors' }}" href="{{ url('requestbrochure-logistics') }}">
                    <span class="material-icons text-xl">input</span>
                    운송장 번호 입력
                </a> -->
                @if($path !== 'requestbrochure' && $path !== 'requestbrochure-list' && $path !== 'requestbrochure-list-v2' && $path !== 'requestbrochure-v2')
                <a class="shell-sidebar-link {{ $navAdmin ? 'shell-sidebar-link--active' : '' }}" href="{{ url('admin/login') }}">
                    <span class="material-icons text-xl">settings</span>
                    관리자 페이지
                </a>
                @endif

            </nav>
           <!--  <a class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors mt-auto" href="{{ url('/') }}">
                <span class="material-icons text-xl">home</span>
                메인으로 돌아가기
            </a> -->
            <div class="p-4 border-t border-border-light dark:border-border-dark">
                <div class="flex items-center gap-3 px-4 py-2">
                    <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                        <span class="material-icons text-gray-500 dark:text-gray-400 text-sm">person</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">User</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">@yield('sidebar-footer-label', 'GrapeSEED Brochure')</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 md:ml-64 p-4 md:p-8 lg:p-12 overflow-y-auto">
            @yield('content')
        </main>
    </div>

    <script>window.API_BASE_URL = '{{ url("/api/gs-brochure") }}';</script>
    @stack('scripts')
</body>
</html>
