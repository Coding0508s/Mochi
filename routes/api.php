<?php

use App\GsBrochure\Http\Controllers\Api\AdminController;
use App\GsBrochure\Http\Controllers\Api\BrochureController;
use App\GsBrochure\Http\Controllers\Api\ContactController;
use App\GsBrochure\Http\Controllers\Api\InstitutionController;
use App\GsBrochure\Http\Controllers\Api\RequestController;
use App\GsBrochure\Http\Controllers\Api\ResetDataController;
use App\GsBrochure\Http\Controllers\Api\StockHistoryController;
use App\GsBrochure\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\ExternalInstitutionController;
use Illuminate\Support\Facades\Route;

/*
| CRM 기관 마스터(S_AccountName) 외부 연동 — gs-brochure API 와 분리
*/
Route::middleware(['external.institution.ingest', 'throttle:external-institution-ingest'])
    ->put('internal/institutions/{sk}', [ExternalInstitutionController::class, 'upsert'])
    ->where('sk', '[A-Za-z0-9._\-]+');

Route::prefix('gs-brochure')->group(function () {
    Route::get('/health', [BrochureController::class, 'health']);

    Route::post('verification/send', [VerificationController::class, 'sendCode']);
    Route::post('verification/verify', [VerificationController::class, 'verify']);

    Route::get('brochures', [BrochureController::class, 'index']);

    Route::get('requests/search', [RequestController::class, 'search']);
    Route::post('requests', [RequestController::class, 'store']);

    Route::get('institutions', [InstitutionController::class, 'listPublic']);
    Route::post('admin/login', [AdminController::class, 'login']);

    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('requests', [RequestController::class, 'index']);
        Route::put('requests/{id}', [RequestController::class, 'update']);
        Route::get('contacts', [ContactController::class, 'index']);
    });

    Route::middleware(['web', 'auth', 'can:manageGsBrochureAdmin'])->group(function () {
        Route::post('requests/{id}/invoices', [RequestController::class, 'addInvoices']);
        Route::delete('requests/{id}/invoices', [RequestController::class, 'deleteInvoices']);
        Route::get('stock-history', [StockHistoryController::class, 'index']);
    });

    Route::middleware(['web', 'auth', 'can:manageGsBrochureAdmin'])->group(function () {
        Route::post('brochures', [BrochureController::class, 'store']);
        Route::put('brochures/{id}', [BrochureController::class, 'update']);
        Route::delete('brochures/{id}', [BrochureController::class, 'destroy']);
        Route::put('brochures/{id}/stock', [BrochureController::class, 'updateStock']);
        Route::put('brochures/{id}/stock-warehouse', [BrochureController::class, 'updateWarehouseStock']);
        Route::put('brochures/{id}/transfer-to-hq', [BrochureController::class, 'transferToHq']);
        Route::post('brochures/{id}/image', [BrochureController::class, 'uploadImage']);
        Route::delete('brochures/{id}/image', [BrochureController::class, 'deleteImage']);
        Route::post('contacts', [ContactController::class, 'store']);
        Route::put('contacts/{id}', [ContactController::class, 'update']);
        Route::delete('contacts/{id}', [ContactController::class, 'destroy']);
        Route::delete('requests/{id}', [RequestController::class, 'destroy']);
        Route::post('stock-history', [StockHistoryController::class, 'store']);
        Route::get('admin/users', [AdminController::class, 'users']);
        Route::post('admin/users', [AdminController::class, 'createUser']);
        Route::put('admin/users/{id}/password', [AdminController::class, 'changePassword']);
        Route::delete('admin/users/{id}', [AdminController::class, 'deleteUser']);
        Route::post('admin/reset', [ResetDataController::class, 'reset']);
        Route::get('admin/institutions', [InstitutionController::class, 'index']);
        Route::patch('admin/institutions/bulk', [InstitutionController::class, 'bulkUpdateIsActive']);
        Route::get('admin/institutions/{id}', [InstitutionController::class, 'show']);
        Route::post('admin/institutions', [InstitutionController::class, 'store']);
        Route::put('admin/institutions/{id}', [InstitutionController::class, 'update']);
        Route::delete('admin/institutions/{id}', [InstitutionController::class, 'destroy']);
    });
});
