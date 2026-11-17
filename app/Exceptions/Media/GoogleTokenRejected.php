<?php

declare(strict_types=1);

namespace App\Exceptions\Media;

use RuntimeException;

/**
 * Google answered `invalid_grant` to a refresh: the stored refresh token was
 * revoked or has expired, and only a new sign-in replaces it.
 */
class GoogleTokenRejected extends RuntimeException {}
