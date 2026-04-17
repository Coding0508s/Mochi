@extends('layouts.shell-no-sidebar')

@section('title', '관리자 로그인')

@section('sidebar-footer-label', '관리자 페이지')

@section('content')
    <div class="max-w-4xl mx-auto mt-10 w-1/4">
        <header class="mb-3 text-center">
            <h1 class="text-3xl font-bold text-primary dark:text-purple-400 mb-2">관리자 로그인</h1>
            <p class="text-gray-600 dark:text-gray-300">관리자 계정으로 로그인하세요.</p>
        </header>

        <div id="alert" class="mb-6 hidden rounded-lg border p-4 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200" role="alert"></div>

        <form id="loginForm" class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark p-6 space-y-5" autocomplete="off">
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="username">아이디</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="off"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5">
            </div>
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="password">비밀번호</label>
                <input type="password" id="password" name="password" required autocomplete="off"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm focus:border-primary focus:ring-primary dark:text-white sm:text-sm py-2.5">
            </div>
            <button type="submit" class="w-full px-6 py-3 bg-primary hover:bg-purple-800 text-white font-semibold rounded-lg transition-colors">
                로그인
            </button>
        </form>

        <p class="mt-6 text-center">
            <a href="{{ url('/') }}" class="text-primary hover:underline text-sm">← 메인 페이지로 돌아가기</a>
        </p>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/gs-brochure-api.js') }}"></script>
<script>
    function showAlert(message, type) {
        const alertDiv = document.getElementById('alert');
        alertDiv.className = 'mb-6 rounded-lg border p-4 ' + (type === 'danger' ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200' : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200');
        alertDiv.textContent = message;
        alertDiv.classList.remove('hidden');
        setTimeout(function() { alertDiv.classList.add('hidden'); }, 3000);
    }
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        var username = document.getElementById('username').value;
        var password = document.getElementById('password').value;
        try {
            var result = await AdminAPI.login(username, password);
            if (result.success) {
                sessionStorage.setItem('admin_logged_in', 'true');
                sessionStorage.setItem('admin_username', username);
                window.location.href = '{{ route("co.gs-brochure.admin.dashboard") }}';
            } else {
                showAlert('아이디 또는 비밀번호가 올바르지 않습니다.', 'danger');
            }
        } catch (error) {
            var errorMessage = '로그인 요청 중 오류가 발생했습니다.';
            if (error instanceof TypeError && error.message && error.message.indexOf('fetch') !== -1) {
                errorMessage = '서버에 연결할 수 없습니다. 네트워크 연결을 확인해주세요.';
            } else if (error.message) {
                errorMessage = error.message;
            }
            showAlert(errorMessage, 'danger');
        }
    });
</script>
@endpush
