<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * The System admin's assistant. It reads page aggregates only and never gets
 * a tool, so it can neither write nor read per-Account data.
 */
#[MaxTokens(900)]
class AskTheDeskAssistant implements Agent
{
    use Promptable;

    /**
     * @param  array<string, mixed>  $aggregates
     */
    public function __construct(public array $aggregates) {}

    public function instructions(): string
    {
        return view('prompts.ask_the_desk.assistant', [
            'aggregates' => (string) json_encode(
                $this->aggregates,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        ])->render();
    }
}
