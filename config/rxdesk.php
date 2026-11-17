<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage (Quota)
    |--------------------------------------------------------------------------
    |
    | Sizes are human-readable ("5GB", "500MB"), in binary units (1GB is
    | 1024MB). `default_quota` is the Quota of an Owner without one of their
    | own. `usable_size` is how much the disk can give to media: `rxdesk:quota`
    | warns when the Quotas handed out add up to more. Unset, it never warns.
    |
    */

    'storage' => [
        'default_quota' => env('RXDESK_DEFAULT_QUOTA', '5GB'),
        'usable_size' => env('RXDESK_USABLE_STORAGE'),
    ],

];
