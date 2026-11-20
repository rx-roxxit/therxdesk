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
            'landing' => '担当するすべてのブランドの投稿を計画、作成、予約できます。クライアントごとにワークスペース、カレンダー、承認を用意できます。',
            'privacy' => 'The RX Desk が収集する情報とその理由、Google・YouTube・Meta から読み取る情報、データの削除方法。',
            'terms' => 'The RX Desk の利用規約：利用できる人、あなたのコンテンツ、接続するネットワーク、当方の責任範囲。',
        ],
        'nav' => [
            'sign_in' => 'ログイン',
        ],
        'hero' => [
            'title' => '運営するすべてのブランドを、ひとつの場所に。',
            'lead' => '一度書いて、各ネットワークに合わせて調整できます。クライアントごとのチャンネル、カレンダー、承認はそれぞれのワークスペースにまとまります。',
            'signup_soon' => 'まもなく誰でも登録できるようになります。',
        ],
        'hook' => [
            'workspaces' => 'ワークスペース',
            'channels' => 'チャンネル',
            'unlimited' => '無制限',
        ],
        'networks' => [
            'title' => 'いま投稿しているネットワークをつなぎましょう。',
            'lead' => 'ブランドごとのアカウントは一度つなぐだけ。チームはパスワードを共有せずに投稿できます。',
        ],
        'composer' => [
            'title' => '一度書いて、ネットワークごとに仕上げる。',
            'body' => '1つのエディターで下書きを書き、各ネットワークに必要なテキスト、メディア、設定を調整します。プレビューで実際の見え方を確認でき、入力中に制限をチェックするので、予約する前に問題に気づけます。',
            'points' => [
                'per_network' => [
                    'title' => 'ネットワークごとのテキストとメディア。',
                    'text' => '1つの下書きで、ネットワークごとに異なるキャプション、画像、タイトルを設定できます。',
                ],
                'checked' => [
                    'title' => '入力しながらチェック。',
                    'text' => '選んだチャンネルの文字数制限、ハッシュタグ、メディアのルールを確認します。',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'ブランドごとのカレンダーとキュー。',
            'body' => 'チャンネルごとに投稿時間を決めて、投稿をキューに追加します。各投稿は次の空いている時間に入ります。予定が変わったら、投稿を別の日にドラッグするだけです。',
            'points' => [
                'one_per_time' => [
                    'title' => '1つの投稿時間に1件。',
                    'text' => 'キューに入った投稿は時間を保ち、その上に別の投稿が入ることはありません。',
                ],
                'drag' => [
                    'title' => 'ドラッグで日程変更。',
                    'text' => 'カレンダー上で投稿を別の日へ移動できます。',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'クライアントごとにワークスペースを。',
            'body' => 'ブランドごとにチャンネル、カレンダー、メディア、チームを分けて管理できます。チームメンバーの投稿は、公開前にあなたの承認を待つようにできます。',
            'points' => [
                'unlimited' => [
                    'title' => 'ワークスペースは必要なだけ。',
                    'text' => 'クライアントごとに1つ、または自分で運営するブランドごとに1つ。',
                ],
                'approvals' => [
                    'title' => 'メンバーごとの承認。',
                    'text' => '新しいメンバーにはオンにし、投稿を任せられるようになったらオフにできます。',
                ],
            ],
        ],
        'assistant' => [
            'title' => 'エディターにアシスタント。あなたのAIもここで働けます。',
            'body' => '内蔵アシスタントがエディターで下書きを書き、書き直します。ClaudeやChatGPTをつなげば、ワークスペースで下書き作成、予約、結果の確認ができます。',
            'points' => [
                'rewrite' => [
                    'title' => '頼めば書き直し。',
                    'text' => '下書きを短くしたり、言い換えたり、トーンを変えたりできます。',
                ],
                'api' => [
                    'title' => 'MCPとREST API。',
                    'text' => 'AIツールや自作のスクリプトも、あなたと同じワークスペースで動きます。',
                ],
            ],
        ],
        'closing' => [
            'title' => 'まずは1つのブランドから。次を獲得したら追加しましょう。',
        ],
        'screenshots' => [
            'calendar_list' => 'パン屋の投稿キュー。先頭には、明日9:00の承認待ちになっているチームメンバーのFacebook投稿があり、キューに追加するボタンと却下ボタンが付いています。',
            'composer' => 'Instagram、Facebook、YouTube向けの下書きとその画像、横にInstagramとFacebookのライブプレビューが表示されたエディター。',
            'calendar_month' => 'パン屋のInstagramとFacebookの投稿が1日1件ずつ並ぶ月表示のカレンダー。',
            'approvals' => 'チームメンバーからの2件の投稿が表示された承認タブ。1件はキュー向け、もう1件は時間指定で、それぞれに却下ボタンがあります。',
            'assistant' => 'エディターの横で、AIアシスタントが下書きの短い版を提案しています。',
        ],
        'footer' => [
            'terms' => '利用規約',
            'privacy' => 'プライバシー',
            'source_code' => 'ソースコード',
            'built_on' => ':upstream をベースに構築 (AGPL-3.0)',
            'status' => 'ステータス',
        ],
        'skip_to_content' => 'コンテンツへスキップ',
    ],
];
