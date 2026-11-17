<?php

declare(strict_types=1);

namespace App\Exceptions\Cover;

use RuntimeException;

/**
 * The network cannot take the Cover yet (the video is still processing, the
 * service is busy): `SetPostCover` looks again later.
 */
class CoverNotReady extends RuntimeException {}
