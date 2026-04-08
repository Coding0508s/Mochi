<?php

use App\Http\Controllers\ContractDocumentFileController;
use Illuminate\Support\Facades\Route;

// 기본 주소(/)로 접속하면 기관 리스트로 바로 이동합니다
Route::get('/', fn() => redirect('/institutions'));

// People - Employees 페이지
Route::get('/people', function () {
    return view('pages.people.index');
})->name('people.index');

// 기관 리스트 페이지
Route::get('/institutions', function () {
    return view('pages.institutions.index');
})->name('institutions.index');

// 연락처 관리 페이지
Route::get('/contacts', function () {
    return view('pages.contacts.index');
})->name('contacts.index');

// 기관 지원 내역 페이지
Route::get('/supports', function () {
    return view('pages.supports.index');
})->name('supports.index');

// 기관 지원 내역 신규 작성 페이지
Route::get('/supports/create', function () {
    return view('pages.supports.create');
})->name('supports.create');

// Salesforce 계약서 파일 조회 페이지
Route::get('/salesforce-files', function () {
    return view('pages.salesforce-files.index');
})->name('salesforce-files.index');

// 계약서 파일 (다운로드·미리보기)
Route::get('/contract-documents/{contractDocument}/download', [ContractDocumentFileController::class, 'download'])
    ->name('contract-documents.download');
Route::get('/contract-documents/{contractDocument}/preview', [ContractDocumentFileController::class, 'preview'])
    ->name('contract-documents.preview');

// 잠재기관 관리 페이지
Route::get('/potential-institutions', function () {
    return view('pages.potential-institutions.index');
})->name('potential-institutions.index');

// SetUp 페이지
Route::get('/setup', function () {
    return view('pages.setup.index');
})->name('setup.index');
