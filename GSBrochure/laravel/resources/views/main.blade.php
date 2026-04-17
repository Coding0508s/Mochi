@extends('layouts.shell-no-sidebar')

@section('title', 'GrapeSEED Brochure')

@section('content')
    <div id="main-page-content" class="w-full max-w-2xl mx-auto text-center flex flex-col items-center justify-center transition-opacity duration-300 ease-out">
        <header class="mb-8 opacity-0 animate-fade-in-up">
            <h1 class="text-4xl font-bold text-primary dark:text-purple-400 mb-2">GrapeSEED Brochure</h1>
            <h2 class="text-2xl font-bold text-primary dark:text-purple-400 mb-2">Management System</h2>
            <p class="text-gray-600 dark:text-gray-300 mb-2">원하시는 서비스를 선택해 주세요.</p>
        </header>
        <div class="opacity-0 animate-fade-in-up-delay flex flex-wrap justify-center gap-4">
            <a href="{{ url('requestbrochure-v2') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-primary hover:bg-primary/90 text-white font-semibold rounded-lg transition-all duration-200 shadow-sm hover:-translate-y-1 hover:shadow-lg">
                <span class="material-icons text-xl">description</span>
                브로셔 신청
            </a>
            <a href="{{ url('admin/login') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-primary hover:bg-primary/90 text-white font-semibold rounded-lg transition-all duration-200 shadow-sm hover:-translate-y-1 hover:shadow-lg">
                <span class="material-icons text-xl">settings</span>
                관리자 페이지
            </a>
        </div>
    </div>
    @push('scripts')
    <script>
        (function() {
            var wrapper = document.getElementById('main-page-content');
            if (!wrapper) return;
            wrapper.querySelectorAll('a[href]:not([href^="#"])').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    if (link.target === '_blank' || link.hasAttribute('download')) return;
                    e.preventDefault();
                    var url = link.href;
                    wrapper.classList.add('opacity-0');
                    setTimeout(function() { window.location.href = url; }, 300);
                });
            });
        })();
    </script>
    @endpush
@endsection
