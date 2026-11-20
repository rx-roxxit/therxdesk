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
            'landing' => 'Плануйте, пишіть і публікуйте за розкладом пости кожного бренду, яким ви керуєте: у кожного клієнта свій робочий простір, календар і погодження.',
            'privacy' => 'Що збирає The RX Desk і навіщо, що він читає з Google, YouTube і Meta та як видалити свої дані.',
            'terms' => 'Умови використання The RX Desk: хто може ним користуватися, ваш контент, підключені мережі та за що ми відповідаємо.',
        ],
        'nav' => [
            'sign_in' => 'Вхід',
        ],
        'hero' => [
            'title' => 'Усі бренди, якими ви керуєте, в одному місці.',
            'lead' => 'Напишіть один раз і адаптуйте під кожну мережу. Канали, календар і погодження кожного клієнта залишаються в окремому робочому просторі.',
            'signup_soon' => 'Незабаром реєстрація відкриється для всіх.',
        ],
        'hook' => [
            'workspaces' => 'Робочі простори',
            'channels' => 'Канали',
            'unlimited' => 'Без обмежень',
        ],
        'networks' => [
            'title' => 'Підключіть мережі, у яких ви вже публікуєте.',
            'lead' => 'Підключіть акаунти кожного бренду один раз. Ваша команда публікує в них, не передаючи одне одному паролі.',
        ],
        'composer' => [
            'title' => 'Пишіть один раз. Доводьте до ладу для кожної мережі.',
            'body' => 'Пишіть чернетку в одному редакторі, а потім змінюйте текст, медіа й налаштування під кожну мережу. Попередній перегляд показує допис таким, яким його побачать, а ліміти перевіряються під час набору, тож проблеми видно ще до планування.',
            'points' => [
                'per_network' => [
                    'title' => 'Текст і медіа для кожної мережі.',
                    'text' => 'Одна чернетка може мати власний підпис, зображення чи заголовок для кожної мережі.',
                ],
                'checked' => [
                    'title' => 'Перевірка під час набору.',
                    'text' => 'Ліміти символів, хештеги й правила для медіа вибраних каналів.',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'Календар і черга для кожного бренду.',
            'body' => 'Задайте час публікацій для кожного каналу й додавайте дописи до черги: кожен займає найближчий вільний час. Якщо плани змінилися, перетягніть допис на інший день.',
            'points' => [
                'one_per_time' => [
                    'title' => 'Один допис на один час.',
                    'text' => 'Допис у черзі зберігає свій час, і поверх нього нічого не ставиться.',
                ],
                'drag' => [
                    'title' => 'Перетягніть, щоб перенести.',
                    'text' => 'Переносьте допис на інший день просто в календарі.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'Робочий простір для кожного клієнта.',
            'body' => 'Кожен бренд має власні канали, календар, медіа й команду. Дописи учасника команди можуть чекати на ваше схвалення перед публікацією.',
            'points' => [
                'unlimited' => [
                    'title' => 'Стільки робочих просторів, скільки потрібно.',
                    'text' => 'По одному на клієнта або на кожен бренд, який ви ведете самі.',
                ],
                'approvals' => [
                    'title' => 'Схвалення для кожного учасника.',
                    'text' => 'Увімкніть його для новачка й вимкніть, коли довірятимете його дописам.',
                ],
            ],
        ],
        'assistant' => [
            'title' => 'Помічник у редакторі. Ваш власний ШІ теж може тут працювати.',
            'body' => 'Вбудований помічник пише й переписує чернетки в редакторі. Підключіть Claude або ChatGPT, і він зможе писати чернетки, планувати дописи й читати результати у ваших робочих просторах.',
            'points' => [
                'rewrite' => [
                    'title' => 'Переписування на запит.',
                    'text' => 'Скоротіть чернетку, перефразуйте її або змініть тон.',
                ],
                'api' => [
                    'title' => 'MCP і REST API.',
                    'text' => 'Ваші ШІ-інструменти та власні скрипти працюють у тих самих робочих просторах, що й ви.',
                ],
            ],
        ],
        'closing' => [
            'title' => 'Почніть з одного бренду. Додайте наступний, коли його здобудете.',
        ],
        'screenshots' => [
            'calendar_list' => 'Черга дописів пекарні. Першим іде допис учасника команди для Facebook, що чекає на схвалення на завтра на 9:00, з кнопками, щоб додати його до черги або відхилити.',
            'composer' => 'Редактор із чернеткою для Instagram, Facebook і YouTube, її зображенням і попереднім переглядом Instagram і Facebook поруч.',
            'calendar_month' => 'Календар на місяць із дописами пекарні в Instagram і Facebook, по одному на день.',
            'approvals' => 'Вкладка «Схвалення» з двома дописами учасника команди: один до черги, інший на точний час, у кожного є кнопка «Відхилити».',
            'assistant' => 'ШІ-помічник поруч із редактором пропонує коротшу версію чернетки.',
        ],
        'footer' => [
            'terms' => 'Умови',
            'privacy' => 'Конфіденційність',
            'source_code' => 'Вихідний код',
            'built_on' => 'На основі :upstream (AGPL-3.0)',
            'status' => 'Статус',
        ],
        'skip_to_content' => 'Перейти до вмісту',
    ],
];
