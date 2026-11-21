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
            'capacity_ram' => 'Hohe Speicherauslastung',
            'capacity_load' => 'Hohe Serverlast',
            'capacity_wait' => 'Lange Wartezeit in der Warteschlange',
            'capacity_disk_free' => 'Wenig freier Speicherplatz',
            'disk_guard' => 'Speicherplatz fast erschöpft',
            'nightwatch_burst' => 'Viele neue Probleme auf einmal',
        ],
        'messages' => [
            'capacity_ram' => 'Der Arbeitsspeicher des Servers liegt bei :percent %, über dem Alarmschwellenwert von :threshold %.',
            'capacity_load' => 'Die durchschnittliche Last der letzten 5 Minuten ist :load, über dem Alarmschwellenwert von :threshold.',
            'capacity_wait' => 'Die längste Wartezeit in der Warteschlange beträgt :minutes Minuten, über dem Alarmschwellenwert von :threshold_minutes Minuten.',
            'capacity_disk_free' => 'Der freie Speicherplatz beträgt :free, unter dem Alarmschwellenwert von :threshold.',
            'disk_guard' => 'Der freie Speicherplatz beträgt :free, nahe der Schutzschwelle von :threshold. Uploads werden bald gestoppt.',
            'nightwatch_burst' => 'In der letzten Stunde wurden :count Probleme in Nightwatch geöffnet.',
        ],
        'resolved_body' => 'Dieser Alarm hat sich erledigt. Er wird erneut gesendet, wenn die Bedingung wieder auftritt.',
        'digest' => [
            'title' => 'Tägliche Zusammenfassung',
            'cleared' => 'Alarme aufgehoben',
            'cleared_line' => 'Diese Alarme wurden in den letzten 24 Stunden aufgehoben.',
            'issues' => 'Neue Probleme',
            'failed_jobs' => 'Fehlgeschlagene Jobs',
            'failed_jobs_line' => '{1} Ein fehlgeschlagener Job gestern|[0,*] :count fehlgeschlagene Jobs gestern',
        ],
        'preview' => [
            'urgent' => 'Ein Problem braucht jetzt Aufmerksamkeit.',
            'today' => 'Ein Problem braucht heute Aufmerksamkeit.',
            'resolved' => 'Ein Alarm hat sich erledigt.',
            'digest' => 'Ihre tägliche Zusammenfassung.',
        ],
        'sent_at' => 'Gesendet :time',
    ],
];
