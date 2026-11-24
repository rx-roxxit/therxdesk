<?php

declare(strict_types=1);

namespace App\Enums\AskTheDesk;

enum AskTheDeskRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
