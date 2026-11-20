<?php

declare(strict_types=1);

/*
| The RX Desk's own copy. The `public` block (the pre-launch website) is written
| natively in every locale; the rest stays English in every locale until v3.0.0
| (see AGENTS.md, "Writing in-app copy"). Upstream never touches this file.
*/

return [
    'help' => [
        'source_code' => 'Source code',
    ],
    'signup_code' => [
        'label' => 'Signup code',
        'placeholder' => 'XXXX-XXXX-XXXX',
        'errors' => [
            'required' => 'Enter the signup code the admin gave you.',
            'invalid' => "This signup code doesn't exist. Check it and try again.",
            'used' => 'This signup code was already used. Ask the admin for a new one.',
            'revoked' => 'This signup code was revoked. Ask the admin for a new one.',
        ],
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

    'public' => [
        'nav' => [
            'sign_in' => 'Inloggen',
        ],
        'hero' => [
            'title' => 'Elk merk dat je beheert op één plek.',
            'lead' => 'Schrijf één keer en pas het aan per netwerk. De kanalen, agenda en goedkeuringen van elke klant blijven in een eigen werkruimte.',
            'signup_soon' => 'Aanmelden opent binnenkort voor iedereen.',
        ],
        'hook' => [
            'workspaces' => 'Workspaces',
            'channels' => 'Kanalen',
            'unlimited' => 'Onbeperkt',
        ],
        'networks' => [
            'title' => 'Koppel de netwerken waar je al op post.',
            'lead' => 'Koppel de accounts van elk merk één keer. Je team post erop zonder wachtwoorden te delen.',
        ],
        'composer' => [
            'title' => 'Eén keer schrijven. Per netwerk afmaken.',
            'body' => 'Schrijf het concept in één editor en pas daarna de tekst, media en instellingen aan die elk netwerk nodig heeft. De voorbeelden tonen de post zoals die verschijnt, en limieten worden gecontroleerd terwijl je typt, zodat niets bij het publiceren wordt geweigerd.',
            'points' => [
                'per_network' => [
                    'title' => 'Tekst en media per netwerk.',
                    'text' => 'Eén concept kan op elk kanaal een ander bijschrift, een andere afbeelding of een andere titel hebben.',
                ],
                'checked' => [
                    'title' => 'Gecontroleerd terwijl je typt.',
                    'text' => 'Tekenlimieten, hashtags en mediaregels van de kanalen die je kiest.',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'Een kalender en een wachtrij voor elk merk.',
            'body' => 'Stel de posttijden van elk kanaal in en voeg posts toe aan de wachtrij: elke post krijgt de eerstvolgende vrije tijd. Veranderen de plannen, sleep een post dan naar een andere dag.',
            'points' => [
                'one_per_time' => [
                    'title' => 'Eén post per posttijd.',
                    'text' => 'Een post in de wachtrij houdt zijn tijd, en er wordt niets overheen gepland.',
                ],
                'drag' => [
                    'title' => 'Slepen om te verplaatsen.',
                    'text' => 'Verplaats een post rechtstreeks in de kalender naar een andere dag.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'Een workspace voor elke klant.',
            'body' => 'Elk merk houdt zijn eigen kanalen, kalender, media en team. Posts van een teamlid kunnen op jouw goedkeuring wachten voordat ze live gaan.',
            'points' => [
                'unlimited' => [
                    'title' => 'Zoveel workspaces als je nodig hebt.',
                    'text' => 'Eén per klant, of één per merk dat je zelf runt.',
                ],
                'approvals' => [
                    'title' => 'Goedkeuringen per teamlid.',
                    'text' => 'Zet ze aan voor iemand die nieuw is, en uit zodra je diens posts vertrouwt.',
                ],
            ],
        ],
        'assistant' => [
            'title' => 'Een assistent in de editor. Je eigen AI kan er ook mee werken.',
            'body' => 'De ingebouwde assistent schrijft en herschrijft concepten in de editor. Koppel Claude of ChatGPT, dan kan die concepten schrijven, inplannen en resultaten lezen in je workspaces.',
            'points' => [
                'rewrite' => [
                    'title' => 'Herschrijven op verzoek.',
                    'text' => 'Maak een concept korter, herformuleer het of verander de toon.',
                ],
                'api' => [
                    'title' => 'MCP en REST API.',
                    'text' => 'Je AI-tools en je eigen scripts werken in dezelfde workspaces als jij.',
                ],
            ],
        ],
        'closing' => [
            'title' => 'Begin met één merk. Voeg het volgende toe zodra je het binnenhaalt.',
        ],
        'screenshots' => [
            'calendar_list' => 'De wachtrij van een bakkerij. Bovenaan wacht een Facebook-post van een teamlid op goedkeuring voor morgen om 9:00, met knoppen om hem aan de wachtrij toe te voegen of af te wijzen.',
            'composer' => 'De editor met een concept voor Instagram, Facebook en YouTube, de afbeelding ervan en het live Instagram-voorbeeld ernaast.',
            'calendar_month' => 'De maandkalender met de Instagram- en Facebook-posts van een bakkerij, één per dag.',
            'approvals' => 'Het tabblad Goedkeuringen met twee posts van een teamlid: één voor de wachtrij en één op een vaste tijd, elk met een knop om hem af te wijzen.',
            'assistant' => 'De AI-assistent naast de editor stelt een kortere versie van het concept voor.',
        ],
        'footer' => [
            'terms' => 'Voorwaarden',
            'privacy' => 'Privacy',
            'source_code' => 'Broncode',
            'built_on' => 'Gebouwd op :upstream (AGPL-3.0)',
            'status' => 'Status',
        ],
        'skip_to_content' => 'Naar de inhoud',
    ],
];
