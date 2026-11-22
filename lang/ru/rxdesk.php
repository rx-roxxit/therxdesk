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
        'label' => 'Страна',
        'select' => 'Выберите страну',
        'blocked' => 'Пока недоступно в ЕС и Великобритании',
    ],
    'complimentary' => [
        'plan_given' => 'План предоставлен The RX Desk',
        'until' => 'До :date',
        'no_end' => 'Без даты окончания',
        'checkout_blocked' => 'Ваш план предоставлен The RX Desk, поэтому, чтобы оформить подписку, свяжитесь с The RX Desk.',
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
            'capacity_ram' => 'Высокое использование памяти',
            'capacity_load' => 'Высокая нагрузка на сервер',
            'capacity_wait' => 'Долгое ожидание в очереди',
            'capacity_disk_free' => 'Мало свободного места на диске',
            'disk_guard' => 'Место на диске почти исчерпано',
            'nightwatch_burst' => 'Всплеск новых проблем',
        ],
        'messages' => [
            'capacity_ram' => 'Память сервера занята на :percent%, выше порога оповещения :threshold%.',
            'capacity_load' => 'Средняя нагрузка за 5 минут — :load, выше порога оповещения :threshold.',
            'capacity_wait' => 'Самое долгое ожидание в очереди — :minutes мин, выше порога оповещения :threshold_minutes мин.',
            'capacity_disk_free' => 'Свободно :free на диске, меньше порога оповещения :threshold.',
            'disk_guard' => 'Свободно :free на диске, близко к порогу защиты диска :threshold. Загрузки скоро остановятся.',
            'nightwatch_burst' => 'За последний час в Nightwatch открыто проблем: :count.',
        ],
        'resolved_body' => 'Это оповещение снято. Оно вернётся, если условие повторится.',
        'digest' => [
            'title' => 'Ежедневная сводка',
            'cleared' => 'Оповещения сняты',
            'cleared_line' => 'Эти оповещения сняты за последние 24 часа.',
            'issues' => 'Новые проблемы',
            'failed_jobs' => 'Неудачные задания',
            'failed_jobs_line' => '{1} Вчера одно задание завершилось ошибкой|[2,4] Вчера :count задания завершились ошибкой|[0,*] Вчера :count заданий завершились ошибкой',
            'restore_drill' => 'Учебное восстановление',
            'restore_drill_line' => 'Последнее учебное восстановление прошло :date. Проведите новое сейчас.',
            'restore_drill_never' => 'Учебных восстановлений пока не зафиксировано. Проведите первое сейчас.',
        ],
        'preview' => [
            'urgent' => 'Проблема требует немедленного внимания.',
            'today' => 'Проблема требует внимания сегодня.',
            'resolved' => 'Оповещение снято.',
            'digest' => 'Ваша ежедневная сводка.',
        ],
        'sent_at' => 'Отправлено :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => ':discount скидки на первый месяц',
            'once_yearly' => ':discount скидки на первый год',
            'repeating_monthly' => '{1} :discount скидки на первый месяц|[2,4] :discount скидки на первые :months месяца|[5,*] :discount скидки на первые :months месяцев',
            'repeating_yearly' => ':discount скидки на первый год',
            'forever' => ':discount скидки, пока вы остаётесь подписчиком',
            'forever_until' => ':discount скидки, пока вы остаётесь подписчиком, до :date',
            'trial' => '{1} :days день бесплатной пробной версии|[2,4] :days дня бесплатной пробной версии|[5,*] :days дней бесплатной пробной версии',
        ],
    ],

    'welcome_plan' => [
        'title' => 'Выберите тариф',
        'description' => 'Оплата ежемесячно или ежегодно. Меняйте или отменяйте в любое время.',
        'storage' => ':size памяти',
        'ai_credits' => ':credits ИИ-кредитов в месяц',
        'unlimited' => 'Неограниченно: пространства, каналы, публикации и участники.',
        'start_trial' => 'Начать :days-дневный пробный период',
        'subscribe' => 'Подписаться',
        'trial_terms' => 'Затем :price с :date. Отмена в любое время. Один пробный период на карту.',
        'subscribe_terms' => 'Сегодня спишем :price. Отмена в любое время.',
        'payment_retry' => [
            'title' => 'Первый платёж не прошёл',
            'description' => 'Аккаунт остаётся на паузе, пока платёж не пройдёт. Обновите карту, чтобы начать тариф.',
            'update_card' => 'Обновить карту',
        ],
        'verify_email' => [
            'title' => 'Подтвердите почту',
            'description' => 'Мы отправили ссылку на :email. Откройте её и вернитесь сюда, чтобы начать.',
            'resend' => 'Отправить ещё раз',
            'close' => 'Не сейчас',
        ],
    ],
    'suspension' => [
        'title' => 'Аккаунт приостановлен',
        'description' => 'Аккаунт приостановлен, обратитесь в поддержку. Пока он приостановлен, публиковать ничего нельзя, а подключённые инструменты не могут получить доступ к приложению.',
        'auto' => 'Эта страница обновляется автоматически — перезагружать не нужно.',
        'support_action' => 'Написать в поддержку',
    ],
    'publishing_block' => [
        'title' => 'Почему публикация не вышла',
        'lapsed' => 'Ваша подписка закончилась, поэтому эта публикация не вышла. Оформите подписку снова и повторите попытку.',
        'suspended' => 'Ваш аккаунт приостановлен, поэтому эта публикация не вышла. Обратитесь в поддержку и повторите попытку.',
        'retry' => 'Повторить',
        'retry_started' => 'Повторная попытка публикации.',
        'retry_failed' => 'Не удалось повторить публикацию. Попробуйте ещё раз.',
    ],
    'mail' => [
        'footer' => [
            'operator' => 'Оператор The RX Desk — :name, :address.',
        ],
    ],

];
