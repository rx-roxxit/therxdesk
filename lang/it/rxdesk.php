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
            'sign_in' => 'Accedi',
        ],
        'hero' => [
            'title' => 'Tutti i brand che gestisci in un unico posto.',
            'lead' => 'Scrivi una volta e adatta il testo a ogni rete. I canali, il calendario e le approvazioni di ogni cliente restano nel proprio spazio di lavoro.',
            'signup_soon' => 'La registrazione aprirà presto a tutti.',
        ],
        'hook' => [
            'workspaces' => 'Workspace',
            'channels' => 'Canali',
            'unlimited' => 'Illimitati',
        ],
        'networks' => [
            'title' => 'Collega le reti su cui pubblichi già.',
            'lead' => 'Collega una volta gli account di ogni brand. Il tuo team ci pubblica senza scambiarsi password.',
        ],
        'composer' => [
            'title' => 'Scrivi una volta. Rifiniscilo per ogni rete.',
            'body' => 'Scrivi la bozza in un solo editor, poi cambia testo, media e impostazioni come serve a ogni rete. Le anteprime mostrano il post come apparirà, e i limiti vengono controllati mentre scrivi, così niente viene rifiutato al momento della pubblicazione.',
            'points' => [
                'per_network' => [
                    'title' => 'Testo e media per ogni rete.',
                    'text' => 'Una bozza può avere didascalia, immagine o titolo diversi su ogni canale.',
                ],
                'checked' => [
                    'title' => 'Controllato mentre scrivi.',
                    'text' => 'Limiti di caratteri, hashtag e regole sui media dei canali che hai scelto.',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'Un calendario e una coda per ogni brand.',
            'body' => 'Imposta gli orari di pubblicazione di ogni canale e aggiungi i post alla coda: ognuno prende il primo orario libero. Se i piani cambiano, trascina un post su un altro giorno.',
            'points' => [
                'one_per_time' => [
                    'title' => 'Un post per orario.',
                    'text' => 'Un post in coda mantiene il suo orario, e niente viene programmato sopra.',
                ],
                'drag' => [
                    'title' => 'Trascina per riprogrammare.',
                    'text' => 'Sposta un post su un altro giorno direttamente nel calendario.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'Un workspace per ogni cliente.',
            'body' => 'Ogni brand ha i suoi canali, il suo calendario, i suoi media e il suo team. I post di un membro del team possono aspettare la tua approvazione prima di uscire.',
            'points' => [
                'unlimited' => [
                    'title' => 'Tutti i workspace che ti servono.',
                    'text' => 'Uno per cliente, o uno per ogni brand che gestisci tu.',
                ],
                'approvals' => [
                    'title' => 'Approvazioni per persona.',
                    'text' => 'Attivale per chi è appena arrivato e disattivale quando ti fidi dei suoi post.',
                ],
            ],
        ],
        'assistant' => [
            'title' => "Un assistente nell'editor. Anche la tua IA può lavorarci.",
            'body' => "L'assistente integrato scrive e riscrive le bozze nell'editor. Collega Claude o ChatGPT e potrà scrivere bozze, programmare e leggere i risultati nei tuoi workspace.",
            'points' => [
                'rewrite' => [
                    'title' => 'Riscrittura su richiesta.',
                    'text' => 'Accorcia una bozza, riformulala o cambiane il tono.',
                ],
                'api' => [
                    'title' => 'MCP e API REST.',
                    'text' => 'I tuoi strumenti di IA e i tuoi script lavorano negli stessi workspace in cui lavori tu.',
                ],
            ],
        ],
        'closing' => [
            'title' => 'Inizia con un brand. Aggiungi il prossimo quando lo conquisti.',
        ],
        'screenshots' => [
            'calendar_list' => "La coda dei post di una panetteria. In cima, un post Facebook di un membro del team attende l'approvazione per domani alle 9:00, con i pulsanti per aggiungerlo alla coda o rifiutarlo.",
            'composer' => "L'editor con una bozza per Instagram, Facebook e YouTube, la sua immagine e l'anteprima di Instagram accanto.",
            'calendar_month' => 'Il calendario del mese con i post Instagram e Facebook di una panetteria, uno al giorno.',
            'approvals' => 'La scheda Approvazioni con due post di un membro del team: uno per la coda e uno a un orario fisso, ciascuno con un pulsante per rifiutarlo.',
            'assistant' => "L'Assistente IA accanto all'editor propone una versione più breve della bozza.",
        ],
        'footer' => [
            'terms' => 'Termini',
            'privacy' => 'Privacy',
            'source_code' => 'Codice sorgente',
            'built_on' => 'Basato su :upstream (AGPL-3.0)',
            'status' => 'Stato',
        ],
        'skip_to_content' => 'Vai al contenuto',
    ],
];
