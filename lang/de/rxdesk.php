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
        'meta' => [
            'landing' => 'Beiträge für jede Marke, die du betreust, schreiben und einplanen – mit eigenem Workspace, Kalender und Freigaben für jeden Kunden.',
            'privacy' => 'Was The RX Desk erfasst und warum, was es von Google, YouTube und Meta liest und wie du deine Daten löschst.',
            'terms' => 'Die Bedingungen für The RX Desk: wer es nutzen darf, deine Inhalte, die verbundenen Netzwerke und wofür wir verantwortlich sind.',
        ],
        'nav' => [
            'sign_in' => 'Anmelden',
        ],
        'hero' => [
            'title' => 'Alle Marken, die du betreust, an einem Ort.',
            'lead' => 'Einmal schreiben und für jedes Netzwerk anpassen. Kanäle, Kalender und Freigaben jedes Kunden bleiben in einem eigenen Workspace.',
            'signup_soon' => 'Die Registrierung öffnet bald für alle.',
        ],
        'hook' => [
            'workspaces' => 'Workspaces',
            'channels' => 'Kanäle',
            'unlimited' => 'Unbegrenzt',
        ],
        'networks' => [
            'title' => 'Verbinde die Netzwerke, auf denen du schon postest.',
            'lead' => 'Verbinde die Konten jeder Marke einmal. Dein Team postet darauf, ohne Passwörter weiterzugeben.',
        ],
        'composer' => [
            'title' => 'Einmal schreiben. Für jedes Netzwerk fertigstellen.',
            'body' => 'Schreib den Entwurf in einem Editor und passe dann Text, Medien und Einstellungen an, die jedes Netzwerk braucht. Die Vorschau zeigt den Beitrag so, wie er erscheinen wird, und Limits werden schon beim Tippen geprüft, damit du Probleme bemerkst, bevor du planst.',
            'points' => [
                'per_network' => [
                    'title' => 'Text und Medien pro Netzwerk.',
                    'text' => 'Ein Entwurf kann in jedem Netzwerk eine andere Bildunterschrift, ein anderes Bild oder einen anderen Titel haben.',
                ],
                'checked' => [
                    'title' => 'Beim Tippen geprüft.',
                    'text' => 'Zeichenlimits, Hashtags und Medienregeln der Kanäle, die du ausgewählt hast.',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'Ein Kalender und eine Warteschlange für jede Marke.',
            'body' => 'Lege die Posting-Zeiten jedes Kanals fest und füge Beiträge zur Warteschlange hinzu: Jeder nimmt die nächste freie Zeit. Ändern sich die Pläne, ziehst du einen Beitrag auf einen anderen Tag.',
            'points' => [
                'one_per_time' => [
                    'title' => 'Ein Beitrag pro Posting-Zeit.',
                    'text' => 'Ein Beitrag in der Warteschlange behält seine Zeit, und nichts wird darüber gebucht.',
                ],
                'drag' => [
                    'title' => 'Zum Verschieben ziehen.',
                    'text' => 'Verschiebe einen Beitrag direkt im Kalender auf einen anderen Tag.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'Ein Workspace für jeden Kunden.',
            'body' => 'Jede Marke behält ihre eigenen Kanäle, ihren Kalender, ihre Medien und ihr Team. Beiträge aus deinem Team können auf deine Freigabe warten, bevor sie live gehen.',
            'points' => [
                'unlimited' => [
                    'title' => 'So viele Workspaces, wie du brauchst.',
                    'text' => 'Einen pro Kunde oder einen pro Marke, die du selbst führst.',
                ],
                'approvals' => [
                    'title' => 'Freigaben pro Teammitglied.',
                    'text' => 'Schalte sie für neue Leute ein und wieder aus, sobald du ihren Beiträgen vertraust.',
                ],
            ],
        ],
        'assistant' => [
            'title' => 'Ein Assistent im Editor. Auch deine eigene KI kann hier arbeiten.',
            'body' => 'Der eingebaute Assistent schreibt und überarbeitet Entwürfe im Editor. Verbinde Claude oder ChatGPT, dann kann die KI in deinen Workspaces Entwürfe schreiben, Beiträge planen und Ergebnisse lesen.',
            'points' => [
                'rewrite' => [
                    'title' => 'Umschreiben auf Wunsch.',
                    'text' => 'Kürze einen Entwurf, formuliere ihn um oder ändere seinen Ton.',
                ],
                'api' => [
                    'title' => 'MCP und REST-API.',
                    'text' => 'Deine KI-Tools und eigenen Skripte arbeiten in denselben Workspaces wie du.',
                ],
            ],
        ],
        'closing' => [
            'title' => 'Fang mit einer Marke an. Nimm die nächste dazu, sobald du sie gewinnst.',
        ],
        'screenshots' => [
            'calendar_list' => 'Die Warteschlange einer Bäckerei. Ganz oben wartet ein Facebook-Beitrag aus dem Team auf Freigabe für morgen um 9:00 Uhr, mit Buttons, um ihn zur Warteschlange hinzuzufügen oder abzulehnen.',
            'composer' => 'Der Editor mit einem Entwurf für Instagram, Facebook und YouTube, seinem Bild und den Live-Vorschauen für Instagram und Facebook daneben.',
            'calendar_month' => 'Der Monatskalender mit den Instagram- und Facebook-Beiträgen einer Bäckerei, einer pro Tag.',
            'approvals' => 'Der Tab Freigaben mit zwei Beiträgen aus dem Team: einer für die Warteschlange, einer zu einer festen Zeit, jeweils mit einem Button zum Ablehnen.',
            'assistant' => 'Der KI-Assistent neben dem Editor schlägt eine kürzere Fassung des Entwurfs vor.',
        ],
        'footer' => [
            'terms' => 'AGB',
            'privacy' => 'Datenschutz',
            'source_code' => 'Quellcode',
            'built_on' => 'Basierend auf :upstream (AGPL-3.0)',
            'status' => 'Status',
        ],
        'skip_to_content' => 'Zum Inhalt springen',
    ],
];
