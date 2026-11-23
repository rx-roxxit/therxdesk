<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Models\Account;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Produces an Owner's posts as JSON plus their media, zipped, for a data
 * request answered within 30 days. `--dry-run` counts without writing and
 * without deleting anything. Off while the app is self-hosted.
 */
class ExportCommand extends Command
{
    protected $signature = 'rxdesk:export
        {--account= : The Account UUID to export}
        {--output= : Directory to write the zip in (defaults to storage/app/exports)}
        {--dry-run : Count what would be exported, write nothing}';

    protected $description = "Export an Owner's posts as JSON and media, zipped";

    public function handle(): int
    {
        if (config('trypost.self_hosted')) {
            $this->error('Self-hosted: account exports are off.');

            return self::FAILURE;
        }

        $id = (string) $this->option('account');

        if (blank($id)) {
            $this->error('--account=<uuid> is required.');

            return self::FAILURE;
        }

        $account = Account::query()->with('owner')->find($id);

        if (! $account instanceof Account) {
            $this->error("No Account with id {$id}.");

            return self::FAILURE;
        }

        $posts = $account->posts()->orderBy('posts.created_at')->get();
        $media = Media::query()
            ->whereIn('post_id', $posts->modelKeys())
            ->orderBy('created_at')
            ->get();

        $disk = Storage::disk((string) config('filesystems.default'));

        if ($this->option('dry-run')) {
            $this->reportDryRun($account, $posts, $media, $disk);

            return self::SUCCESS;
        }

        return $this->writeZip($account, $posts, $media, $disk);
    }

    /**
     * @param  Collection<int, Post>  $posts
     * @param  Collection<int, Media>  $media
     */
    private function reportDryRun(Account $account, $posts, $media, $disk): void
    {
        $missing = $media->reject(fn (Media $item): bool => $disk->exists((string) $item->path))->count();

        $this->table(['Account', 'Posts', 'Media', 'Missing files', 'Media bytes'], [[
            $account->id,
            $posts->count(),
            $media->count(),
            $missing,
            (string) $media->sum('size'),
        ]]);

        $this->info('Dry run: nothing was written.');
    }

    /**
     * @param  Collection<int, Post>  $posts
     * @param  Collection<int, Media>  $media
     */
    private function writeZip(Account $account, $posts, $media, $disk): int
    {
        $directory = filled($this->option('output'))
            ? (string) $this->option('output')
            : storage_path('app/exports');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Could not create {$directory}.");

            return self::FAILURE;
        }

        $path = "{$directory}/rxdesk-export-{$account->id}-".now()->format('YmdHis').'.zip';

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Could not open {$path}.");

            return self::FAILURE;
        }

        $byPost = $media->groupBy('post_id');
        $written = 0;

        $payload = [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'owner_email' => $account->owner?->email,
            ],
            'exported_at' => now()->toIso8601String(),
            'posts' => $posts->map(function (Post $post) use ($byPost, $disk, $zip, &$written): array {
                $files = $byPost->get($post->id, collect())
                    ->map(function (Media $item) use ($disk, $zip, &$written): array {
                        $entry = null;

                        if ($disk->exists((string) $item->path)) {
                            $entry = 'media/'.$item->id.'-'.$this->safeName($item);

                            $zip->addFromString($entry, (string) $disk->get((string) $item->path));
                            $written++;
                        }

                        return [
                            'filename' => $item->original_filename,
                            'type' => $item->type?->value,
                            'mime_type' => $item->mime_type,
                            'size' => $item->size,
                            'file' => $entry,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $post->id,
                    'platform' => $post->platform,
                    'content_type' => $post->content_type,
                    'status' => $post->status,
                    'publish_status' => $post->publish_status,
                    'content' => $post->content,
                    'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                    'published_at' => $post->published_at?->toIso8601String(),
                    'created_at' => $post->created_at?->toIso8601String(),
                    'media' => $files,
                ];
            })->all(),
        ];

        $zip->addFromString('posts.json', (string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $zip->close();

        $this->info("Exported {$posts->count()} post(s) and {$written} media file(s) to {$path}.");

        return self::SUCCESS;
    }

    private function safeName(Media $media): string
    {
        $name = $media->original_filename ?: basename((string) $media->path);

        return Str::of($name)->replaceMatches('/[^A-Za-z0-9._-]/', '_')->toString();
    }
}
