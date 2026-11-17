<?php

declare(strict_types=1);

namespace App\Services\VideoPolicy;

use App\Models\Media;
use App\Models\MediaSourceConnection;
use App\Services\Media\Sources\GoogleMediaOAuth;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use RuntimeException;

/**
 * The Owner's Google Drive, as the Video policy's Backup sees it: folders
 * found or created by name (with `drive.file` the app only sees what it
 * created), and videos sent through a resumable upload, read from storage
 * one chunk at a time so a large file never sits in memory.
 */
class GoogleDriveBackup
{
    public const string FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    /**
     * An access token this close to its expiry is refreshed first.
     */
    private const int EXPIRY_MARGIN_SECONDS = 60;

    private const int REQUEST_TIMEOUT_SECONDS = 30;

    private const int CHUNK_TIMEOUT_SECONDS = 300;

    public function __construct(private readonly GoogleMediaOAuth $oauth) {}

    /**
     * A usable access token for the Owner's connection, refreshed with the
     * stored refresh token (and saved) when it has expired.
     */
    public function accessToken(MediaSourceConnection $connection): string
    {
        if ($connection->expires_at?->isAfter(now()->addSeconds(self::EXPIRY_MARGIN_SECONDS))) {
            return (string) $connection->access_token;
        }

        $token = $this->oauth->refreshAccessToken((string) $connection->refresh_token);

        $connection->forceFill([
            'access_token' => $token['access_token'],
            'expires_at' => now()->addSeconds($token['expires_in']),
        ])->save();

        return $token['access_token'];
    }

    /**
     * The id of the folder at `$names` under My Drive, creating each level
     * that does not exist yet.
     *
     * @param  list<string>  $names
     */
    public function folder(string $accessToken, array $names): string
    {
        $parent = 'root';

        foreach ($names as $name) {
            $parent = $this->childFolder($accessToken, $parent, $name);
        }

        return $parent;
    }

    /**
     * Uploads the video's file into the folder under `$name` and returns
     * the Drive file once Google has confirmed the last byte.
     *
     * @return array{id: string, link: ?string}
     */
    public function upload(string $accessToken, string $folderId, string $name, Media $video): array
    {
        $size = Storage::size($video->path);
        $mimeType = (string) ($video->mime_type ?? 'application/octet-stream');
        $session = $this->startSession($accessToken, $folderId, $name, $mimeType, $size);
        $stream = Storage::readStream($video->path);

        if ($stream === null) {
            throw new RuntimeException("The video file {$video->id} could not be read.");
        }

        try {
            return $this->sendChunks($session, $stream, $mimeType, $size);
        } finally {
            fclose($stream);
        }
    }

    private function childFolder(string $accessToken, string $parent, string $name): string
    {
        $api = config('trypost.media_sources.google_drive.api');
        $quoted = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);
        $folderType = self::FOLDER_MIME_TYPE;

        $found = $this->drive($accessToken)
            ->get("{$api}/files", [
                'q' => "name = '{$quoted}' and '{$parent}' in parents and mimeType = '{$folderType}' and trashed = false",
                'fields' => 'files(id)',
                'pageSize' => 1,
            ])
            ->throw()
            ->json('files.0.id');

        if (filled($found)) {
            return (string) $found;
        }

        $created = (string) $this->drive($accessToken)
            ->withQueryParameters(['fields' => 'id'])
            ->post("{$api}/files", [
                'name' => $name,
                'mimeType' => $folderType,
                'parents' => [$parent],
            ])
            ->throw()
            ->json('id', '');

        if ($created === '') {
            throw new RuntimeException('Google Drive created a folder without an id.');
        }

        return $created;
    }

    /**
     * Opens the resumable session and returns its URI, which must be on the
     * configured upload host: the chunks go there without the token.
     */
    private function startSession(string $accessToken, string $folderId, string $name, string $mimeType, int $size): string
    {
        $uploadApi = config('trypost.media_sources.google_drive.upload_api');

        $response = $this->drive($accessToken)
            ->withHeaders([
                'X-Upload-Content-Type' => $mimeType,
                'X-Upload-Content-Length' => (string) $size,
            ])
            ->withQueryParameters(['uploadType' => 'resumable', 'fields' => 'id,webViewLink'])
            ->post("{$uploadApi}/files", [
                'name' => $name,
                'parents' => [$folderId],
            ])
            ->throw();

        $session = (string) $response->header('Location');

        if ($session === '' || Uri::of($session)->host() !== Uri::of($uploadApi)->host()) {
            throw new RuntimeException('Google Drive did not open an upload session.');
        }

        return $session;
    }

    /**
     * @param  resource  $stream
     * @return array{id: string, link: ?string}
     */
    private function sendChunks(string $session, $stream, string $mimeType, int $size): array
    {
        $chunkBytes = max(1, (int) config('trypost.media_sources.google_drive.backup_chunk_bytes'));
        $offset = 0;

        while (true) {
            $chunk = (string) stream_get_contents($stream, $chunkBytes);

            if ($chunk === '') {
                throw new RuntimeException('The video file ended before Google Drive confirmed the upload.');
            }

            $last = $offset + strlen($chunk) - 1;
            $response = Http::timeout(self::CHUNK_TIMEOUT_SECONDS)
                ->withHeaders(['Content-Range' => "bytes {$offset}-{$last}/{$size}"])
                ->withBody($chunk, $mimeType)
                ->put($session);

            if ($response->status() === 308) {
                $this->assertReceivedThrough($response->header('Range'), $last);
                $offset = $last + 1;

                continue;
            }

            $fileId = (string) $response->throw()->json('id', '');

            if ($fileId === '') {
                throw new RuntimeException('Google Drive finished the upload without a file id.');
            }

            return ['id' => $fileId, 'link' => $this->webLink($response->json('webViewLink'))];
        }
    }

    /**
     * Google answers each chunk with the bytes it holds so far
     * (`bytes=0-N`). Anything short of the whole chunk fails the upload,
     * and the job tries again with a new session.
     */
    private function assertReceivedThrough(string $range, int $last): void
    {
        if ($range === "bytes=0-{$last}") {
            return;
        }

        throw new RuntimeException("Google Drive kept only part of a chunk ({$range}).");
    }

    /**
     * The file's page on Drive, kept only as an https link since the post
     * details render it as one.
     */
    private function webLink(mixed $link): ?string
    {
        if (! is_string($link) || Uri::of($link)->scheme() !== 'https') {
            return null;
        }

        return $link;
    }

    private function drive(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(self::REQUEST_TIMEOUT_SECONDS);
    }
}
