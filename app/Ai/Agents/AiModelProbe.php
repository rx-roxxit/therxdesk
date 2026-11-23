<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * A one-shot connectivity probe for the System admin: the model only has to
 * answer, so the instruction asks for the single word OK.
 */
class AiModelProbe implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return view('prompts.ai.model_probe')->render();
    }
}
