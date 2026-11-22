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
        'label' => 'الدولة',
        'select' => 'اختر دولتك',
        'blocked' => 'غير متاح في الاتحاد الأوروبي أو المملكة المتحدة حتى الآن',
    ],
    'complimentary' => [
        'plan_given' => 'خطة مقدمة من The RX Desk',
        'until' => 'حتى :date',
        'no_end' => 'بلا تاريخ انتهاء',
        'checkout_blocked' => 'خطتك مقدمة من The RX Desk، لذا تواصل مع The RX Desk للاشتراك.',
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
            'capacity_ram' => 'استخدام مرتفع للذاكرة',
            'capacity_load' => 'حمل مرتفع على الخادم',
            'capacity_wait' => 'انتظار طويل في قائمة الانتظار',
            'capacity_disk_free' => 'مساحة قرص حرة منخفضة',
            'disk_guard' => 'مساحة القرص على وشك النفاد',
            'nightwatch_burst' => 'اندفاع في مشكلات جديدة',
        ],
        'messages' => [
            'capacity_ram' => 'ذاكرة الخادم عند :percent%، أعلى من حد التنبيه :threshold%.',
            'capacity_load' => 'متوسط الحمل خلال 5 دقائق هو :load، أعلى من حد التنبيه :threshold.',
            'capacity_wait' => 'أطول انتظار في قائمة الانتظار هو :minutes دقيقة، أعلى من حد التنبيه :threshold_minutes دقيقة.',
            'capacity_disk_free' => 'المساحة الحرة على القرص هي :free، أقل من حد التنبيه :threshold.',
            'disk_guard' => 'المساحة الحرة على القرص هي :free، قريبة من حد حماية القرص :threshold. ستتوقف عمليات الرفع قريبًا.',
            'nightwatch_burst' => 'فُتحت :count مشكلات في Nightwatch خلال الساعة الأخيرة.',
        ],
        'resolved_body' => 'تم رفع هذا التنبيه. سيُرسل مجددًا إذا تكرر الشرط.',
        'digest' => [
            'title' => 'الملخص اليومي',
            'cleared' => 'تنبيهات تم رفعها',
            'cleared_line' => 'تم رفع هذه التنبيهات خلال آخر 24 ساعة.',
            'issues' => 'مشكلات جديدة',
            'failed_jobs' => 'مهام فاشلة',
            'failed_jobs_line' => '{1} فشلت مهمة واحدة أمس|[0,*] فشلت :count مهام أمس',
            'restore_drill' => 'تمرين الاستعادة',
            'restore_drill_line' => 'آخر تمرين استعادة كان في :date. نفّذ واحدًا الآن.',
            'restore_drill_never' => 'لم يُسجَّل أي تمرين استعادة بعد. نفّذ واحدًا الآن.',
        ],
        'preview' => [
            'urgent' => 'هناك مشكلة تحتاج إلى انتباه فوري.',
            'today' => 'هناك مشكلة تحتاج إلى انتباه اليوم.',
            'resolved' => 'تم رفع تنبيه.',
            'digest' => 'ملخصك اليومي.',
        ],
        'sent_at' => 'أُرسل :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => 'خصم :discount على شهرك الأول',
            'once_yearly' => 'خصم :discount على عامك الأول',
            'repeating_monthly' => '{1} خصم :discount على شهرك الأول|[2,10] خصم :discount على أول :months أشهر|[11,*] خصم :discount على أول :months شهرًا',
            'repeating_yearly' => 'خصم :discount على عامك الأول',
            'forever' => 'خصم :discount طوال فترة اشتراكك',
            'forever_until' => 'خصم :discount طوال فترة اشتراكك، حتى :date',
            'trial' => '{1} تجربة مجانية ليوم واحد|[2,10] تجربة مجانية لمدة :days أيام|[11,*] تجربة مجانية لمدة :days يومًا',
        ],
    ],

    'welcome_plan' => [
        'title' => 'اختر خطتك',
        'description' => 'ادفع شهريًا أو سنويًا. يمكنك التغيير أو الإلغاء في أي وقت.',
        'storage' => 'مساحة تخزين :size',
        'ai_credits' => ':credits رصيد ذكاء اصطناعي شهريًا',
        'unlimited' => 'مساحات عمل وقنوات ومنشورات وأعضاء بلا حدود.',
        'start_trial' => 'ابدأ تجربة مجانية لمدة :days أيام',
        'subscribe' => 'اشترك',
        'trial_terms' => 'ثم :price ابتداءً من :date. ألغِ في أي وقت. تجربة مجانية واحدة لكل بطاقة.',
        'subscribe_terms' => 'سيتم تحصيل :price اليوم. ألغِ في أي وقت.',
        'payment_retry' => [
            'title' => 'لم تكتمل دفعتك الأولى',
            'description' => 'يبقى حسابك متوقفًا حتى يتم الدفع. حدّث بطاقتك لبدء خطتك.',
            'update_card' => 'تحديث البطاقة',
        ],
        'verify_email' => [
            'title' => 'تحقق من بريدك الإلكتروني',
            'description' => 'أرسلنا رابطًا إلى :email. افتحه ثم عُد إلى هنا للبدء.',
            'resend' => 'إعادة إرسال البريد',
            'close' => 'ليس الآن',
        ],
    ],
];
