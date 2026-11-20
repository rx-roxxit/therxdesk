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
            'sign_in' => 'Connexion',
        ],
        'hero' => [
            'title' => 'Toutes les marques que vous gérez au même endroit.',
            'lead' => 'Écrivez une fois, adaptez pour chaque réseau. Les canaux, le calendrier et les approbations de chaque client restent dans leur propre espace de travail.',
            'signup_soon' => 'L\'inscription ouvrira bientôt à tout le monde.',
        ],
        'hook' => [
            'workspaces' => 'Espaces de travail',
            'channels' => 'Canaux',
            'unlimited' => 'Illimités',
        ],
        'networks' => [
            'title' => 'Connectez les réseaux sur lesquels vous publiez déjà.',
            'lead' => 'Connectez une fois les comptes de chaque marque. Votre équipe y publie sans se passer de mots de passe.',
        ],
        'composer' => [
            'title' => 'Écrivez une fois. Finalisez pour chaque réseau.',
            'body' => "Rédigez dans un seul éditeur, puis adaptez le texte, les médias et les réglages dont chaque réseau a besoin. Les aperçus montrent la publication telle qu'elle apparaîtra, et les limites sont vérifiées pendant la saisie : vous repérez les problèmes avant de programmer.",
            'points' => [
                'per_network' => [
                    'title' => 'Texte et médias par réseau.',
                    'text' => 'Un même brouillon peut avoir une légende, une image ou un titre différents sur chaque réseau.',
                ],
                'checked' => [
                    'title' => 'Vérifié pendant la saisie.',
                    'text' => 'Limites de caractères, hashtags et règles de médias des canaux choisis.',
                ],
            ],
        ],
        'calendar' => [
            'title' => "Un calendrier et une file d'attente pour chaque marque.",
            'body' => "Définissez les horaires de publication de chaque canal, puis ajoutez des publications à la file d'attente : chacune prend le prochain horaire libre. Si vos plans changent, faites glisser une publication vers un autre jour.",
            'points' => [
                'one_per_time' => [
                    'title' => 'Une publication par horaire.',
                    'text' => "Une publication en file d'attente garde son horaire, et rien n'est programmé par-dessus.",
                ],
                'drag' => [
                    'title' => 'Glissez pour reprogrammer.',
                    'text' => 'Déplacez une publication vers un autre jour, directement dans le calendrier.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'Un espace de travail pour chaque client.',
            'body' => "Chaque marque garde ses propres canaux, son calendrier, ses médias et son équipe. Les publications d'un membre de l'équipe peuvent attendre votre approbation avant de partir.",
            'points' => [
                'unlimited' => [
                    'title' => "Autant d'espaces de travail que nécessaire.",
                    'text' => 'Un par client, ou un par marque que vous gérez vous-même.',
                ],
                'approvals' => [
                    'title' => 'Approbations par membre.',
                    'text' => 'Activez-les pour une nouvelle personne, et désactivez-les quand vous faites confiance à ses publications.',
                ],
            ],
        ],
        'assistant' => [
            'title' => "Un assistant dans l'éditeur. Votre propre IA peut aussi y travailler.",
            'body' => "L'assistant intégré rédige et réécrit les brouillons dans l'éditeur. Connectez Claude ou ChatGPT : il pourra rédiger, programmer et lire les résultats dans vos espaces de travail.",
            'points' => [
                'rewrite' => [
                    'title' => 'Réécriture à la demande.',
                    'text' => 'Raccourcissez un brouillon, reformulez-le ou changez son ton.',
                ],
                'api' => [
                    'title' => 'MCP et API REST.',
                    'text' => "Vos outils d'IA et vos propres scripts travaillent dans les mêmes espaces de travail que vous.",
                ],
            ],
        ],
        'closing' => [
            'title' => 'Commencez avec une marque. Ajoutez la suivante quand vous la décrochez.',
        ],
        'screenshots' => [
            'calendar_list' => "La file d'attente d'une boulangerie. En tête, une publication Facebook d'un membre de l'équipe attend l'approbation pour demain à 9 h, avec des boutons pour l'ajouter à la file ou la refuser.",
            'composer' => "L'éditeur avec un brouillon pour Instagram, Facebook et YouTube, son image et les aperçus Instagram et Facebook en direct à côté.",
            'calendar_month' => 'Le calendrier du mois avec les publications Instagram et Facebook d’une boulangerie, une par jour.',
            'approvals' => "L'onglet Approbations avec deux publications d'un membre de l'équipe : l'une pour la file d'attente, l'autre à une heure fixe, chacune avec un bouton pour la refuser.",
            'assistant' => "L'Assistant IA, à côté de l'éditeur, propose une version plus courte du brouillon.",
        ],
        'footer' => [
            'terms' => 'Conditions',
            'privacy' => 'Confidentialité',
            'source_code' => 'Code source',
            'built_on' => 'Basé sur :upstream (AGPL-3.0)',
            'status' => 'Statut',
        ],
        'skip_to_content' => 'Aller au contenu',
    ],
];
