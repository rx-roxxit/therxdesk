<?php

declare(strict_types=1);

use App\Http\Controllers\Internal\AccountAiCreditsController;
use App\Http\Controllers\Internal\AccountController;
use App\Http\Controllers\Internal\AccountDeletionController;
use App\Http\Controllers\Internal\AccountSuspensionController;
use App\Http\Controllers\Internal\AiBalanceController;
use App\Http\Controllers\Internal\AiLedgerController;
use App\Http\Controllers\Internal\AiModelController;
use App\Http\Controllers\Internal\AiSettingController;
use App\Http\Controllers\Internal\AuditLogController;
use App\Http\Controllers\Internal\AvailabilityController;
use App\Http\Controllers\Internal\CatalogSettingController;
use App\Http\Controllers\Internal\ComplimentaryPlanController;
use App\Http\Controllers\Internal\HealthController;
use App\Http\Controllers\Internal\OfferController;
use App\Http\Controllers\Internal\OperatorController;
use App\Http\Controllers\Internal\PlanController;
use App\Http\Controllers\Internal\SiteController;
use App\Http\Controllers\Internal\SupportConversationController;
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

    Route::post('accounts/{account}/complimentary', [ComplimentaryPlanController::class, 'store'])
        ->whereUuid('account')
        ->name('internal.accounts.complimentary.store');

    Route::post('accounts/{account}/complimentary/end', [ComplimentaryPlanController::class, 'end'])
        ->whereUuid('account')
        ->name('internal.accounts.complimentary.end');

    Route::post('accounts/{account}/suspension', [AccountSuspensionController::class, 'store'])
        ->whereUuid('account')
        ->name('internal.accounts.suspension.store');

    Route::post('accounts/{account}/suspension/lift', [AccountSuspensionController::class, 'lift'])
        ->whereUuid('account')
        ->name('internal.accounts.suspension.lift');

    Route::patch('accounts/{account}/ai-credits', [AccountAiCreditsController::class, 'update'])
        ->whereUuid('account')
        ->name('internal.accounts.ai-credits.update');

    Route::post('accounts/{account}/ai-credits/reset', [AccountAiCreditsController::class, 'reset'])
        ->whereUuid('account')
        ->name('internal.accounts.ai-credits.reset');

    Route::post('accounts/{account}/delete', [AccountDeletionController::class, 'store'])
        ->whereUuid('account')
        ->name('internal.accounts.delete');

    Route::get('audit-log', [AuditLogController::class, 'index'])
        ->name('internal.audit-log.index');

    Route::get('health', HealthController::class)
        ->name('internal.health');

    Route::get('operator', [OperatorController::class, 'show'])
        ->name('internal.operator.show');

    Route::post('operator', [OperatorController::class, 'store'])
        ->name('internal.operator.store');

    Route::patch('operator', [OperatorController::class, 'update'])
        ->name('internal.operator.update');

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

    Route::get('ai/models', [AiModelController::class, 'index'])
        ->name('internal.ai.models.index');

    Route::post('ai/models/test', [AiModelController::class, 'test'])
        ->name('internal.ai.models.test');

    Route::get('ai/settings', [AiSettingController::class, 'show'])
        ->name('internal.ai.settings.show');

    Route::patch('ai/settings', [AiSettingController::class, 'update'])
        ->name('internal.ai.settings.update');

    Route::get('ai/balances', [AiBalanceController::class, 'index'])
        ->name('internal.ai.balances.index');

    Route::get('ai/ledger', [AiLedgerController::class, 'index'])
        ->name('internal.ai.ledger.index');

    Route::get('availability', [AvailabilityController::class, 'index'])
        ->name('internal.availability.index');

    Route::patch('availability/{key}', [AvailabilityController::class, 'update'])
        ->name('internal.availability.update');

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

    Route::get('support/conversations', [SupportConversationController::class, 'index'])
        ->name('internal.support.conversations.index');
});
