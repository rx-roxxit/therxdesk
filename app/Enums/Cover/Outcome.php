<?php

declare(strict_types=1);

namespace App\Enums\Cover;

/**
 * What became of a post's video Cover on a network that takes it after the
 * video is up. There is no pending case: a post without an outcome is still
 * trying, or has no Cover.
 */
enum Outcome: string
{
    case Set = 'set';
    case Failed = 'failed';
}
