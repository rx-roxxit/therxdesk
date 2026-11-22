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
        'label' => '国家或地区',
        'select' => '选择国家或地区',
        'blocked' => '暂不在欧盟和英国提供',
    ],
    'complimentary' => [
        'plan_given' => 'The RX Desk 赠送的套餐',
        'until' => '至 :date',
        'no_end' => '无结束日期',
        'checkout_blocked' => '你的套餐由 The RX Desk 赠送，如需订阅请联系 The RX Desk。',
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
            'capacity_ram' => '内存使用率高',
            'capacity_load' => '服务器负载高',
            'capacity_wait' => '队列等待时间长',
            'capacity_disk_free' => '磁盘可用空间不足',
            'disk_guard' => '磁盘空间即将耗尽',
            'nightwatch_burst' => '新问题激增',
        ],
        'messages' => [
            'capacity_ram' => '服务器内存使用率为 :percent%，超过 :threshold% 的告警阈值。',
            'capacity_load' => '5 分钟平均负载为 :load，超过 :threshold 的告警阈值。',
            'capacity_wait' => '队列最长等待时间为 :minutes 分钟，超过 :threshold_minutes 分钟的告警阈值。',
            'capacity_disk_free' => '磁盘可用空间为 :free，低于 :threshold 的告警阈值。',
            'disk_guard' => '磁盘可用空间为 :free，接近 :threshold 的磁盘保护阈值。上传即将停止。',
            'nightwatch_burst' => '过去一小时内 Nightwatch 新增了 :count 个问题。',
        ],
        'resolved_body' => '此告警已解除。如果该情况再次出现，会再次通知。',
        'digest' => [
            'title' => '每日摘要',
            'cleared' => '已解除的告警',
            'cleared_line' => '这些告警在过去 24 小时内解除。',
            'issues' => '新问题',
            'failed_jobs' => '失败的任务',
            'failed_jobs_line' => '{1} 昨天有 1 个任务失败|[0,*] 昨天有 :count 个任务失败',
            'restore_drill' => '恢复演练',
            'restore_drill_line' => '上次恢复演练是在 :date。现在再演练一次。',
            'restore_drill_never' => '还没有恢复演练记录。现在演练一次。',
        ],
        'preview' => [
            'urgent' => '有问题需要立即处理。',
            'today' => '有问题需要今天处理。',
            'resolved' => '告警已解除。',
            'digest' => '你的每日摘要。',
        ],
        'sent_at' => '发送时间 :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => '首月 :discount 优惠',
            'once_yearly' => '首年 :discount 优惠',
            'repeating_monthly' => '前 :months 个月 :discount 优惠',
            'repeating_yearly' => '首年 :discount 优惠',
            'forever' => '订阅期间一直 :discount 优惠',
            'forever_until' => '订阅期间一直 :discount 优惠，直到 :date',
            'trial' => ':days 天免费试用',
        ],
    ],

    'welcome_plan' => [
        'title' => '选择套餐',
        'description' => '按月或按年付费。随时更改或取消。',
        'storage' => ':size 存储空间',
        'ai_credits' => '每月 :credits AI 积分',
        'unlimited' => '工作区、频道、帖子和成员均无限制。',
        'start_trial' => '开始 :days 天免费试用',
        'subscribe' => '订阅',
        'trial_terms' => '此后自 :date 起 :price。可随时取消。每张卡一次免费试用。',
        'subscribe_terms' => '今天将收取 :price。可随时取消。',
        'payment_retry' => [
            'title' => '首次付款未成功',
            'description' => '付款完成前你的账户将保持暂停。更新银行卡以开始你的套餐。',
            'update_card' => '更新银行卡',
        ],
        'verify_email' => [
            'title' => '验证你的邮箱',
            'description' => '我们已向 :email 发送链接。打开链接后回到这里开始。',
            'resend' => '重新发送邮件',
            'close' => '稍后',
        ],
    ],
];
