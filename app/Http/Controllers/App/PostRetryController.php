<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\RetryBlockedPost;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PostRetryController extends Controller
{
    /**
     * The in-app "Try again" for a post the publishing gate blocked. Access is
     * not checked here: the gate decides again when the job runs.
     */
    public function store(Post $post): RedirectResponse
    {
        $this->authorize('update', $post);
        $this->authorize('publishDirectly', $post->workspace);

        try {
            RetryBlockedPost::execute($post);
        } catch (ValidationException $exception) {
            session()->flash('flash.banner', data_get($exception->errors(), 'post.0'));
            session()->flash('flash.bannerStyle', 'danger');

            return back();
        }

        session()->flash('flash.banner', __('rxdesk.publishing_block.retry_started'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
