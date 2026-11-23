<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Internal API
    |--------------------------------------------------------------------------
    |
    | Bearer tokens for /internal/v1: the admin app's read-write token and the
    | website's read-only one. Either empty makes every internal route answer
    | 404. They are secrets, so they come from env and are never stored.
    |
    */

    'internal' => [
        'admin_token' => env('RXDESK_INTERNAL_ADMIN_TOKEN'),
        'site_token' => env('RXDESK_INTERNAL_SITE_TOKEN'),
    ],

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
    | Owners store, minus that threshold, but only while media lives on the
    | local disk: object storage cannot be compared with the VPS disk.
    |
    */

    'storage' => [
        'default_quota' => env('RXDESK_DEFAULT_QUOTA', '5GB'),
        'disk_guard_threshold' => env('RXDESK_DISK_GUARD_THRESHOLD', '10GB'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin alerts
    |--------------------------------------------------------------------------
    |
    | The System admin gets one alert system by email. `recipient` receives
    | every level; `today_dedupe_hours` is how long a TODAY alert stays quiet
    | after it was sent; the digest leaves at `digest_at` in `timezone`.
    | `capacity` holds the thresholds the 5-minute capacity check reads, and
    | `nightwatch.burst_threshold` is how many issues opened within an hour
    | raise a TODAY alert. Sizes are human-readable ("30GB").
    |
    */

    'alerts' => [
        'recipient' => env('RXDESK_ALERT_RECIPIENT', 'admin@therxdesk.com'),
        'timezone' => env('RXDESK_ALERTS_TIMEZONE', 'Asia/Bangkok'),
        'digest_at' => env('RXDESK_ALERTS_DIGEST_AT', '08:00'),
        'today_dedupe_hours' => (int) env('RXDESK_ALERTS_TODAY_DEDUPE_HOURS', 6),
        'capacity' => [
            'ram_percent' => (float) env('RXDESK_CAPACITY_RAM_PERCENT', 75),
            'load' => (float) env('RXDESK_CAPACITY_LOAD', 5),
            'wait_seconds' => (int) env('RXDESK_CAPACITY_WAIT_SECONDS', 120),
            'disk_free' => env('RXDESK_CAPACITY_DISK_FREE', '30GB'),
            'disk_urgent_margin' => env('RXDESK_CAPACITY_DISK_URGENT_MARGIN', '5GB'),
        ],
        'nightwatch' => [
            'burst_threshold' => (int) env('RXDESK_NIGHTWATCH_BURST_THRESHOLD', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    |
    | `rxdesk:backup` owns the hourly run: it dumps the database to the backup
    | bucket, records the run and pings the backup heartbeat. The package's own
    | `BACKUP_ENABLED` schedule stays off so only one command ever runs.
    | `restore_drill_months` is how long a restore drill stays current; after
    | that the daily digest asks for another one.
    |
    */

    'backup' => [
        'enabled' => (bool) env('RXDESK_BACKUP_ENABLED', false),
        'restore_drill_months' => (int) env('RXDESK_RESTORE_DRILL_MONTHS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeat
    |--------------------------------------------------------------------------
    |
    | The scheduler dispatches `PingHeartbeat` every five minutes; the job
    | pings Better Stack at `url` from the queue. A blank URL turns the ping
    | into a no-op, which is the self-hosted default. `backup_url` is the
    | separate backup heartbeat `rxdesk:backup` pings only after a run
    | succeeds.
    |
    */

    'heartbeat' => [
        'url' => env('RXDESK_HEARTBEAT_URL'),
        'backup_url' => env('RXDESK_BACKUP_HEARTBEAT_URL'),
        'timeout' => (int) env('RXDESK_HEARTBEAT_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nightwatch webhook
    |--------------------------------------------------------------------------
    |
    | Nightwatch signs its webhook with this secret in the
    | `Nightwatch-Signature` header. A blank secret makes the endpoint answer
    | 404, so nothing reaches it until the secret is configured.
    |
    */

    'nightwatch' => [
        'webhook_secret' => env('NIGHTWATCH_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI model slots and the worst-case cost table
    |--------------------------------------------------------------------------
    |
    | Two Anthropic model slots: the text model every AI feature uses, and the
    | image model alt text needs. The registry is the source of truth for the
    | published price and the image capability; the live provider list only
    | says which ids exist and their display names. Prices are USD per million
    | tokens, from Anthropic's published first-party rates. A model missing
    | here is never selectable, so a price is never guessed.
    |
    */

    'ai' => [
        'default_text_model' => env('ANTHROPIC_TEXT_MODEL', 'claude-haiku-5-5'),
        'default_image_model' => env('ANTHROPIC_IMAGE_MODEL', env('ANTHROPIC_TEXT_MODEL', 'claude-haiku-5-5')),
        'models' => [
            'claude-fable-5-2' => ['input_price_per_mtok' => 8.00, 'output_price_per_mtok' => 40.00, 'accepts_image_input' => true],
            'claude-haiku-5-5' => ['input_price_per_mtok' => 0.10, 'output_price_per_mtok' => 0.50, 'accepts_image_input' => true],
            'claude-sonnet-5-5' => ['input_price_per_mtok' => 2.00, 'output_price_per_mtok' => 10.00, 'accepts_image_input' => true],
            'claude-opus-5-5' => ['input_price_per_mtok' => 4.00, 'output_price_per_mtok' => 20.00, 'accepts_image_input' => true],
            'claude-fable-5-1' => ['input_price_per_mtok' => 10.00, 'output_price_per_mtok' => 50.00, 'accepts_image_input' => true],
            'claude-opus-5' => ['input_price_per_mtok' => 5.00, 'output_price_per_mtok' => 25.00, 'accepts_image_input' => true],
            'claude-sonnet-5' => ['input_price_per_mtok' => 2.00, 'output_price_per_mtok' => 10.00, 'accepts_image_input' => true],
            'claude-fable-5' => ['input_price_per_mtok' => 10.00, 'output_price_per_mtok' => 50.00, 'accepts_image_input' => true],
            'claude-opus-4-8' => ['input_price_per_mtok' => 5.00, 'output_price_per_mtok' => 25.00, 'accepts_image_input' => true],
            'claude-opus-4-7' => ['input_price_per_mtok' => 5.00, 'output_price_per_mtok' => 25.00, 'accepts_image_input' => true],
            'claude-sonnet-4-6' => ['input_price_per_mtok' => 3.00, 'output_price_per_mtok' => 15.00, 'accepts_image_input' => true],
            'claude-opus-4-6' => ['input_price_per_mtok' => 5.00, 'output_price_per_mtok' => 25.00, 'accepts_image_input' => true],
            'claude-haiku-4-5-20251001' => ['input_price_per_mtok' => 1.00, 'output_price_per_mtok' => 5.00, 'accepts_image_input' => true],
        ],
    ],

];
