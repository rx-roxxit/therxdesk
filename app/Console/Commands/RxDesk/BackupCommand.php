<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Models\BackupRun;
use App\Services\Ops\BackupHeartbeat;
use Illuminate\Console\Command;
use PauloCastellano\DatabaseBackup\DatabaseBackup;

/**
 * Dumps the database to the backup bucket, records the run and pings the
 * backup heartbeat only after the dump succeeds. It never prunes (the bucket's
 * lifecycle rule does) and never emails (the alerts do).
 */
class BackupCommand extends Command
{
    protected $signature = 'rxdesk:backup';

    protected $description = 'Dump the database to the backup bucket, record the run and ping the backup heartbeat';

    public function handle(DatabaseBackup $backup, BackupHeartbeat $heartbeat): int
    {
        $result = $backup->backup();

        BackupRun::query()->create([
            'completed_at' => now(),
        ]);

        $heartbeat->ping();

        $this->info((string) data_get($result, 'path'));

        return self::SUCCESS;
    }
}
