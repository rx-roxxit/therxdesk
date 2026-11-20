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
            'sign_in' => 'Вход',
        ],
        'hero' => [
            'title' => 'Все бренды, которые вы ведёте, в одном месте.',
            'lead' => 'Напишите один раз и адаптируйте под каждую сеть. Каналы, календарь и одобрения каждого клиента остаются в отдельном рабочем пространстве.',
            'signup_soon' => 'Скоро регистрация откроется для всех.',
        ],
        'hook' => [
            'workspaces' => 'Рабочие пространства',
            'channels' => 'Каналы',
            'unlimited' => 'Без лимита',
        ],
        'networks' => [
            'title' => 'Подключите сети, в которых вы уже публикуете.',
            'lead' => 'Подключите аккаунты каждого бренда один раз. Ваша команда публикует в них, не передавая друг другу пароли.',
        ],
        'composer' => [
            'title' => 'Пишите один раз. Доводите до ума для каждой сети.',
            'body' => 'Пишите черновик в одном редакторе, а затем меняйте текст, медиа и настройки под каждую сеть. Предпросмотр показывает пост таким, каким его увидят, а лимиты проверяются прямо при наборе, так что проблемы видны ещё до планирования.',
            'points' => [
                'per_network' => [
                    'title' => 'Текст и медиа для каждой сети.',
                    'text' => 'У одного черновика может быть своя подпись, картинка или заголовок для каждой сети.',
                ],
                'checked' => [
                    'title' => 'Проверка при наборе.',
                    'text' => 'Лимиты символов, хештеги и правила для медиа выбранных каналов.',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'Календарь и очередь для каждого бренда.',
            'body' => 'Задайте время публикаций для каждого канала и добавляйте посты в очередь: каждый занимает ближайшее свободное время. Если планы изменились, перетащите пост на другой день.',
            'points' => [
                'one_per_time' => [
                    'title' => 'Один пост на одно время.',
                    'text' => 'Пост в очереди сохраняет своё время, и поверх него ничего не ставится.',
                ],
                'drag' => [
                    'title' => 'Перетащите, чтобы перенести.',
                    'text' => 'Переносите пост на другой день прямо в календаре.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'Рабочее пространство для каждого клиента.',
            'body' => 'У каждого бренда свои каналы, календарь, медиа и команда. Посты участника команды могут ждать вашего одобрения перед публикацией.',
            'points' => [
                'unlimited' => [
                    'title' => 'Столько рабочих пространств, сколько нужно.',
                    'text' => 'По одному на клиента или на каждый бренд, который вы ведёте сами.',
                ],
                'approvals' => [
                    'title' => 'Одобрение для каждого участника.',
                    'text' => 'Включите его для новичка и выключите, когда начнёте доверять его постам.',
                ],
            ],
        ],
        'assistant' => [
            'title' => 'Помощник в редакторе. Ваш собственный ИИ тоже может здесь работать.',
            'body' => 'Встроенный помощник пишет и переписывает черновики в редакторе. Подключите Claude или ChatGPT, и он сможет писать черновики, планировать посты и читать результаты в ваших рабочих пространствах.',
            'points' => [
                'rewrite' => [
                    'title' => 'Переписывание по запросу.',
                    'text' => 'Сократите черновик, перефразируйте его или смените тон.',
                ],
                'api' => [
                    'title' => 'MCP и REST API.',
                    'text' => 'Ваши ИИ-инструменты и собственные скрипты работают в тех же рабочих пространствах, что и вы.',
                ],
            ],
        ],
        'closing' => [
            'title' => 'Начните с одного бренда. Добавьте следующий, когда его получите.',
        ],
        'screenshots' => [
            'calendar_list' => 'Очередь постов пекарни. Первым идёт пост участника команды для Facebook, который ждёт одобрения на завтра на 9:00, с кнопками, чтобы добавить его в очередь или отклонить.',
            'composer' => 'Редактор с черновиком для Instagram, Facebook и YouTube, его картинкой и предпросмотром Instagram и Facebook рядом.',
            'calendar_month' => 'Календарь на месяц с постами пекарни в Instagram и Facebook, по одному в день.',
            'approvals' => 'Вкладка «Одобрение» с двумя постами участника команды: один в очередь, другой на точное время, у каждого есть кнопка «Отклонить».',
            'assistant' => 'ИИ-помощник рядом с редактором предлагает более короткую версию черновика.',
        ],
        'footer' => [
            'terms' => 'Условия',
            'privacy' => 'Конфиденциальность',
            'source_code' => 'Исходный код',
            'built_on' => 'На основе :upstream (AGPL-3.0)',
            'status' => 'Статус',
        ],
        'skip_to_content' => 'Перейти к содержимому',
    ],
];
