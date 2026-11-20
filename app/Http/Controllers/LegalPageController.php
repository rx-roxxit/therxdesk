<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;

/**
 * The RX Desk's Privacy Policy and Terms of Service: English Markdown in
 * resources/legal, the first line its "# Title", shown to anyone, signed in
 * or not (the social networks' app reviews read them).
 */
class LegalPageController extends Controller
{
    public function privacy(): Response
    {
        return $this->show('privacy');
    }

    public function terms(): Response
    {
        return $this->show('terms');
    }

    private function show(string $document): Response
    {
        $markdown = File::get(resource_path("legal/{$document}.md"));

        return Inertia::render('legal/Show', [
            'title' => Str::of($markdown)->before("\n")->after('# ')->trim()->value(),
            'body_html' => Str::markdown(Str::after($markdown, "\n"), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'external_link' => [
                    'internal_hosts' => [Uri::of((string) config('app.url'))->host()],
                    'open_in_new_window' => true,
                ],
            ], [new ExternalLinkExtension, new AttributesExtension]),
        ]);
    }
}
