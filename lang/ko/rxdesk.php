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
            'sign_in' => '로그인',
        ],
        'hero' => [
            'title' => '관리하는 모든 브랜드를 한곳에서.',
            'lead' => '한 번 작성하고 네트워크별로 다듬으세요. 고객마다 채널, 캘린더, 승인을 각자의 워크스페이스에 모아 둡니다.',
            'signup_soon' => '곧 누구나 가입할 수 있습니다.',
        ],
        'hook' => [
            'workspaces' => '워크스페이스',
            'channels' => '채널',
            'unlimited' => '무제한',
        ],
        'networks' => [
            'title' => '이미 게시하고 있는 네트워크를 연결하세요.',
            'lead' => '브랜드별 계정은 한 번만 연결하면 됩니다. 팀은 비밀번호를 공유하지 않고 게시할 수 있습니다.',
        ],
        'composer' => [
            'title' => '한 번 쓰고, 네트워크마다 다듬으세요.',
            'body' => '하나의 에디터에서 초안을 쓴 다음, 네트워크마다 필요한 텍스트, 미디어, 설정을 바꾸세요. 미리보기로 실제 모습을 확인하고, 입력하는 동안 제한을 확인하므로 예약하기 전에 문제를 찾을 수 있습니다.',
            'points' => [
                'per_network' => [
                    'title' => '네트워크별 텍스트와 미디어.',
                    'text' => '하나의 초안에 네트워크마다 다른 캡션, 이미지, 제목을 넣을 수 있습니다.',
                ],
                'checked' => [
                    'title' => '입력하면서 확인.',
                    'text' => '선택한 채널의 글자 수 제한, 해시태그, 미디어 규칙을 확인합니다.',
                ],
            ],
        ],
        'calendar' => [
            'title' => '브랜드마다 캘린더와 대기열.',
            'body' => '채널마다 게시 시간을 정하고 게시물을 대기열에 추가하세요. 각 게시물은 다음 빈 시간에 들어갑니다. 계획이 바뀌면 게시물을 다른 날로 끌어다 놓으세요.',
            'points' => [
                'one_per_time' => [
                    'title' => '게시 시간마다 게시물 하나.',
                    'text' => '대기열의 게시물은 자기 시간을 유지하고, 그 위에 다른 게시물이 잡히지 않습니다.',
                ],
                'drag' => [
                    'title' => '끌어서 일정 변경.',
                    'text' => '캘린더에서 바로 게시물을 다른 날로 옮기세요.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => '클라이언트마다 워크스페이스 하나.',
            'body' => '브랜드마다 채널, 캘린더, 미디어, 팀을 따로 둡니다. 팀원의 게시물은 게시되기 전에 승인을 기다리게 할 수 있습니다.',
            'points' => [
                'unlimited' => [
                    'title' => '필요한 만큼 워크스페이스를.',
                    'text' => '클라이언트마다 하나, 또는 직접 운영하는 브랜드마다 하나.',
                ],
                'approvals' => [
                    'title' => '팀원별 승인.',
                    'text' => '새로 온 팀원에게는 켜고, 게시물을 믿을 수 있게 되면 끄세요.',
                ],
            ],
        ],
        'assistant' => [
            'title' => '에디터 안의 어시스턴트. 내 AI도 여기서 일할 수 있습니다.',
            'body' => '내장 어시스턴트가 에디터에서 초안을 쓰고 다시 씁니다. Claude나 ChatGPT를 연결하면 워크스페이스에서 초안 작성, 예약, 결과 확인을 할 수 있습니다.',
            'points' => [
                'rewrite' => [
                    'title' => '요청하면 다시 쓰기.',
                    'text' => '초안을 줄이거나, 다르게 표현하거나, 어조를 바꾸세요.',
                ],
                'api' => [
                    'title' => 'MCP와 REST API.',
                    'text' => 'AI 도구와 직접 만든 스크립트도 같은 워크스페이스에서 작동합니다.',
                ],
            ],
        ],
        'closing' => [
            'title' => '브랜드 하나로 시작하세요. 다음 브랜드를 맡게 되면 추가하세요.',
        ],
        'screenshots' => [
            'calendar_list' => '빵집의 게시물 대기열. 맨 위에 팀원의 Facebook 게시물이 내일 오전 9:00 승인을 기다리고 있고, 대기열에 추가하는 버튼과 거절 버튼이 있습니다.',
            'composer' => 'Instagram, Facebook, YouTube용 초안과 이미지, 옆에 Instagram과 Facebook 실시간 미리보기가 보이는 에디터.',
            'calendar_month' => '빵집의 Instagram과 Facebook 게시물이 하루에 하나씩 보이는 월간 캘린더.',
            'approvals' => '팀원이 보낸 게시물 두 개가 있는 승인 탭. 하나는 대기열용, 하나는 정해진 시간용이며 각각 거절 버튼이 있습니다.',
            'assistant' => '에디터 옆의 AI 어시스턴트가 초안의 짧은 버전을 제안하고 있습니다.',
        ],
        'footer' => [
            'terms' => '약관',
            'privacy' => '개인정보',
            'source_code' => '소스 코드',
            'built_on' => ':upstream 기반 (AGPL-3.0)',
            'status' => '상태',
        ],
        'skip_to_content' => '본문으로 건너뛰기',
    ],
];
