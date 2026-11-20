<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\App\MarkPublicPage;
use Illuminate\Support\Facades\Route;

/*
| The public surface: anyone reaches these pages, signed in or not. The
| landing sends a signed-in visitor on to the calendar, and the legal pages
| stay readable inside a session (the networks' app reviews read them).
| Every page here is marked public (`MarkPublicPage`), so none carries
| analytics. robots.txt and the sitemap tell crawlers about them.
*/

Route::get('robots.txt', RobotsController::class)->name('robots');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

Route::middleware(MarkPublicPage::class)->group(function (): void {
    require __DIR__.'/legal.php';

    Route::get('/', HomeController::class)->name('app.home');
});
