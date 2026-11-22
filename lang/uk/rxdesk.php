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
        'label' => 'Країна',
        'select' => 'Виберіть країну',
        'blocked' => 'Поки недоступно в ЄС і Великій Британії',
    ],
    'complimentary' => [
        'plan_given' => 'План надано The RX Desk',
        'until' => 'До :date',
        'no_end' => 'Без дати завершення',
        'checkout_blocked' => 'Ваш план надано The RX Desk, тож щоб оформити підписку, зверніться до The RX Desk.',
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
            'capacity_ram' => 'Високе використання пам\'яті',
            'capacity_load' => 'Високе навантаження сервера',
            'capacity_wait' => 'Довге очікування в черзі',
            'capacity_disk_free' => 'Мало вільного місця на диску',
            'disk_guard' => 'Місце на диску майже вичерпано',
            'nightwatch_burst' => 'Сплеск нових проблем',
        ],
        'messages' => [
            'capacity_ram' => 'Пам\'ять сервера на рівні :percent%, що перевищує поріг :threshold%.',
            'capacity_load' => 'Середнє навантаження за 5 хвилин становить :load, що перевищує поріг :threshold.',
            'capacity_wait' => 'Найдовше очікування в черзі — :minutes хв, що перевищує поріг :threshold_minutes хв.',
            'capacity_disk_free' => 'Вільно :free на диску, що менше за поріг :threshold.',
            'disk_guard' => 'Вільно :free на диску — близько до порогу захисту диска :threshold. Завантаження скоро зупиняться.',
            'nightwatch_burst' => 'За останню годину в Nightwatch з\'явилося проблем: :count.',
        ],
        'resolved_body' => 'Це попередження знято. Його буде надіслано знову, якщо умова повториться.',
        'digest' => [
            'title' => 'Щоденний звіт',
            'cleared' => 'Попередження знято',
            'cleared_line' => 'Ці попередження знято за останні 24 години.',
            'issues' => 'Нові проблеми',
            'failed_jobs' => 'Невдалі завдання',
            'failed_jobs_line' => '{1} Одне невдале завдання вчора|[0,*] Невдалих завдань учора: :count',
            'restore_drill' => 'Перевірка відновлення',
            'restore_drill_line' => 'Остання перевірка відновлення була :date. Запустіть нову зараз.',
            'restore_drill_never' => 'Перевірок відновлення ще не зафіксовано. Запустіть першу зараз.',
        ],
        'preview' => [
            'urgent' => 'Проблема потребує негайної уваги.',
            'today' => 'Проблема потребує уваги сьогодні.',
            'resolved' => 'Попередження знято.',
            'digest' => 'Ваш щоденний підсумок.',
        ],
        'sent_at' => 'Надіслано :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => ':discount знижки на перший місяць',
            'once_yearly' => ':discount знижки на перший рік',
            'repeating_monthly' => '{1} :discount знижки на перший місяць|[2,4] :discount знижки на перші :months місяці|[5,*] :discount знижки на перші :months місяців',
            'repeating_yearly' => ':discount знижки на перший рік',
            'forever' => ':discount знижки, доки ви залишаєтеся підписником',
            'forever_until' => ':discount знижки, доки ви залишаєтеся підписником, до :date',
            'trial' => '{1} :days день безкоштовної пробної версії|[2,4] :days дні безкоштовної пробної версії|[5,*] :days днів безкоштовної пробної версії',
        ],
    ],

    'welcome_plan' => [
        'title' => 'Виберіть тариф',
        'description' => 'Платіть щомісяця або щороку. Змінюйте чи скасовуйте будь-коли.',
        'storage' => ':size пам’яті',
        'ai_credits' => ':credits кредитів ШІ на місяць',
        'unlimited' => 'Необмежені робочі простори, канали, публікації та учасники.',
        'start_trial' => 'Почати :days-денний пробний період',
        'subscribe' => 'Підписатися',
        'trial_terms' => 'Далі :price з :date. Скасуйте будь-коли. Один пробний період на картку.',
        'subscribe_terms' => 'Сьогодні спишемо :price. Скасуйте будь-коли.',
        'payment_retry' => [
            'title' => 'Перший платіж не пройшов',
            'description' => 'Акаунт залишається на паузі, доки платіж не пройде. Оновіть картку, щоб почати тариф.',
            'update_card' => 'Оновити картку',
        ],
        'verify_email' => [
            'title' => 'Підтвердьте пошту',
            'description' => 'Ми надіслали посилання на :email. Відкрийте його й поверніться сюди, щоб почати.',
            'resend' => 'Надіслати ще раз',
            'close' => 'Не зараз',
        ],
    ],
];
