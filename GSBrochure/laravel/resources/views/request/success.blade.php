@extends('layouts.shell-no-sidebar')

@section('title', '브로셔 신청 완료')

@section('sidebar-footer-label', '브로셔 신청')

@section('content')
    <div class="w-full max-w-2xl mx-auto text-center py-12">
        <div class="mb-8 flex justify-center">
            <span class="flex items-center justify-center w-20 h-20 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400">
                <span class="material-icons text-5xl">check_circle</span>
            </span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">브로셔 신청이 완료되었습니다</h1>
        <p class="text-gray-600 dark:text-gray-300 mb-10">신청하신 브로셔는 최대 3일 이내에 발송됩니다.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('co.gs-brochure.request') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-primary hover:bg-purple-800 text-white font-medium rounded-lg shadow-sm transition-colors">
                <span class="material-icons text-xl">add_circle_outline</span>
                추가 신청
            </a>
            <a href="{{ route('co.gs-brochure.requests') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 border-2 border-primary text-primary hover:bg-primary/10 dark:hover:bg-primary/20 font-medium rounded-lg transition-colors">
                <span class="material-icons text-xl">list_alt</span>
                신청 내역 조회
            </a>
        </div>
    </div>
@endsection
