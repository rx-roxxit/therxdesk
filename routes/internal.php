<?php

declare(strict_types=1);

use App\Http\Controllers\Internal\AccountController;
use App\Http\Controllers\Internal\AuditLogController;
use App\Http\Controllers\Internal\HealthController;
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
});
