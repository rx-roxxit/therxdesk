<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The domain's front door. A visitor without an account reads the public
 * landing; anyone signed in goes straight to their calendar, so the landing
 * never stands between a user and their work.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('app.calendar');
        }

        return Inertia::render('public/Landing');
    }
}
