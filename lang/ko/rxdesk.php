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
        'label' => '국가',
        'select' => '국가를 선택하세요',
        'blocked' => 'EU/영국에서는 아직 이용할 수 없습니다',
    ],
    'complimentary' => [
        'plan_given' => 'The RX Desk에서 제공한 요금제',
        'until' => ':date까지',
        'no_end' => '종료일 없음',
        'checkout_blocked' => '이 요금제는 The RX Desk에서 제공하므로 구독하려면 The RX Desk에 문의하세요.',
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
            'capacity_ram' => '메모리 사용량 높음',
            'capacity_load' => '서버 부하 높음',
            'capacity_wait' => '대기열 대기 시간 김',
            'capacity_disk_free' => '디스크 여유 공간 부족',
            'disk_guard' => '디스크 공간 거의 소진',
            'nightwatch_burst' => '새 문제 급증',
        ],
        'messages' => [
            'capacity_ram' => '서버 메모리가 :percent%로, 경고 기준인 :threshold%를 넘었습니다.',
            'capacity_load' => '5분 평균 부하가 :load로, 경고 기준인 :threshold를 넘었습니다.',
            'capacity_wait' => '대기열의 최대 대기 시간이 :minutes분으로, 경고 기준인 :threshold_minutes분을 넘었습니다.',
            'capacity_disk_free' => '디스크 여유 공간이 :free로, 경고 기준인 :threshold보다 적습니다.',
            'disk_guard' => '디스크 여유 공간이 :free로, 디스크 보호 기준인 :threshold에 가깝습니다. 곧 업로드가 중단됩니다.',
            'nightwatch_burst' => '지난 한 시간 동안 Nightwatch에서 :count개의 문제가 열렸습니다.',
        ],
        'resolved_body' => '이 경고는 해제되었습니다. 같은 상황이 다시 발생하면 다시 알려드립니다.',
        'digest' => [
            'title' => '일일 요약',
            'cleared' => '해제된 경고',
            'cleared_line' => '지난 24시간 동안 해제된 경고입니다.',
            'issues' => '새 문제',
            'failed_jobs' => '실패한 작업',
            'failed_jobs_line' => '{1} 어제 작업 1개가 실패했습니다|[0,*] 어제 작업 :count개가 실패했습니다',
            'restore_drill' => '복원 훈련',
            'restore_drill_line' => '마지막 복원 훈련은 :date에 진행되었습니다. 지금 진행하세요.',
            'restore_drill_never' => '아직 복원 훈련 기록이 없습니다. 지금 진행하세요.',
        ],
        'preview' => [
            'urgent' => '지금 주의가 필요한 문제가 있습니다.',
            'today' => '오늘 주의가 필요한 문제가 있습니다.',
            'resolved' => '경고가 해제되었습니다.',
            'digest' => '일일 요약입니다.',
        ],
        'sent_at' => '보낸 시각 :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => '첫 달 :discount 할인',
            'once_yearly' => '첫해 :discount 할인',
            'repeating_monthly' => '첫 :months개월 :discount 할인',
            'repeating_yearly' => '첫해 :discount 할인',
            'forever' => '구독하는 동안 :discount 할인',
            'forever_until' => '구독하는 동안 :discount 할인, :date까지',
            'trial' => ':days일 무료 체험',
        ],
    ],

    'welcome_plan' => [
        'title' => '플랜 선택',
        'description' => '월간 또는 연간으로 결제하세요. 언제든 변경하거나 해지할 수 있습니다.',
        'storage' => ':size 저장 공간',
        'ai_credits' => '월 :credits AI 크레딧',
        'unlimited' => '워크스페이스, 채널, 게시물, 멤버 무제한.',
        'start_trial' => ':days일 무료 체험 시작',
        'subscribe' => '구독하기',
        'trial_terms' => '이후 :date부터 :price. 언제든 해지할 수 있습니다. 카드당 무료 체험 1회.',
        'subscribe_terms' => '오늘 :price가 결제됩니다. 언제든 해지할 수 있습니다.',
        'lapsed_deletion' => '구독이 종료되었습니다. 다시 구독하지 않으면 :date에 데이터가 삭제됩니다.',
        'payment_retry' => [
            'title' => '첫 결제가 완료되지 않았습니다',
            'description' => '결제가 완료될 때까지 계정이 일시 중지됩니다. 카드를 업데이트하여 플랜을 시작하세요.',
            'update_card' => '카드 업데이트',
        ],
        'verify_email' => [
            'title' => '이메일 인증',
            'description' => ':email로 링크를 보냈습니다. 링크를 열고 여기로 돌아와 시작하세요.',
            'resend' => '이메일 다시 보내기',
            'close' => '나중에',
        ],
    ],
    'suspension' => [
        'title' => '계정이 정지되었습니다',
        'description' => '계정이 정지되어 지원팀에 문의해야 합니다. 정지 중에는 아무것도 게시할 수 없고 연결된 도구도 앱에 접근할 수 없습니다.',
        'auto' => '이 페이지는 자동으로 업데이트됩니다 — 새로고침할 필요가 없습니다.',
        'support_action' => '지원팀에 이메일',
    ],
    'availability' => [
        'schedule_refused' => ':network은(는) 현재 사용할 수 없습니다. 이 게시물을 초안으로 저장하거나 네트워크가 복구되면 다시 시도하세요.',
        'banner' => ':network은(는) 현재 사용할 수 없습니다. 초안은 저장할 수 있지만 이 채널은 복구될 때까지 게시되지 않습니다.',
    ],
    'publishing_block' => [
        'title' => '게시되지 않은 이유',
        'lapsed' => '구독이 종료되어 이 게시물이 게시되지 않았습니다. 다시 구독한 후 다시 시도하세요.',
        'suspended' => '계정이 정지되어 이 게시물이 게시되지 않았습니다. 지원팀에 문의한 후 다시 시도하세요.',
        'unavailable' => ':network은(는) 현재 사용할 수 없습니다. 네트워크가 복구되면 다시 시도하세요.',
        'retry' => '다시 시도',
        'retry_started' => '게시물을 다시 게시하는 중입니다.',
        'retry_failed' => '게시물을 다시 시도하지 못했습니다. 다시 시도하세요.',
    ],
    'lifecycle' => [
        'trial_ending' => [
            'subject' => '무료 체험이 곧 종료됩니다',
            'title' => '무료 체험이 곧 종료됩니다',
            'preview' => '첫 결제가 다가오고 있습니다.',
            'heading' => '무료 체험이 곧 종료됩니다',
            'body' => '첫 결제는 :date입니다.',
            'amount' => ':amount가 결제됩니다.',
            'button' => '결제 관리',
            'cancel' => '그전에 결제 포털에서 해지할 수 있습니다.',
        ],
        'access_stopped' => [
            'subject' => '구독이 종료되었습니다',
            'title' => '구독이 종료되었습니다',
            'preview' => '다시 구독하지 않으면 데이터가 삭제됩니다.',
            'heading' => '구독이 종료되었습니다',
            'body' => '계정에 더 이상 접근할 수 없습니다. 다시 구독하지 않으면 :date에 데이터가 삭제됩니다.',
            'export' => '그전에 데이터 사본을 요청할 수 있습니다.',
            'button' => '다시 구독하기',
            'support' => '사본을 요청하시려면',
            'support_link' => '지원팀에 문의하세요.',
        ],
        'deletion_in_7_days' => [
            'subject' => '7일 후에 데이터가 삭제됩니다',
            'title' => '7일 후에 데이터가 삭제됩니다',
            'preview' => '데이터를 유지하려면 다시 구독하세요.',
            'heading' => '7일 후에 데이터가 삭제됩니다',
            'body' => '다시 구독하지 않으면 :date에 데이터가 삭제됩니다.',
            'export' => '그전에 데이터 사본을 요청할 수 있습니다.',
            'button' => '다시 구독하기',
            'support' => '사본을 요청하시려면',
            'support_link' => '지원팀에 문의하세요.',
        ],
        'deletion_in_1_day' => [
            'subject' => '내일 데이터가 삭제됩니다',
            'title' => '내일 데이터가 삭제됩니다',
            'preview' => '다시 구독할 수 있는 마지막 기회입니다.',
            'heading' => '내일 데이터가 삭제됩니다',
            'body' => '다시 구독하지 않으면 :date에 데이터가 삭제됩니다.',
            'export' => '그전에 데이터 사본을 요청할 수 있습니다.',
            'button' => '다시 구독하기',
            'support' => '사본을 요청하시려면',
            'support_link' => '지원팀에 문의하세요.',
        ],
        'member_deletion_in_7_days' => [
            'subject' => '7일 후에 워크스페이스가 삭제됩니다',
            'title' => '7일 후에 워크스페이스가 삭제됩니다',
            'preview' => '그전에 필요한 것을 저장하세요.',
            'heading' => '7일 후에 워크스페이스가 삭제됩니다',
            'body' => '계정 소유자의 구독이 종료되어 :date에 워크스페이스가 삭제됩니다.',
            'export' => '필요한 것을 저장하거나 소유자에게 다시 구독해 달라고 요청하세요. 데이터 사본을 요청할 수도 있습니다.',
            'support' => '사본을 요청하시려면',
            'support_link' => '지원팀에 문의하세요.',
        ],
    ],
    'mail' => [
        'footer' => [
            'operator' => 'The RX Desk는 :name, :address에 의해 운영됩니다.',
        ],
    ],

];
