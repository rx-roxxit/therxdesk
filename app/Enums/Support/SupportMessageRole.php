<?php

declare(strict_types=1);

namespace App\Enums\Support;

enum SupportMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
