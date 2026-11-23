<?php

declare(strict_types=1);

/*
| The RX Desk's own copy. Every new key ships in all 16 locales; upstream
| never touches this file.
*/

return [
    'ai' => [
        'unavailable' => 'AIに今すぐ接続できません。数分後にもう一度お試しください。',
    ],
    'ai_credits' => [
        'exhausted_owner' => '今月のAIクレジットを使い切りました。:date に更新されます。',
        'exhausted_member' => '今月のAIクレジットを使い切りました。プランの変更はワークスペースの所有者に依頼してください。',
        'low' => '今月のAIクレジットは :total 中あと :remaining です。',
        'change_plan' => 'プランを変更',
    ],
    'help' => [
        'source_code' => 'Source code',
    ],
    'country' => [
        'label' => '国',
        'select' => '国を選択',
        'blocked' => 'EU・英国ではまだご利用いただけません',
    ],
    'complimentary' => [
        'plan_given' => 'The RX Desk が提供するプラン',
        'until' => ':date まで',
        'no_end' => '終了日なし',
        'checkout_blocked' => 'このプランは The RX Desk から提供されているため、購読するには The RX Desk にお問い合わせください。',
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
            'capacity_ram' => 'メモリ使用率が高い',
            'capacity_load' => 'サーバー負荷が高い',
            'capacity_wait' => 'キューの待ち時間が長い',
            'capacity_disk_free' => 'ディスクの空き容量が少ない',
            'disk_guard' => 'ディスク容量がほぼ限界',
            'ai_provider_failure' => 'AIプロバイダーの障害',
            'nightwatch_burst' => '新しい問題が急増',
        ],
        'messages' => [
            'capacity_ram' => 'サーバーのメモリ使用率は :percent% で、アラートしきい値の :threshold% を超えています。',
            'capacity_load' => '5分間の平均負荷は :load で、アラートしきい値の :threshold を超えています。',
            'capacity_wait' => 'キューの最も長い待ち時間は :minutes 分で、アラートしきい値の :threshold_minutes 分を超えています。',
            'capacity_disk_free' => 'ディスクの空き容量は :free で、アラートしきい値の :threshold を下回っています。',
            'disk_guard' => 'ディスクの空き容量は :free で、ディスク保護しきい値の :threshold に近づいています。まもなくアップロードが停止します。',
            'ai_provider_failure' => ':model モデルでエラーが発生しました: :error',
            'nightwatch_burst' => '過去1時間に Nightwatch で :count 件の問題が発生しました。',
        ],
        'resolved_body' => 'このアラートは解除されました。同じ状態が再発すると再び通知されます。',
        'digest' => [
            'title' => '日次ダイジェスト',
            'cleared' => '解除されたアラート',
            'cleared_line' => '過去24時間に解除されたアラートです。',
            'issues' => '新しい問題',
            'failed_jobs' => '失敗したジョブ',
            'failed_jobs_line' => '{1} 昨日、1件のジョブが失敗しました|[0,*] 昨日、:count 件のジョブが失敗しました',
            'restore_drill' => 'リストア訓練',
            'restore_drill_line' => '前回のリストア訓練は :date でした。今すぐ実行してください。',
            'restore_drill_never' => 'リストア訓練はまだ記録されていません。今すぐ実行してください。',
        ],
        'preview' => [
            'urgent' => 'すぐに対応が必要な問題があります。',
            'today' => '今日対応が必要な問題があります。',
            'resolved' => 'アラートが解除されました。',
            'digest' => '日次のまとめです。',
        ],
        'sent_at' => '送信日時 :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => '初月 :discount オフ',
            'once_yearly' => '初年度 :discount オフ',
            'repeating_monthly' => '最初の :months か月間 :discount オフ',
            'repeating_yearly' => '初年度 :discount オフ',
            'forever' => 'ご契約中ずっと :discount オフ',
            'forever_until' => 'ご契約中ずっと :discount オフ（:date まで）',
            'trial' => ':days 日間の無料トライアル',
        ],
    ],

    'welcome_plan' => [
        'title' => 'プランを選択',
        'description' => '月払いまたは年払い。いつでも変更・解約できます。',
        'storage' => ':size のストレージ',
        'ai_credits' => '月 :credits AIクレジット',
        'unlimited' => 'ワークスペース、チャンネル、投稿、メンバーは無制限。',
        'start_trial' => ':days日間の無料トライアルを開始',
        'subscribe' => '登録する',
        'trial_terms' => ':date 以降は :price。いつでも解約できます。無料トライアルはカード1枚につき1回。',
        'subscribe_terms' => '本日 :price が請求されます。いつでも解約できます。',
        'lapsed_deletion' => 'サブスクリプションが終了しました。:date までに再度ご登録いただけない場合、データは削除されます。',
        'payment_retry' => [
            'title' => '初回のお支払いが完了しませんでした',
            'description' => 'お支払いが完了するまでアカウントは停止します。カードを更新してプランを開始してください。',
            'update_card' => 'カードを更新',
        ],
        'verify_email' => [
            'title' => 'メールアドレスを確認',
            'description' => ':email にリンクを送りました。開いてからここに戻って開始してください。',
            'resend' => 'メールを再送',
            'close' => '後で',
        ],
    ],
    'suspension' => [
        'title' => 'アカウント停止中',
        'description' => 'アカウントが停止されています。サポートにお問い合わせください。停止中は何も公開できず、連携ツールからアプリにアクセスできません。',
        'auto' => 'このページは自動で更新されます — 再読み込みは不要です。',
        'support_action' => 'サポートにメール',
    ],
    'availability' => [
        'schedule_refused' => ':network は現在ご利用いただけません。この投稿を下書きとして保存するか、復旧してからもう一度お試しください。',
        'banner' => ':network は現在ご利用いただけません。下書きは保存できますが、このチャンネルは復旧するまで投稿されません。',
    ],
    'publishing_block' => [
        'title' => '公開されなかった理由',
        'lapsed' => 'サブスクリプションが終了したため、この投稿は公開されませんでした。再度サブスクリプションを登録してから、もう一度お試しください。',
        'suspended' => 'アカウントが停止されているため、この投稿は公開されませんでした。サポートにお問い合わせのうえ、もう一度お試しください。',
        'unavailable' => ':network は現在ご利用いただけません。復旧してからもう一度お試しください。',
        'retry' => '再試行',
        'retry_started' => '投稿の公開を再試行しています。',
        'retry_failed' => '投稿の再試行に失敗しました。もう一度お試しください。',
    ],
    'lifecycle' => [
        'trial_ending' => [
            'subject' => '無料トライアルがまもなく終了します',
            'title' => '無料トライアルがまもなく終了します',
            'preview' => '初回のお支払いが近づいています。',
            'heading' => '無料トライアルがまもなく終了します',
            'body' => '初回のお支払いは :date です。',
            'amount' => ':amount が請求されます。',
            'button' => 'お支払いを管理',
            'cancel' => 'その前に請求ポータルでキャンセルできます。',
        ],
        'access_stopped' => [
            'subject' => 'サブスクリプションが終了しました',
            'title' => 'サブスクリプションが終了しました',
            'preview' => '再度ご登録いただけない場合、データは削除されます。',
            'heading' => 'サブスクリプションが終了しました',
            'body' => 'アカウントへのアクセスがなくなりました。:date までに再度ご登録いただけない場合、データは削除されます。',
            'export' => 'その前にデータのコピーをリクエストできます。',
            'button' => '再度登録する',
            'support' => 'エクスポートをご希望の場合は、',
            'support_link' => 'サポートにお問い合わせください。',
        ],
        'deletion_in_7_days' => [
            'subject' => 'データは7日後に削除されます',
            'title' => 'データは7日後に削除されます',
            'preview' => 'データを残すには再度ご登録ください。',
            'heading' => 'データは7日後に削除されます',
            'body' => ':date までに再度ご登録いただけない場合、データは削除されます。',
            'export' => 'その前にデータのコピーをリクエストできます。',
            'button' => '再度登録する',
            'support' => 'エクスポートをご希望の場合は、',
            'support_link' => 'サポートにお問い合わせください。',
        ],
        'deletion_in_1_day' => [
            'subject' => 'データは明日削除されます',
            'title' => 'データは明日削除されます',
            'preview' => '再度ご登録いただける最後の機会です。',
            'heading' => 'データは明日削除されます',
            'body' => ':date までに再度ご登録いただけない場合、データは削除されます。',
            'export' => 'その前にデータのコピーをリクエストできます。',
            'button' => '再度登録する',
            'support' => 'エクスポートをご希望の場合は、',
            'support_link' => 'サポートにお問い合わせください。',
        ],
        'member_deletion_in_7_days' => [
            'subject' => 'ワークスペースは7日後に削除されます',
            'title' => 'ワークスペースは7日後に削除されます',
            'preview' => 'その前に必要なものを保存してください。',
            'heading' => 'ワークスペースは7日後に削除されます',
            'body' => 'アカウント所有者のサブスクリプションが終了したため、ワークスペースは :date に削除されます。',
            'export' => '必要なものを保存するか、所有者に再度のご登録をお願いしてください。データのコピーをリクエストすることもできます。',
            'support' => 'エクスポートをご希望の場合は、',
            'support_link' => 'サポートにお問い合わせください。',
        ],
    ],
    'mail' => [
        'footer' => [
            'operator' => 'The RX Desk は :name（:address）が運営しています。',
        ],
    ],

];
