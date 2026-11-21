# Database backups and restore drills

The app dumps its Postgres database to the test R2 bucket and pings the backup heartbeat only when the
upload succeeds. The health endpoint reports the last run, and the digest reports a restore drill that
is due. There is no UI.

The test bucket holds objects for 30 days and the token is scoped to it, so a proof uploads for real
and keeps the object. Run the upload once per proof session. Do not run it in a loop.

## Proof checklist

Run everything from the dev machine, with the stack in selling mode. `$V` is
`.claude/skills/verify-therxdesk/scripts`.

1. `$V/selling.sh up` starts the local `heartbeat` receiver. It receives both the queue heartbeat and
   the backup heartbeat, so nothing calls Better Stack.

2. One real dump to the test bucket. `BACKUP_DIRECTORY` names the proof folder and is passed as a
   container env:

   ```sh
   source .claude/skills/verify-therxdesk/scripts/env.sh
   compose run --rm -T -u app \
       -e BACKUP_DIRECTORY=therxdesk-verify \
       app php artisan rxdesk:backup
   ```

   It prints the object path, e.g. `therxdesk-verify/20261122143000.sql.gz`. The command never prunes
   and never sends mail.

3. The heartbeat fired only because the upload succeeded:

   ```sh
   $V/selling.sh heartbeat 20
   ```

   The receiver log shows `GET /backup-hb` from the app.

4. Health: `$V/internal.sh GET /health --token admin` answers `backup.last_run_at` with the run time.
   `restore_drill` shows `due` and `last_performed_at`.

5. Download the newest proof object into the checkout (the checkout is bind-mounted, so the file is
   on the host):

   ```sh
   compose run --rm -T -u app \
       -e BACKUP_DIRECTORY=therxdesk-verify \
       app php artisan tinker --execute='
   $disk = Illuminate\Support\Facades\Storage::disk(\PauloCastellano\DatabaseBackup\DatabaseBackup::DISK);
   $entry = collect($disk->listContents("therxdesk-verify", false))->filter->isFile()
       ->sortByDesc(fn ($entry) => $entry->lastModified())->first();
   $target = storage_path("app/restore-proof/" . basename($entry->path()));
   Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($target));
   $out = fopen($target, "wb");
   stream_copy_to_stream($disk->readStream($entry->path()), $out);
   fclose($out);
   echo $target, PHP_EOL;
   '
   ```

6. Restore into a scratch Postgres in its own container. It never touches the dev database
   (`trypost`) or the test database:

   ```sh
   docker run --rm --name rxdesk-restore -d \
       -e POSTGRES_PASSWORD=restore-only -e POSTGRES_DB=restored \
       postgres:16-alpine
   until docker exec rxdesk-restore pg_isready -U postgres -d restored; do sleep 1; done
   gunzip -c storage/app/restore-proof/<file>.sql.gz \
       | docker exec -i rxdesk-restore psql -U postgres -d restored -v ON_ERROR_STOP=1 -q
   ```

7. Compare row counts. Save the query, read the dev database read only, read the restored copy, and
   diff. Tables the app writes during the proof (`sessions`, `cache`, `cache_locks`, `jobs`,
   `failed_jobs`, `backup_runs`) can differ; every other table must match.

   ```sh
   cat > /tmp/row-counts.sql <<'SQL'
   SELECT relname,
          (xpath('/row/c/text()',
                 query_to_xml(format('select count(*) as c from %I.%I', schemaname, relname),
                              false, true, '')))[1]::text::int AS rows
   FROM pg_stat_user_tables WHERE schemaname = 'public' ORDER BY relname;
   SQL

   compose exec -T pgsql sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At -F " " -f -' \
       < /tmp/row-counts.sql > /tmp/counts-source.txt
   docker exec -i rxdesk-restore sh -c 'psql -U postgres -d restored -At -F " " -f -' \
       < /tmp/row-counts.sql > /tmp/counts-restored.txt
   diff -u /tmp/counts-source.txt /tmp/counts-restored.txt
   ```

8. Record the drill. The command writes a `restore_drills` row and the health endpoint stops saying
   the drill is due:

   ```sh
   compose run --rm -T -u app app php artisan rxdesk:restore-drill
   ```

9. Digest line. Backdate the recorded drill past three months, clear Mailpit, and run the digest:

   ```sh
   compose run --rm -T -u app app php artisan tinker --execute='
   App\Models\RestoreDrill::query()->latest("performed_at")->first()
       ->forceFill(["performed_at" => now()->subMonthsNoOverflow(4)])->save();
   '
   $V/mail.sh clear
   compose run --rm -T -u app app php artisan rxdesk:digest
   $V/mail.sh wait --to admin@therxdesk.com --subject '[DIGEST]'
   ```

   The mail body lists the restore drill and its last recorded date.

10. Remove the scratch container and the downloaded dump. Leave the object in the bucket; the lock
    and its lifecycle rule remove it after 30 days.

    ```sh
    docker rm -f rxdesk-restore
    rm -rf storage/app/restore-proof /tmp/counts-source.txt /tmp/counts-restored.txt /tmp/row-counts.sql
    ```

11. `$V/selling.sh down` returns the stack to self-hosted mode and removes the heartbeat receiver.

Never point the backup settings at a real Better Stack heartbeat or the production bucket.
