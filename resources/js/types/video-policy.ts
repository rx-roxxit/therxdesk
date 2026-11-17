/** The Owner's Video policy (`App\Enums\Account\VideoPolicy`). */
export type VideoPolicy = 'keep' | 'delete' | 'backup';

/** Where the Owner's Google Drive sign-in stands (`App\Enums\Account\DriveConnection`). */
export type DriveConnection = 'none' | 'connected' | 'needs_reconnect';

/** What the Video policy did with one video (`App\Enums\VideoRecord\Outcome`). */
export type VideoRecordOutcome =
    | 'pending'
    | 'deleted'
    | 'backed_up'
    | 'backup_failed';

/** One removed video in a post's history (`App\Models\VideoRecord`). */
export interface VideoRecord {
    id: string;
    original_filename: string | null;
    size: number | null;
    outcome: VideoRecordOutcome;
    drive_link: string | null;
    created_at: string;
}
