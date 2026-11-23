<?php

declare(strict_types=1);

namespace App\Support\SupportChat;

use Illuminate\Support\Str;

/**
 * Rendered on the server: raw HTML is stripped and the link allowlist is
 * applied last.
 */
final class SupportChatMarkdown
{
    public function __construct(private readonly SupportChatLinks $links) {}

    public function render(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        $html = Str::markdown((string) $markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);

        return $this->links->sanitize($html);
    }
}
