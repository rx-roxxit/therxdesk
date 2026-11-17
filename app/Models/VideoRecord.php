<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VideoRecord\Outcome;
use Database\Factories\VideoRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the Video policy did with one video of a published post: the post's
 * history shows it in place of the removed player. `media_id` names the
 * `medias` row it was, which may be gone. The Drive columns, `attempts` and
 * `last_error` belong to Backup; `source_media_id` is the file the video was a
 * copy of (its own id for the original), which the other posts of its group
 * match to reuse its Drive copy.
 */
class VideoRecord extends Model
{
    /** @use HasFactory<VideoRecordFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'post_id',
        'media_id',
        'source_media_id',
        'original_filename',
        'size',
        'outcome',
        'drive_file_id',
        'drive_link',
        'attempts',
        'last_error',
    ];

    /**
     * What the post's history needs; the rest stays on the server.
     *
     * @var list<string>
     */
    protected $visible = [
        'id',
        'original_filename',
        'size',
        'outcome',
        'drive_link',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => Outcome::class,
            'size' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
