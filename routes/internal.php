<?php

declare(strict_types=1);

use App\Http\Controllers\Internal\AccountController;
use App\Http\Controllers\Internal\AuditLogController;
use App\Http\Controllers\Internal\CatalogSettingController;
use App\Http\Controllers\Internal\HealthController;
use App\Http\Controllers\Internal\OfferController;
use App\Http\Controllers\Internal\PlanController;
use App\Http\Controllers\Internal\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('site', SiteController::class)
    ->middleware('internal')
    ->name('internal.site');

Route::middleware('internal:admin')->group(function (): void {
    Route::get('accounts', [AccountController::class, 'index'])
        ->name('internal.accounts.index');

    Route::get('accounts/{account}', [AccountController::class, 'show'])
        ->whereUuid('account')
        ->name('internal.accounts.show');

    Route::get('audit-log', [AuditLogController::class, 'index'])
        ->name('internal.audit-log.index');

    Route::get('health', HealthController::class)
        ->name('internal.health');

    Route::get('plans', [PlanController::class, 'index'])
        ->name('internal.plans.index');

    Route::post('plans', [PlanController::class, 'store'])
        ->name('internal.plans.store');

    Route::patch('plans/{plan}', [PlanController::class, 'update'])
        ->whereUuid('plan')
        ->name('internal.plans.update');

    Route::post('plans/{plan}/archive', [PlanController::class, 'archive'])
        ->whereUuid('plan')
        ->name('internal.plans.archive');

    Route::post('plans/{plan}/quota-cut-dry-run', [PlanController::class, 'quotaCutDryRun'])
        ->whereUuid('plan')
        ->name('internal.plans.quota-cut-dry-run');

    Route::get('catalog-settings', [CatalogSettingController::class, 'show'])
        ->name('internal.catalog-settings.show');

    Route::patch('catalog-settings', [CatalogSettingController::class, 'update'])
        ->name('internal.catalog-settings.update');

    Route::get('offers', [OfferController::class, 'index'])
        ->name('internal.offers.index');

    Route::post('offers', [OfferController::class, 'store'])
        ->name('internal.offers.store');

    Route::patch('offers/{offer}', [OfferController::class, 'update'])
        ->whereUuid('offer')
        ->name('internal.offers.update');

    Route::post('offers/{offer}/end', [OfferController::class, 'end'])
        ->whereUuid('offer')
        ->name('internal.offers.end');
});
