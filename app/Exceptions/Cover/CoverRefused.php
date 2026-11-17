<?php

declare(strict_types=1);

namespace App\Exceptions\Cover;

use RuntimeException;

/**
 * The network will never take the Cover (the image or the video was refused,
 * the token cannot do it): `SetPostCover` records the failure at once.
 */
class CoverRefused extends RuntimeException {}
