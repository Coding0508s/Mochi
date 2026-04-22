<?php

use App\Http\Controllers\ContractDocumentFileController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

/*
| GS Brochure — 공개 신청 (플랫폼 계정 없이 접근)
| 레거시 URL requestbrochure-v2 등도 동일 폼으로 연결
*/
Route::get('/co/gs-brochure', function () {
    return auth()->user()?->can('manageGsBrochureAdmin')
        ? redirect()->route('co.gs-brochure.admin.dashboard')
        : redirect()->route('co.gs-brochure.request');
})->name('co.gs-brochure');
Route::get('/co/gs-brochure/request', function (Request $request) {
    $showStaffList = $request->query('view') === 'list';
    if ($showStaffList) {
        if (! auth()->check()) {
            return redirect()->guest(route('login'));
        }

        return view('request.list');
    }

    return view('request.form-v2');
})->name('co.gs-brochure.request');
Route::get('/co/gs-brochure/request/success', function () {
    return view('request.success');
})->name('co.gs-brochure.request.success');

Route::get('/requestbrochure-v2', function () {
    return redirect()->route('co.gs-brochure.request');
})->name('gs-brochure.legacy.request');
Route::get('/requestbrochure', function () {
    return redirect()->route('co.gs-brochure.request');
})->name('gs-brochure.legacy.request.v1');
Route::get('/requestbrochure-success', function () {
    return redirect()->route('co.gs-brochure.request.success');
})->name('gs-brochure.legacy.request.success');

/** 기관명·전화번호로 본인 신청만 조회 (공개, GSBrochure/laravel/routes/web.php와 동일) */
Route::get('/requestbrochure-list-v2', function () {
    return view('request.list-v2');
})->name('gs-brochure.legacy.request.list-v2');

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

    Route::get('/co/gs-brochure/main', function () {
        return view('pages.co.gs-brochure-main');
    })->name('co.gs-brochure.main');
    Route::get('/co/gs-brochure/requests', function () {
        return redirect()->route('co.gs-brochure.request', ['view' => 'list']);
    })->name('co.gs-brochure.requests');
    Route::get('/co/gs-brochure/admin/login', function () {
        if (! Gate::allows('manageGsBrochureAdmin')) {
            return redirect()->route('co.gs-brochure.request');
        }

        return redirect()->route('co.gs-brochure.admin.dashboard');
    })->name('co.gs-brochure.admin.login');
    Route::get('/co/gs-brochure/admin/dashboard', function () {
        if (! Gate::allows('manageGsBrochureAdmin')) {
            return redirect()->route('co.gs-brochure.request');
        }

        return view('admin.dashboard');
    })->name('co.gs-brochure.admin.dashboard');

    // GS 레거시 Blade 호환 URL (목록·관리자 등 — 로그인 필요)
    Route::get('/requestbrochure-list', function () {
        return redirect()->route('co.gs-brochure.request', ['view' => 'list']);
    })->name('gs-brochure.legacy.request.list');
    Route::get('/admin/login', function () {
        if (! Gate::allows('manageGsBrochureAdmin')) {
            return redirect()->route('co.gs-brochure.request');
        }

        return redirect()->route('co.gs-brochure.admin.dashboard');
    })->name('gs-brochure.legacy.admin.login');
    Route::get('/admin/dashboard', function () {
        if (! Gate::allows('manageGsBrochureAdmin')) {
            return redirect()->route('co.gs-brochure.request');
        }

        return redirect()->route('co.gs-brochure.admin.dashboard');
    })->name('gs-brochure.legacy.admin.dashboard');

    Route::get('/store/inventory', function () {
        return view('pages.store.inventory.index');
    })->name('store.inventory.index');
    Route::get('/store/sales', function () {
        return view('pages.store.sales.index');
    })->name('store.sales.index');
    Route::get('/store/inventory/skus', function () {
        return view('pages.store.inventory.skus.index');
    })->middleware('can:manageStoreInventory')
        ->name('store.inventory.skus.index');

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
