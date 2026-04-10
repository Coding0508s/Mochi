<?php

use App\Http\Controllers\ContractDocumentFileController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Breeze 로그인 후 의도 URL — dashboard 이름 유지
*/
Route::middleware(['auth'])->get('/dashboard', function () {
    return redirect()->route('institutions.index');
})->name('dashboard');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('institutions.index')
        : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/people', function () {
        return view('pages.people.index');
    })->name('people.index');

    Route::get('/institutions', function () {
        return view('pages.institutions.index');
    })->name('institutions.index');

    Route::get('/institutions/create', function () {
        if (! config('features.institution_create_enabled')) {
            return redirect()
                ->route('institutions.index')
                ->with('warning', '신규 기관 등록이 비활성화되어 있습니다.');
        }

        return view('pages.institutions.create');
    })->name('institutions.create');

    Route::get('/contacts', function () {
        return view('pages.contacts.index');
    })->name('contacts.index');

    Route::get('/supports', function () {
        return view('pages.supports.index');
    })->name('supports.index');

    Route::get('/supports/create', function () {
        return view('pages.supports.create');
    })->name('supports.create');

    Route::get('/salesforce-files', function () {
        return view('pages.salesforce-files.index');
    })->name('salesforce-files.index');

    Route::get('/contract-documents/{contractDocument}/download', [ContractDocumentFileController::class, 'download'])
        ->name('contract-documents.download');
    Route::get('/contract-documents/{contractDocument}/preview', [ContractDocumentFileController::class, 'preview'])
        ->name('contract-documents.preview');

    Route::get('/potential-institutions', function () {
        return view('pages.potential-institutions.index');
    })->name('potential-institutions.index');

    Route::get('/potential-institutions/view', function () {
        return view('pages.potential-institutions.view');
    })->name('potential-institutions.view');

    Route::get('/setup', function () {
        return view('pages.setup.index');
    })->name('setup.index');

    Route::get('/setup/team', function () {
        return view('pages.setup.team');
    })->name('setup.team');

    Route::get('/setup/common-codes', function () {
        return view('pages.setup.common-codes');
    })->name('setup.common-codes');

    Route::get('/setup/roles', function () {
        return view('pages.setup.roles');
    })->name('setup.roles');

    Route::get('/setup/employees/create', function () {
        return view('pages.setup.employee-create');
    })->name('setup.employees.create');
});

require __DIR__.'/auth.php';
