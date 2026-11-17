<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Enums\Account\VideoPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Settings\UpdateVideoPolicyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;

class VideoPolicyController extends Controller
{
    public function update(UpdateVideoPolicyRequest $request): RedirectResponse|HttpResponse
    {
        $request->user()->account->forceFill([
            'video_policy' => $request->enum('video_policy', VideoPolicy::class),
        ])->save();

        return $request->wantsJson() ? response()->noContent() : back();
    }
}
