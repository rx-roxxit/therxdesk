<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\VideoPolicy\RetryVideoBackup;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

class VideoBackupRetryController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse|HttpResponse
    {
        $this->authorize('view', $post);
        $this->authorize('update', $post->workspace->account);

        RetryVideoBackup::execute($post);

        return $request->wantsJson() ? response()->noContent() : back();
    }
}
