<?php

declare(strict_types=1);

namespace App\Actions\SignupCode;

use App\Models\SignupCode;
use Random\Randomizer;

class CreateSignupCode
{
    /**
     * Letters and digits that cannot be misread for one another (no 0/O, 1/I/L).
     */
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const int GROUPS = 3;

    private const int GROUP_LENGTH = 4;

    public static function execute(?string $note = null): SignupCode
    {
        return SignupCode::query()->create([
            'code' => self::generate(),
            'note' => $note,
        ]);
    }

    /**
     * A random code such as `K7PM-3QXD-WN8R`, from a cryptographically secure source.
     */
    public static function generate(): string
    {
        $characters = new Randomizer()->getBytesFromString(self::ALPHABET, self::GROUPS * self::GROUP_LENGTH);

        return implode('-', str_split($characters, self::GROUP_LENGTH));
    }
}
