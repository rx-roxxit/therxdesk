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
            'sign_in' => '登录',
        ],
        'hero' => [
            'title' => '你管理的所有品牌，都在同一个地方。',
            'lead' => '写一次，再为每个网络调整内容。每位客户的渠道、日历与审批，都留在各自的工作区里。',
            'signup_soon' => '注册即将向所有人开放。',
        ],
        'hook' => [
            'workspaces' => '工作区',
            'channels' => '频道',
            'unlimited' => '无限制',
        ],
        'networks' => [
            'title' => '连接你已经在发布的平台。',
            'lead' => '每个品牌的账号只需连接一次。团队无需共享密码就能发布。',
        ],
        'composer' => [
            'title' => '写一次，再为每个平台定稿。',
            'body' => '在一个编辑器里写草稿，然后按各平台的需要调整文字、媒体和设置。预览会显示帖子的实际样子，输入时就会检查限制，排期前就能发现问题。',
            'points' => [
                'per_network' => [
                    'title' => '每个平台的文字和媒体。',
                    'text' => '同一份草稿可以在每个平台使用不同的文案、图片或标题。',
                ],
                'checked' => [
                    'title' => '边输入边检查。',
                    'text' => '检查所选频道的字数限制、话题标签和媒体规则。',
                ],
            ],
        ],
        'calendar' => [
            'title' => '每个品牌都有日历和队列。',
            'body' => '为每个频道设置发布时间，再把帖子加入队列：每篇帖子会占用下一个空闲时间。计划有变时，把帖子拖到另一天即可。',
            'points' => [
                'one_per_time' => [
                    'title' => '每个发布时间一篇帖子。',
                    'text' => '队列中的帖子会保留自己的时间，不会有其他帖子排在上面。',
                ],
                'drag' => [
                    'title' => '拖动即可改期。',
                    'text' => '直接在日历上把帖子移到另一天。',
                ],
            ],
        ],
        'workspaces' => [
            'title' => '每个客户一个工作区。',
            'body' => '每个品牌都有自己的频道、日历、媒体和团队。团队成员的帖子可以在发布前等待你的审批。',
            'points' => [
                'unlimited' => [
                    'title' => '需要多少工作区都可以。',
                    'text' => '每个客户一个，或你自己经营的每个品牌一个。',
                ],
                'approvals' => [
                    'title' => '按成员设置审批。',
                    'text' => '为新成员开启，信任他们的帖子后再关闭。',
                ],
            ],
        ],
        'assistant' => [
            'title' => '编辑器里的助手。你自己的 AI 也能在这里工作。',
            'body' => '内置助手在编辑器里撰写和改写草稿。连接 Claude 或 ChatGPT 后，它可以在你的工作区里写草稿、排期和查看结果。',
            'points' => [
                'rewrite' => [
                    'title' => '按需改写。',
                    'text' => '缩短草稿、换种说法或调整语气。',
                ],
                'api' => [
                    'title' => 'MCP 和 REST API。',
                    'text' => '你的 AI 工具和自己的脚本，与你在同一个工作区里工作。',
                ],
            ],
        ],
        'closing' => [
            'title' => '从一个品牌开始。拿下下一个时再添加。',
        ],
        'screenshots' => [
            'calendar_list' => '一家面包店的帖子队列。最上面是团队成员的一篇 Facebook 帖子，排在明天 9:00，正在等待审批，带有加入队列和拒绝按钮。',
            'composer' => '编辑器中有一份发往 Instagram、Facebook 和 YouTube 的草稿及其图片，旁边是 Instagram 和 Facebook 的实时预览。',
            'calendar_month' => '月历视图，显示一家面包店的 Instagram 和 Facebook 帖子，每天一篇。',
            'approvals' => '审批标签页中有团队成员的两篇帖子：一篇加入队列，一篇定时发布，每篇都有拒绝按钮。',
            'assistant' => '编辑器旁的 AI 助手给出了一个更短的草稿版本。',
        ],
        'footer' => [
            'terms' => '条款',
            'privacy' => '隐私',
            'source_code' => '源代码',
            'built_on' => '基于 :upstream 构建 (AGPL-3.0)',
            'status' => '状态',
        ],
        'skip_to_content' => '跳到主要内容',
    ],
];
