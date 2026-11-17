<?php

declare(strict_types=1);

use App\Http\Controllers\LegalPageController;
use Illuminate\Support\Facades\Route;

Route::get('privacy', [LegalPageController::class, 'privacy'])->name('legal.privacy');
Route::get('terms', [LegalPageController::class, 'terms'])->name('legal.terms');
