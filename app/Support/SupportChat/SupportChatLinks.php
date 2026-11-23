<?php

declare(strict_types=1);

namespace App\Support\SupportChat;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Route;

/**
 * The only links a Support chat answer may keep: the app routes the help pages
 * declare, the Terms and Privacy pages, and the support address. Anything else
 * the model writes is unwrapped to its text, so an invented or unsafe URL
 * never reaches the customer as a link.
 */
final class SupportChatLinks
{
    /**
     * An image would load a URL the allowlist never saw.
     */
    private const array DROPPED_TAGS = [
        'audio', 'button', 'embed', 'form', 'iframe', 'img', 'input', 'link',
        'math', 'meta', 'object', 'script', 'style', 'svg', 'video',
    ];

    /**
     * Human names for the declared routes.
     */
    private const array LABELS = [
        'app.posts.index' => 'Posts',
        'app.posts.create' => 'New post',
        'app.posts.board' => 'Posts board',
        'app.calendar' => 'Calendar',
        'app.insights' => 'Insights',
        'app.workspace.channels' => 'Channels',
        'app.members' => 'Members',
        'app.billing.index' => 'Billing',
        'app.billing.portal' => 'Customer Portal',
        'app.workspace.channels' => 'Channels',
        'app.workspace.settings' => 'Workspace settings',
        'app.workspaces.index' => 'Workspaces',
        'app.profile.edit' => 'Profile',
        'app.settings.preferences' => 'Preferences',
        'app.create.ideas.index' => 'Ideas',
        'app.create.templates.index' => 'Templates',
        'app.create.feeds.index' => 'Feeds',
        'app.repurposes.index' => 'Repurpose',
        'app.api-keys.index' => 'API keys',
        'app.webhooks.index' => 'Webhooks',
        'app.mcp.index' => 'AI apps',
    ];

    public function __construct(private readonly HelpCorpus $corpus) {}

    /**
     * @var array<string, string>|null
     */
    private ?array $allowedLinks = null;

    /**
     * @return array<string, string> href => human label
     */
    public function allowed(): array
    {
        return $this->allowedLinks ??= $this->allowedFromCorpus();
    }

    /**
     * @return array<string, string> href => human label
     */
    private function allowedFromCorpus(): array
    {
        $allowed = [];

        foreach ($this->corpus->routeNames() as $name) {
            $route = Route::getRoutes()->getByName($name);

            // A page link needs a fixed address; a route that needs one
            // account's id cannot be declared for every customer. An optional
            // segment (`{view?}`) builds without a value.
            if ($route !== null && ! $this->needsParameter($route->uri())) {
                $allowed[route($name)] = data_get(self::LABELS, $name, $name);
            }
        }

        $allowed[(string) config('trypost.legal.terms_url')] = 'Terms of Service';
        $allowed[(string) config('trypost.legal.privacy_url')] = 'Privacy Policy';
        $allowed[$this->supportMailto()] = 'Email support';

        return $allowed;
    }

    public function supportMailto(): string
    {
        return 'mailto:'.config('trypost.support.email');
    }

    public function sanitize(string $html): string
    {
        if (blank($html)) {
            return $html;
        }

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="support-answer">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $allowed = array_keys($this->allowed());

        foreach (array_reverse(iterator_to_array($root->getElementsByTagName('*'))) as $element) {
            if ($element instanceof DOMElement && in_array(strtolower($element->tagName), self::DROPPED_TAGS, true)) {
                $element->parentNode?->removeChild($element);
            }
        }

        foreach (iterator_to_array($root->getElementsByTagName('a')) as $anchor) {
            if (in_array($anchor->getAttribute('href'), $allowed, true)) {
                continue;
            }

            $this->unwrap($anchor);
        }

        return $this->innerHtml($root);
    }

    /**
     * A route that needs one account's id cannot be declared for every
     * customer; an optional segment (`{view?}`) builds without a value.
     */
    private function needsParameter(string $uri): bool
    {
        return str_contains((string) preg_replace('/\{[^}]*\?\}/', '', $uri), '{');
    }

    private function unwrap(DOMElement $anchor): void
    {
        $parent = $anchor->parentNode;

        if (! $parent instanceof DOMNode) {
            return;
        }

        while ($anchor->firstChild instanceof DOMNode) {
            $parent->insertBefore($anchor->firstChild, $anchor);
        }

        $parent->removeChild($anchor);
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }
}
