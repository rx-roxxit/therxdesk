<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
| The public surface: anyone reaches these pages, signed in or not. The
| landing sends a signed-in visitor on to the calendar, and the legal pages
| stay readable inside a session (the networks' app reviews read them).
*/

require __DIR__.'/legal.php';

Route::get('/', HomeController::class)->name('app.home');
