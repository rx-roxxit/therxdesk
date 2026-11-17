<?php

declare(strict_types=1);

namespace App\Exceptions\VideoPolicy;

use RuntimeException;

/**
 * The Owner's Drive connection cannot back anything up until they sign in
 * again (missing, flagged, or rejected by Google). Trying again on the
 * backoff cannot help, so the backup fails at once.
 */
class DriveBackupUnavailable extends RuntimeException {}
