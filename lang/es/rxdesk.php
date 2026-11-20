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
            'sign_in' => 'Iniciar sesión',
        ],
        'hero' => [
            'title' => 'Todas las marcas que gestionas en un solo lugar.',
            'lead' => 'Escribe una vez y adáptalo a cada red. Los canales, el calendario y las aprobaciones de cada cliente se quedan en su propio espacio de trabajo.',
            'signup_soon' => 'El registro se abre para todos muy pronto.',
        ],
        'hook' => [
            'workspaces' => 'Workspaces',
            'channels' => 'Canales',
            'unlimited' => 'Ilimitados',
        ],
        'networks' => [
            'title' => 'Conecta las redes en las que ya publicas.',
            'lead' => 'Conecta una vez las cuentas de cada marca. Tu equipo publica en ellas sin compartir contraseñas.',
        ],
        'composer' => [
            'title' => 'Escribe una vez. Termínalo para cada red.',
            'body' => 'Redacta en un solo editor y luego ajusta el texto, el contenido multimedia y la configuración que necesita cada red. Las vistas previas muestran la publicación tal como aparecerá, y los límites se comprueban mientras escribes, así que detectas los problemas antes de programar.',
            'points' => [
                'per_network' => [
                    'title' => 'Texto y multimedia por red.',
                    'text' => 'Un mismo borrador puede llevar un texto, una imagen o un título distintos en cada red.',
                ],
                'checked' => [
                    'title' => 'Comprobado mientras escribes.',
                    'text' => 'Límites de caracteres, hashtags y reglas de multimedia de los canales que elegiste.',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'Un calendario y una cola para cada marca.',
            'body' => 'Define los horarios de publicación de cada canal y añade publicaciones a la cola: cada una toma el siguiente horario libre. Si cambian los planes, arrastra una publicación a otro día.',
            'points' => [
                'one_per_time' => [
                    'title' => 'Una publicación por horario.',
                    'text' => 'Una publicación en la cola conserva su horario, y nada se programa encima.',
                ],
                'drag' => [
                    'title' => 'Arrastra para reprogramar.',
                    'text' => 'Mueve una publicación a otro día directamente en el calendario.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'Un workspace para cada cliente.',
            'body' => 'Cada marca tiene sus propios canales, calendario, multimedia y equipo. Las publicaciones de alguien de tu equipo pueden esperar tu aprobación antes de salir.',
            'points' => [
                'unlimited' => [
                    'title' => 'Todos los workspaces que necesites.',
                    'text' => 'Uno por cliente, o uno por cada marca que llevas tú.',
                ],
                'approvals' => [
                    'title' => 'Aprobaciones por persona.',
                    'text' => 'Actívalas para alguien nuevo y desactívalas cuando confíes en sus publicaciones.',
                ],
            ],
        ],
        'assistant' => [
            'title' => 'Un asistente en el editor. Tu propia IA también puede trabajar aquí.',
            'body' => 'El asistente integrado escribe y reescribe borradores en el editor. Conecta Claude o ChatGPT y podrá redactar, programar y leer resultados en tus workspaces.',
            'points' => [
                'rewrite' => [
                    'title' => 'Reescritura cuando la pidas.',
                    'text' => 'Acorta un borrador, reformúlalo o cambia su tono.',
                ],
                'api' => [
                    'title' => 'MCP y API REST.',
                    'text' => 'Tus herramientas de IA y tus propios scripts trabajan en los mismos workspaces que tú.',
                ],
            ],
        ],
        'closing' => [
            'title' => 'Empieza con una marca. Suma la siguiente cuando la consigas.',
        ],
        'screenshots' => [
            'calendar_list' => 'La cola de publicaciones de una panadería. Primero aparece una publicación de Facebook de alguien del equipo que espera aprobación para mañana a las 9:00, con botones para añadirla a la cola o rechazarla.',
            'composer' => 'El editor con un borrador para Instagram, Facebook y YouTube, su imagen y las vistas previas de Instagram y Facebook al lado.',
            'calendar_month' => 'El calendario del mes con las publicaciones de Instagram y Facebook de una panadería, una por día.',
            'approvals' => 'La pestaña Aprobaciones con dos publicaciones de alguien del equipo: una para la cola y otra a una hora fija, cada una con un botón para rechazarla.',
            'assistant' => 'El Asistente de IA, junto al editor, sugiere una versión más corta del borrador.',
        ],
        'footer' => [
            'terms' => 'Términos',
            'privacy' => 'Privacidad',
            'source_code' => 'Código fuente',
            'built_on' => 'Basado en :upstream (AGPL-3.0)',
            'status' => 'Estado',
        ],
        'skip_to_content' => 'Saltar al contenido',
    ],
];
