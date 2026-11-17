<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\VideoPolicy\RetryVideoBackup;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;

class VideoBackupRetryController extends Controller
{
    /**
     * @throws ValidationException when the post has no failed backup to retry
     */
    public function store(Request $request, Post $post): RedirectResponse|HttpResponse
    {
        $this->authorize('view', $post);
        $this->authorize('update', $post->workspace->account);

        if (! RetryVideoBackup::execute($post)) {
            throw ValidationException::withMessages(['post' => __('rxdesk.video_backup.errors.nothing_to_retry')]);
        }

        if ($request->wantsJson()) {
            return response()->noContent();
        }

        return back();
    }
}
