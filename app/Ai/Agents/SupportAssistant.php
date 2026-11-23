<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\User\Locale;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[MaxTokens(700)]
class SupportAssistant implements Agent, Conversational
{
    use Promptable;

    /**
     * @param  array<string, string>  $links  allowed URL => human label
     * @param  list<Message>  $history
     */
    public function __construct(
        public string $corpus,
        public string $brief,
        public array $links,
        public Locale $locale,
        public array $history = [],
    ) {}

    public function instructions(): string
    {
        return view('prompts.support.assistant', [
            'corpus' => $this->corpus,
            'brief' => $this->brief,
            'links' => $this->links,
            'language' => $this->locale->promptLanguage(),
            'support_email' => config('trypost.support.email'),
        ])->render();
    }

    /**
     * @return list<Message>
     */
    public function messages(): iterable
    {
        return $this->history;
    }
}
