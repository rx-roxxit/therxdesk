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

/** Whether a network took a post's video Cover (`App\Enums\Cover\Outcome`). */
export const CoverOutcomeState = {
    Set: 'set',
    Failed: 'failed',
} as const;

export type CoverOutcomeStateValue =
    (typeof CoverOutcomeState)[keyof typeof CoverOutcomeState];

/** What became of a post's Cover on a network that sets it after publishing (`App\Models\CoverOutcome`). */
export interface CoverOutcome {
    id: string;
    outcome: CoverOutcomeStateValue;
}
