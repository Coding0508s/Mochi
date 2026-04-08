<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GrapeSEED DujjonKu') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|noto-sans-kr:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">
    <header class="flex h-12 items-center justify-between bg-mochi-header px-4 text-sm text-white shadow-sm">
        <div class="flex items-center gap-6">
            <span class="text-base font-semibold tracking-tight">GrapeSEED DujjonKu</span>
        </div>
        <nav class="hidden items-center gap-5 md:flex">
            <a href="#" class="opacity-90 hover:opacity-100">OutLook</a>
            <a href="#" class="opacity-90 hover:opacity-100">Portal</a>
            <a href="#" class="opacity-90 hover:opacity-100">eCount</a>
            <a href="#" class="opacity-90 hover:opacity-100">Coaching</a>
        </nav>
        <div class="flex items-center gap-2">
            <span class="hidden sm:inline text-sm">Andrew Hur</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-xs font-medium">AH</span>
            <svg class="h-4 w-4 opacity-80" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-3rem)]">
        @include('partials.admin.sidebar-co-nav')
        <main class="min-w-0 flex-1 overflow-auto bg-gray-50 p-6">
            @yield('content')
        </main>
    </div>
</body>
</html>
