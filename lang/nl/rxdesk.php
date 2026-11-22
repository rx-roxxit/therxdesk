<?php

declare(strict_types=1);

/*
| The RX Desk's own copy. Every new key ships in all 16 locales; upstream
| never touches this file.
*/

return [
    'help' => [
        'source_code' => 'Source code',
    ],
    'country' => [
        'label' => 'Land',
        'select' => 'Kies je land',
        'blocked' => 'Nog niet beschikbaar in de EU of het Verenigd Koninkrijk',
    ],
    'complimentary' => [
        'plan_given' => 'Plan aangeboden door The RX Desk',
        'until' => 'Tot :date',
        'no_end' => 'Geen einddatum',
        'checkout_blocked' => 'Je plan is aangeboden door The RX Desk; neem contact op met The RX Desk om te abonneren.',
    ],
    'video_policy' => [
        'heading' => 'Video policy',
        'description' => 'One hour after a post publishes, keep its videos on the server, delete them, or back them up to your Google Drive and then delete them. Images are always kept.',
        'options' => [
            'keep' => 'Keep',
            'delete' => 'Delete',
            'backup' => 'Backup to Drive',
        ],
        'save_failed' => "Couldn't save the video policy. Try again.",
        'record' => [
            'deleted' => 'Video deleted',
            'backed_up' => 'Video backed up',
            'open_in_drive' => 'Open in Drive',
        ],
    ],
    'drive_backup' => [
        'heading' => 'Google Drive',
        'folder' => 'The RX Desk',
        'description' => 'Connect your Google Drive to back up videos before they are deleted. The desk only reaches files it creates.',
        'status' => [
            'none' => 'Not connected',
            'connected' => 'Connected',
            'needs_reconnect' => 'Google rejected the connection. Reconnect to keep backing up.',
        ],
        'connect' => 'Connect Google Drive',
        'reconnect' => 'Reconnect',
        'disconnect' => 'Disconnect',
        'disconnect_modal' => [
            'title' => 'Disconnect Google Drive?',
            'description' => 'The desk will stop reaching your Google Drive. Videos already backed up stay in your Drive.',
            'description_backup' => 'Your Video policy goes back to Keep, so new videos stay on the server. Videos already backed up stay in your Drive.',
            'cancel' => 'Cancel',
        ],
        'connected' => 'Google Drive is connected.',
        'errors' => [
            'not_configured' => 'Google Drive is not set up on this server yet. Ask the admin to add the Google keys.',
            'connect_failed' => "Couldn't connect Google Drive. Try again.",
            'scope_not_granted' => 'Google Drive was not connected because access was not granted. Try again and allow access.',
        ],
    ],
    'video_backup_failed' => [
        'subject' => 'A video backup failed in :workspace',
        'title' => 'Video backup failed',
        'preview' => "We couldn't back up a video to your Google Drive.",
        'heading' => 'Video backup failed',
        'body' => "We tried several times and couldn't back up a video from a post in :workspace to your Google Drive. The video is still on the server.",
        'reconnect' => 'Google rejected the Drive connection.',
        'reconnect_link' => 'Reconnect Google Drive',
        'next_steps' => 'Open the post and choose Retry once the problem is fixed.',
        'button' => 'Open the post',
    ],
    'video_backup' => [
        'failed' => 'Backup failed',
        'retry' => 'Retry',
        'retrying' => 'Retrying the backup.',
        'errors' => [
            'nothing_to_retry' => 'This post has no failed backup to retry.',
            'retry_failed' => "Couldn't retry the backup. Try again.",
        ],
    ],
    'invite' => [
        'owner_has_workspaces' => "You own workspaces with this email, so you can't join another account with it. Join as a Member with a different email instead.",
    ],
    'storage' => [
        'errors' => [
            'quota_owner' => "You're out of storage: you've used :used of your :limit. Delete posts or media you no longer need, then try again.",
            'import_owner' => "You're out of storage, so this file can't be added. Delete posts or media you no longer need, then try again.",
            'disk_guard' => "The server is out of space, so this file can't be added. Ask the admin to free some space, then try again.",
            'quota_member' => "The workspace owner's storage is full, so this file can't be added. Ask them to free some space, then try again.",
        ],
    ],
    'storage_bar' => [
        'label' => 'Storage',
        'usage' => ':used of :limit used',
    ],
    'cover' => [
        'mode_label' => 'Cover',
        'mode' => [
            'frame' => 'Pick a frame',
            'image' => 'Upload an image',
        ],
        'hint' => 'JPEG or PNG, up to :max MB. Use the same aspect ratio as the clip (9:16 for a vertical video) so the network doesn\'t crop it.',
        'image_alt' => 'Cover image',
        'upload' => 'Upload image',
        'replace' => 'Replace',
        'remove' => 'Remove',
        'errors' => [
            'not_supported' => "This post type can't use a Cover image. Remove the Cover image, then try again.",
            'not_found' => "The Cover image couldn't be found. Upload it again.",
            'type' => 'The Cover image must be a JPEG or PNG. Export it in one of those formats, then upload it again.',
            'too_large' => 'The Cover image must be :max MB or smaller. Export a smaller file, then upload it again.',
            'upload_failed' => "Couldn't upload the Cover image. Try again.",
            'reference' => 'Give the Cover image as exactly one of upload_token, url or id.',
        ],
        'outcome' => [
            'failed' => "Cover couldn't be set. Add it in the network's app.",
        ],
    ],
    'alerts' => [
        'conditions' => [
            'capacity_ram' => 'Hoog geheugengebruik',
            'capacity_load' => 'Hoge serverbelasting',
            'capacity_wait' => 'Lange wachttijd in de wachtrij',
            'capacity_disk_free' => 'Weinig vrije schijfruimte',
            'disk_guard' => 'Schijfruimte bijna uitgeput',
            'nightwatch_burst' => 'Golf van nieuwe problemen',
        ],
        'messages' => [
            'capacity_ram' => 'Het servergeheugen staat op :percent%, boven de waarschuwingsdrempel van :threshold%.',
            'capacity_load' => 'De gemiddelde belasting over 5 minuten is :load, boven de waarschuwingsdrempel van :threshold.',
            'capacity_wait' => 'De langste wachttijd in de wachtrij is :minutes minuten, boven de waarschuwingsdrempel van :threshold_minutes minuten.',
            'capacity_disk_free' => 'De vrije schijfruimte is :free, onder de waarschuwingsdrempel van :threshold.',
            'disk_guard' => 'De vrije schijfruimte is :free, dicht bij de schijfbewakingdrempel van :threshold. Uploads stoppen binnenkort.',
            'nightwatch_burst' => 'In het laatste uur zijn :count problemen in Nightwatch geopend.',
        ],
        'resolved_body' => 'Deze melding is opgelost. Hij komt terug als de situatie zich opnieuw voordoet.',
        'digest' => [
            'title' => 'Dagelijks overzicht',
            'cleared' => 'Meldingen opgelost',
            'cleared_line' => 'Deze meldingen zijn in de laatste 24 uur opgelost.',
            'issues' => 'Nieuwe problemen',
            'failed_jobs' => 'Mislukte taken',
            'failed_jobs_line' => '{1} Eén taak is gisteren mislukt|[0,*] :count taken zijn gisteren mislukt',
            'restore_drill' => 'Hersteltest',
            'restore_drill_line' => 'De laatste hersteltest was op :date. Voer er nu een uit.',
            'restore_drill_never' => 'Er is nog geen hersteltest vastgelegd. Voer er nu een uit.',
        ],
        'preview' => [
            'urgent' => 'Een probleem vraagt nu om aandacht.',
            'today' => 'Een probleem vraagt vandaag om aandacht.',
            'resolved' => 'Een melding is opgelost.',
            'digest' => 'Je dagelijkse overzicht.',
        ],
        'sent_at' => 'Verzonden :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => ':discount korting op je eerste maand',
            'once_yearly' => ':discount korting op je eerste jaar',
            'repeating_monthly' => '{1} :discount korting op je eerste maand|[2,*] :discount korting op je eerste :months maanden',
            'repeating_yearly' => ':discount korting op je eerste jaar',
            'forever' => ':discount korting zolang je abonnee blijft',
            'forever_until' => ':discount korting zolang je abonnee blijft, tot :date',
            'trial' => '{1} :days dag gratis proberen|[2,*] :days dagen gratis proberen',
        ],
    ],

    'welcome_plan' => [
        'title' => 'Kies je abonnement',
        'description' => 'Betaal maandelijks of jaarlijks. Wissel of stop wanneer je wilt.',
        'storage' => ':size opslag',
        'ai_credits' => ':credits AI-credits per maand',
        'unlimited' => 'Onbeperkte werkruimtes, kanalen, berichten en leden.',
        'start_trial' => 'Probeer :days dagen gratis',
        'subscribe' => 'Abonneren',
        'trial_terms' => 'Daarna :price vanaf :date. Altijd opzegbaar. Eén gratis proefperiode per kaart.',
        'subscribe_terms' => 'Je betaalt vandaag :price. Altijd opzegbaar.',
        'payment_retry' => [
            'title' => 'Je eerste betaling is niet gelukt',
            'description' => 'Je account blijft gepauzeerd tot de betaling binnen is. Werk je kaart bij om je abonnement te starten.',
            'update_card' => 'Kaart bijwerken',
        ],
        'verify_email' => [
            'title' => 'Verifieer je e-mail',
            'description' => 'We hebben een link naar :email gestuurd. Open die en kom hier terug om te beginnen.',
            'resend' => 'E-mail opnieuw sturen',
            'close' => 'Niet nu',
        ],
    ],
    'mail' => [
        'footer' => [
            'operator' => 'The RX Desk wordt beheerd door :name, :address.',
        ],
    ],

];
