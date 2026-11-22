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
            'capacity_ram' => 'Uso alto de memoria',
            'capacity_load' => 'Carga alta del servidor',
            'capacity_wait' => 'Espera larga en la cola',
            'capacity_disk_free' => 'Poco espacio libre en disco',
            'disk_guard' => 'Espacio en disco casi agotado',
            'nightwatch_burst' => 'Ráfaga de nuevos problemas',
        ],
        'messages' => [
            'capacity_ram' => 'La memoria del servidor está al :percent %, por encima del umbral de alerta del :threshold %.',
            'capacity_load' => 'La carga media de 5 minutos es :load, por encima del umbral de alerta de :threshold.',
            'capacity_wait' => 'La espera más larga en la cola es de :minutes minutos, por encima del umbral de alerta de :threshold_minutes minutos.',
            'capacity_disk_free' => 'El espacio libre en disco es :free, por debajo del umbral de alerta de :threshold.',
            'disk_guard' => 'El espacio libre en disco es :free, cerca del umbral de protección de disco de :threshold. Las subidas se detendrán pronto.',
            'nightwatch_burst' => 'Se abrieron :count problemas en Nightwatch durante la última hora.',
        ],
        'resolved_body' => 'Esta alerta se ha resuelto. Volverá a enviarse si la condición reaparece.',
        'digest' => [
            'title' => 'Resumen diario',
            'cleared' => 'Alertas resueltas',
            'cleared_line' => 'Estas alertas se resolvieron en las últimas 24 horas.',
            'issues' => 'Nuevos problemas',
            'failed_jobs' => 'Tareas fallidas',
            'failed_jobs_line' => '{1} Una tarea falló ayer|[0,*] :count tareas fallaron ayer',
            'restore_drill' => 'Prueba de restauración',
            'restore_drill_line' => 'La última prueba de restauración fue el :date. Haz una ahora.',
            'restore_drill_never' => 'Todavía no hay ninguna prueba de restauración registrada. Haz una ahora.',
        ],
        'preview' => [
            'urgent' => 'Un problema necesita atención ahora.',
            'today' => 'Un problema necesita atención hoy.',
            'resolved' => 'Una alerta se ha resuelto.',
            'digest' => 'Tu resumen diario.',
        ],
        'sent_at' => 'Enviado :time',
    ],
    'welcome_plan' => [
        'title' => 'Elige tu plan',
        'description' => 'Paga mensual o anualmente. Cambia o cancela cuando quieras.',
        'storage' => ':size de almacenamiento',
        'ai_credits' => ':credits créditos de IA al mes',
        'unlimited' => 'Espacios de trabajo, canales, publicaciones y miembros ilimitados.',
        'start_trial' => 'Prueba gratis :days días',
        'subscribe' => 'Suscribirse',
        'trial_terms' => 'Después :price desde :date. Cancela cuando quieras. Una prueba gratis por tarjeta.',
        'subscribe_terms' => 'Se te cobrará :price hoy. Cancela cuando quieras.',
        'payment_retry' => [
            'title' => 'Tu primer pago no se completó',
            'description' => 'Tu cuenta sigue en pausa hasta que se pague. Actualiza tu tarjeta para empezar tu plan.',
            'update_card' => 'Actualizar tarjeta',
        ],
        'verify_email' => [
            'title' => 'Verifica tu correo',
            'description' => 'Te enviamos un enlace a :email. Ábrelo y vuelve aquí para empezar.',
            'resend' => 'Reenviar correo',
            'close' => 'Ahora no',
        ],
    ],
];
