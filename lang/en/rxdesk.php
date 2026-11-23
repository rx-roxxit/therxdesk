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
        'label' => 'Country',
        'select' => 'Select your country',
        'blocked' => 'Not available in the EU/UK yet',
    ],
    'complimentary' => [
        'plan_given' => 'Plan given by The RX Desk',
        'until' => 'Until :date',
        'no_end' => 'No end date',
        'checkout_blocked' => 'Your plan was given by The RX Desk; contact The RX Desk to subscribe.',
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
            'capacity_ram' => 'High memory use',
            'capacity_load' => 'High server load',
            'capacity_wait' => 'Long queue wait',
            'capacity_disk_free' => 'Low free disk space',
            'disk_guard' => 'Disk space nearly exhausted',
            'nightwatch_burst' => 'Burst of new issues',
        ],
        'messages' => [
            'capacity_ram' => 'Server memory is at :percent%, over the :threshold% alert level.',
            'capacity_load' => 'The 5-minute load average is :load, over the alert level of :threshold.',
            'capacity_wait' => 'The longest queue wait is :minutes minutes, over the alert level of :threshold_minutes minutes.',
            'capacity_disk_free' => 'Free disk space is :free, under the alert level of :threshold.',
            'disk_guard' => 'Free disk space is :free, near the Disk guard threshold of :threshold. Uploads will stop soon.',
            'nightwatch_burst' => ':count issues opened in Nightwatch within the last hour.',
        ],
        'resolved_body' => 'This alert cleared. It will be raised again if the condition returns.',
        'digest' => [
            'title' => 'Daily digest',
            'cleared' => 'Alerts cleared',
            'cleared_line' => 'These alerts cleared in the last 24 hours.',
            'issues' => 'New issues',
            'failed_jobs' => 'Failed jobs',
            'failed_jobs_line' => '{1} One failed job yesterday|[0,*] :count failed jobs yesterday',
            'restore_drill' => 'Restore drill',
            'restore_drill_line' => 'The last restore drill was on :date. Run one now.',
            'restore_drill_never' => 'No restore drill is recorded yet. Run one now.',
        ],
        'preview' => [
            'urgent' => 'A problem needs attention now.',
            'today' => 'A problem needs attention today.',
            'resolved' => 'An alert cleared.',
            'digest' => 'Your daily summary.',
        ],
        'sent_at' => 'Sent :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => ':discount off your first month',
            'once_yearly' => ':discount off your first year',
            'repeating_monthly' => '{1} :discount off your first month|[2,*] :discount off your first :months months',
            'repeating_yearly' => ':discount off your first year',
            'forever' => ':discount off for as long as you stay subscribed',
            'forever_until' => ':discount off for as long as you stay subscribed, until :date',
            'trial' => ':days-day free trial',
        ],
    ],

    'welcome_plan' => [
        'title' => 'Choose your plan',
        'description' => 'Pay monthly or yearly. Change or cancel anytime.',
        'storage' => ':size storage',
        'ai_credits' => ':credits AI credits a month',
        'unlimited' => 'Unlimited workspaces, channels, posts and members.',
        'start_trial' => 'Start :days-day free trial',
        'subscribe' => 'Subscribe',
        'trial_terms' => 'Then :price from :date. Cancel anytime. One free trial per card.',
        'subscribe_terms' => "You'll be charged :price today. Cancel anytime.",
        'lapsed_deletion' => 'Your subscription ended. Your data will be deleted on :date unless you subscribe again.',
        'payment_retry' => [
            'title' => "Your first payment didn't go through",
            'description' => "Your account stays paused until it's paid. Update your card to start your plan.",
            'update_card' => 'Update card',
        ],
        'verify_email' => [
            'title' => 'Verify your email',
            'description' => 'We sent a link to :email. Open it, then come back here to start.',
            'resend' => 'Resend email',
            'close' => 'Not now',
        ],
    ],
    'suspension' => [
        'title' => 'Account suspended',
        'description' => 'Account suspended, contact support. Nothing can be published and connected tools can’t reach the app while it is suspended.',
        'auto' => 'This page updates automatically — no need to refresh.',
        'support_action' => 'Email support',
    ],
    'availability' => [
        'schedule_refused' => ':network is unavailable right now. Save this post as a draft, or try again once it\'s back.',
        'banner' => ':network is unavailable right now. You can save drafts, but this channel won\'t post until it\'s back.',
    ],
    'publishing_block' => [
        'title' => 'Why it wasn’t published',
        'lapsed' => 'Your subscription ended, so this post was not published. Subscribe again, then try again.',
        'suspended' => 'Your account is suspended, so this post was not published. Contact support, then try again.',
        'unavailable' => ':network is unavailable right now. Try again once it\'s back.',
        'retry' => 'Try again',
        'retry_started' => 'Retrying the post.',
        'retry_failed' => 'Couldn’t retry the post. Try again.',
    ],
    'lifecycle' => [
        'trial_ending' => [
            'subject' => 'Your free trial ends soon',
            'title' => 'Your free trial ends soon',
            'preview' => 'Your first payment is coming up.',
            'heading' => 'Your free trial ends soon',
            'body' => 'Your first payment is on :date.',
            'amount' => 'You’ll be charged :amount.',
            'button' => 'Manage billing',
            'cancel' => 'You can cancel before then in the billing portal.',
        ],
        'access_stopped' => [
            'subject' => 'Your subscription ended',
            'title' => 'Your subscription ended',
            'preview' => 'Your data will be deleted unless you subscribe again.',
            'heading' => 'Your subscription ended',
            'body' => 'You no longer have access to your account. Your data will be deleted on :date unless you subscribe again.',
            'export' => 'You can request a copy of your data before then.',
            'button' => 'Subscribe again',
            'support' => 'To request an export,',
            'support_link' => 'contact support.',
        ],
        'deletion_in_7_days' => [
            'subject' => 'Your data will be deleted in 7 days',
            'title' => 'Your data will be deleted in 7 days',
            'preview' => 'Subscribe again to keep your data.',
            'heading' => 'Your data will be deleted in 7 days',
            'body' => 'Your data will be deleted on :date unless you subscribe again.',
            'export' => 'You can request a copy of your data before then.',
            'button' => 'Subscribe again',
            'support' => 'To request an export,',
            'support_link' => 'contact support.',
        ],
        'deletion_in_1_day' => [
            'subject' => 'Your data will be deleted tomorrow',
            'title' => 'Your data will be deleted tomorrow',
            'preview' => 'This is your last chance to subscribe again.',
            'heading' => 'Your data will be deleted tomorrow',
            'body' => 'Your data will be deleted on :date unless you subscribe again.',
            'export' => 'You can request a copy of your data before then.',
            'button' => 'Subscribe again',
            'support' => 'To request an export,',
            'support_link' => 'contact support.',
        ],
        'member_deletion_in_7_days' => [
            'subject' => 'Your workspace will be deleted in 7 days',
            'title' => 'Your workspace will be deleted in 7 days',
            'preview' => 'Save anything you need before then.',
            'heading' => 'Your workspace will be deleted in 7 days',
            'body' => 'The account owner’s subscription ended, so your workspace will be deleted on :date.',
            'export' => 'Save anything you need, or ask the owner to subscribe again. You can also request a copy of your data.',
            'support' => 'To request an export,',
            'support_link' => 'contact support.',
        ],
    ],
    'mail' => [
        'footer' => [
            'operator' => 'The RX Desk is operated by :name, :address.',
        ],
    ],

];
