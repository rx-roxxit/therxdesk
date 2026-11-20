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
            'sign_in' => 'تسجيل الدخول',
        ],
        'hero' => [
            'title' => 'كل العلامات التي تديرها في مكان واحد.',
            'lead' => 'اكتب مرة واحدة وكيّف المحتوى لكل شبكة. تبقى قنوات كل عميل وتقويمه وموافقاته في مساحة عمل خاصة به.',
            'signup_soon' => 'التسجيل متاح للجميع قريبًا.',
        ],
        'hook' => [
            'workspaces' => 'مساحات العمل',
            'channels' => 'القنوات',
            'unlimited' => 'بلا حدود',
        ],
        'networks' => [
            'title' => 'اربط الشبكات التي تنشر عليها بالفعل.',
            'lead' => 'اربط حسابات كل علامة مرة واحدة، وسينشر فريقك عليها دون تبادل كلمات المرور.',
        ],
        'composer' => [
            'title' => 'اكتب مرة واحدة، ثم أكمل المنشور لكل شبكة.',
            'body' => 'اكتب المسودة في محرر واحد، ثم غيّر النص والوسائط والإعدادات التي تحتاجها كل شبكة. تعرض المعاينات المنشور كما سيظهر، وتُفحص الحدود أثناء الكتابة، فتكتشف المشكلات قبل الجدولة.',
            'points' => [
                'per_network' => [
                    'title' => 'نص ووسائط لكل شبكة.',
                    'text' => 'يمكن لمسودة واحدة أن تحمل وصفًا أو صورة أو عنوانًا مختلفًا في كل شبكة.',
                ],
                'checked' => [
                    'title' => 'فحص أثناء الكتابة.',
                    'text' => 'حدود الأحرف والوسوم وقواعد الوسائط للقنوات التي اخترتها.',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'تقويم وقائمة انتظار لكل علامة.',
            'body' => 'حدّد أوقات النشر لكل قناة، ثم أضف المنشورات إلى قائمة الانتظار: يأخذ كل منشور أقرب وقت متاح. وإذا تغيّرت الخطط، اسحب المنشور إلى يوم آخر.',
            'points' => [
                'one_per_time' => [
                    'title' => 'منشور واحد لكل وقت نشر.',
                    'text' => 'يحتفظ المنشور في قائمة الانتظار بوقته، ولا يُجدول شيء فوقه.',
                ],
                'drag' => [
                    'title' => 'اسحب لتغيير الموعد.',
                    'text' => 'انقل المنشور إلى يوم آخر مباشرة في التقويم.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'مساحة عمل لكل عميل.',
            'body' => 'تحتفظ كل علامة بقنواتها وتقويمها ووسائطها وفريقها. ويمكن أن تنتظر منشورات أحد أعضاء الفريق موافقتك قبل نشرها.',
            'points' => [
                'unlimited' => [
                    'title' => 'مساحات عمل بقدر ما تحتاج.',
                    'text' => 'واحدة لكل عميل، أو واحدة لكل علامة تديرها بنفسك.',
                ],
                'approvals' => [
                    'title' => 'موافقات لكل عضو.',
                    'text' => 'فعّلها لعضو جديد، وأوقفها عندما تثق بمنشوراته.',
                ],
            ],
        ],
        'assistant' => [
            'title' => 'مساعد داخل المحرر. ويمكن للذكاء الاصطناعي الخاص بك العمل هنا أيضًا.',
            'body' => 'يكتب المساعد المدمج المسودات ويعيد صياغتها داخل المحرر. اربط Claude أو ChatGPT ليتمكن من كتابة المسودات وجدولتها وقراءة النتائج في مساحات عملك.',
            'points' => [
                'rewrite' => [
                    'title' => 'إعادة كتابة عند الطلب.',
                    'text' => 'اختصر المسودة أو أعد صياغتها أو غيّر نبرتها.',
                ],
                'api' => [
                    'title' => 'MCP وواجهة REST API.',
                    'text' => 'تعمل أدوات الذكاء الاصطناعي ونصوصك البرمجية في مساحات العمل نفسها التي تعمل فيها.',
                ],
            ],
        ],
        'closing' => [
            'title' => 'ابدأ بعلامة واحدة، وأضف التالية عندما تكسبها.',
        ],
        'screenshots' => [
            'calendar_list' => 'قائمة انتظار منشورات مخبز. في أعلاها منشور على Facebook من أحد أعضاء الفريق ينتظر الموافقة للنشر غدًا الساعة 9:00، مع زرّين لإضافته إلى قائمة الانتظار أو رفضه.',
            'composer' => 'المحرر وفيه مسودة لـ Instagram وFacebook وYouTube مع صورتها، وبجانبها معاينتا Instagram وFacebook المباشرتان.',
            'calendar_month' => 'تقويم الشهر مع منشورات مخبز على Instagram وFacebook، منشور واحد كل يوم.',
            'approvals' => 'تبويب الموافقات وفيه منشوران من أحد أعضاء الفريق: واحد لقائمة الانتظار وآخر بوقت محدد، ولكل منهما زر رفض.',
            'assistant' => 'مساعد الذكاء الاصطناعي بجانب المحرر يقترح نسخة أقصر من المسودة.',
        ],
        'footer' => [
            'terms' => 'الشروط',
            'privacy' => 'الخصوصية',
            'source_code' => 'الكود المصدري',
            'built_on' => 'مبني على :upstream (AGPL-3.0)',
            'status' => 'الحالة',
        ],
        'skip_to_content' => 'تخطَّ إلى المحتوى',
    ],
];
