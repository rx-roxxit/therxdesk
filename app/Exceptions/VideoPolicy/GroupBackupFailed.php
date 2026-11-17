<?php

declare(strict_types=1);

namespace App\Exceptions\VideoPolicy;

use RuntimeException;

/**
 * The post that uploads a file shared by its group gave up, so the posts
 * waiting on that upload have nothing left to wait for.
 */
class GroupBackupFailed extends RuntimeException {}
