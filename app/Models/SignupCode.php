<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SignupCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single-use code the System admin issues (`rxdesk:code`) so one new person
 * can sign up as an Owner. It is tied to no email and never expires; it is
 * either unused, used (by whom and when) or revoked.
 */
class SignupCode extends Model
{
    /** @use HasFactory<SignupCodeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Codes are stored uppercase, so a code typed in any case, with spaces
     * around it, still matches on both database engines.
     */
    public static function normalize(string $code): string
    {
        return Str::upper(Str::trim($code));
    }

    /**
     * A self-hosted signup needs a code unless it comes through a workspace
     * Invite (a Member joining someone else's workspace).
     */
    public static function isRequiredFor(?Invite $invite): bool
    {
        if (! config('trypost.self_hosted')) {
            return false;
        }

        return $invite === null;
    }

    public function scopeWithCode(Builder $query, string $code): void
    {
        $query->where('code', self::normalize($code));
    }

    /**
     * Why this code cannot sign anyone up, or null when it can.
     */
    public static function refusalFor(?self $signupCode): ?string
    {
        if ($signupCode === null) {
            return __('rxdesk.signup_code.errors.invalid');
        }

        if ($signupCode->isRevoked()) {
            return __('rxdesk.signup_code.errors.revoked');
        }

        if ($signupCode->isUsed()) {
            return __('rxdesk.signup_code.errors.used');
        }

        return null;
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUnused(): bool
    {
        return ! $this->isUsed() && ! $this->isRevoked();
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }
}
