<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage (Quota and Disk guard)
    |--------------------------------------------------------------------------
    |
    | Sizes are human-readable ("5GB", "500MB"), in binary units (1GB is
    | 1024MB). `default_quota` is the Quota of an Owner without one of their
    | own. `disk_guard_threshold` is the free disk space under which every
    | upload and import is refused (the Disk guard); `rxdesk:quota` also warns
    | when the Quotas handed out add up to more than free disk plus what the
    | Owners store, minus that threshold.
    |
    */

    'storage' => [
        'default_quota' => env('RXDESK_DEFAULT_QUOTA', '5GB'),
        'disk_guard_threshold' => env('RXDESK_DISK_GUARD_THRESHOLD', '10GB'),
    ],

];
