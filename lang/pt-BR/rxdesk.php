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
        'label' => 'País',
        'select' => 'Escolha seu país',
        'blocked' => 'Ainda não disponível na UE ou no Reino Unido',
    ],
    'complimentary' => [
        'plan_given' => 'Plano oferecido pela The RX Desk',
        'until' => 'Até :date',
        'no_end' => 'Sem data de término',
        'checkout_blocked' => 'Seu plano foi oferecido pela The RX Desk; fale com a The RX Desk para assinar.',
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
            'capacity_ram' => 'Alto uso de memória',
            'capacity_load' => 'Carga alta no servidor',
            'capacity_wait' => 'Espera longa na fila',
            'capacity_disk_free' => 'Pouco espaço livre em disco',
            'disk_guard' => 'Espaço em disco quase esgotado',
            'nightwatch_burst' => 'Surto de novos problemas',
        ],
        'messages' => [
            'capacity_ram' => 'A memória do servidor está em :percent%, acima do limite de alerta de :threshold%.',
            'capacity_load' => 'A carga média de 5 minutos é :load, acima do limite de alerta de :threshold.',
            'capacity_wait' => 'A maior espera na fila é de :minutes minutos, acima do limite de alerta de :threshold_minutes minutos.',
            'capacity_disk_free' => 'O espaço livre em disco é :free, abaixo do limite de alerta de :threshold.',
            'disk_guard' => 'O espaço livre em disco é :free, perto do limite de proteção de disco de :threshold. Os uploads vão parar em breve.',
            'nightwatch_burst' => ':count problemas foram abertos no Nightwatch na última hora.',
        ],
        'resolved_body' => 'Este alerta foi resolvido. Ele será enviado de novo se a condição voltar.',
        'digest' => [
            'title' => 'Resumo diário',
            'cleared' => 'Alertas resolvidos',
            'cleared_line' => 'Estes alertas foram resolvidos nas últimas 24 horas.',
            'issues' => 'Novos problemas',
            'failed_jobs' => 'Falhas de tarefas',
            'failed_jobs_line' => '{1} Uma tarefa falhou ontem|[0,*] :count tarefas falharam ontem',
            'restore_drill' => 'Teste de restauração',
            'restore_drill_line' => 'O último teste de restauração foi em :date. Faça um agora.',
            'restore_drill_never' => 'Nenhum teste de restauração foi registrado ainda. Faça um agora.',
        ],
        'preview' => [
            'urgent' => 'Um problema precisa de atenção agora.',
            'today' => 'Um problema precisa de atenção hoje.',
            'resolved' => 'Um alerta foi resolvido.',
            'digest' => 'Seu resumo diário.',
        ],
        'sent_at' => 'Enviado em :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => ':discount de desconto no seu primeiro mês',
            'once_yearly' => ':discount de desconto no seu primeiro ano',
            'repeating_monthly' => '{1} :discount de desconto no seu primeiro mês|[2,*] :discount de desconto nos seus primeiros :months meses',
            'repeating_yearly' => ':discount de desconto no seu primeiro ano',
            'forever' => ':discount de desconto enquanto você continuar assinante',
            'forever_until' => ':discount de desconto enquanto você continuar assinante, até :date',
            'trial' => '{1} Teste grátis de :days dia|[2,*] Teste grátis de :days dias',
        ],
    ],

    'welcome_plan' => [
        'title' => 'Escolha seu plano',
        'description' => 'Pague mensal ou anualmente. Mude ou cancele quando quiser.',
        'storage' => ':size de armazenamento',
        'ai_credits' => ':credits créditos de IA por mês',
        'unlimited' => 'Espaços de trabalho, canais, publicações e membros ilimitados.',
        'start_trial' => 'Teste grátis por :days dias',
        'subscribe' => 'Assinar',
        'trial_terms' => 'Depois :price a partir de :date. Cancele quando quiser. Um teste grátis por cartão.',
        'subscribe_terms' => 'Você será cobrado :price hoje. Cancele quando quiser.',
        'lapsed_deletion' => 'Sua assinatura terminou. Seus dados serão excluídos em :date, a menos que você assine novamente.',
        'payment_retry' => [
            'title' => 'Seu primeiro pagamento não foi concluído',
            'description' => 'Sua conta fica pausada até o pagamento ser feito. Atualize seu cartão para começar seu plano.',
            'update_card' => 'Atualizar cartão',
        ],
        'verify_email' => [
            'title' => 'Verifique seu e-mail',
            'description' => 'Enviamos um link para :email. Abra-o e volte aqui para começar.',
            'resend' => 'Reenviar e-mail',
            'close' => 'Agora não',
        ],
    ],
    'suspension' => [
        'title' => 'Conta suspensa',
        'description' => 'Conta suspensa, fale com o suporte. Enquanto estiver suspensa, nada pode ser publicado e as ferramentas conectadas não conseguem acessar o app.',
        'auto' => 'Esta página atualiza automaticamente — não precisa recarregar.',
        'support_action' => 'Enviar e-mail ao suporte',
    ],
    'availability' => [
        'schedule_refused' => ':network não está disponível no momento. Salve esta publicação como rascunho ou tente novamente quando ele voltar.',
        'banner' => ':network não está disponível no momento. Você pode salvar rascunhos, mas este canal não vai publicar até ele voltar.',
    ],
    'publishing_block' => [
        'title' => 'Por que não foi publicado',
        'lapsed' => 'Sua assinatura terminou, então esta publicação não foi publicada. Assine novamente e tente outra vez.',
        'suspended' => 'Sua conta está suspensa, então esta publicação não foi publicada. Fale com o suporte e tente outra vez.',
        'unavailable' => ':network não está disponível no momento. Tente novamente quando ele voltar.',
        'retry' => 'Tentar novamente',
        'retry_started' => 'Tentando publicar novamente.',
        'retry_failed' => 'Não foi possível tentar publicar novamente. Tente outra vez.',
    ],
    'lifecycle' => [
        'trial_ending' => [
            'subject' => 'Sua avaliação gratuita termina em breve',
            'title' => 'Sua avaliação gratuita termina em breve',
            'preview' => 'Seu primeiro pagamento está chegando.',
            'heading' => 'Sua avaliação gratuita termina em breve',
            'body' => 'Seu primeiro pagamento será em :date.',
            'amount' => 'Você será cobrado :amount.',
            'button' => 'Gerenciar pagamento',
            'cancel' => 'Você pode cancelar antes disso no portal de cobrança.',
        ],
        'access_stopped' => [
            'subject' => 'Sua assinatura terminou',
            'title' => 'Sua assinatura terminou',
            'preview' => 'Seus dados serão excluídos, a menos que você assine novamente.',
            'heading' => 'Sua assinatura terminou',
            'body' => 'Você não tem mais acesso à sua conta. Seus dados serão excluídos em :date, a menos que você assine novamente.',
            'export' => 'Você pode pedir uma cópia dos seus dados antes disso.',
            'button' => 'Assinar novamente',
            'support' => 'Para pedir uma exportação,',
            'support_link' => 'fale com o suporte.',
        ],
        'deletion_in_7_days' => [
            'subject' => 'Seus dados serão excluídos em 7 dias',
            'title' => 'Seus dados serão excluídos em 7 dias',
            'preview' => 'Assine novamente para manter seus dados.',
            'heading' => 'Seus dados serão excluídos em 7 dias',
            'body' => 'Seus dados serão excluídos em :date, a menos que você assine novamente.',
            'export' => 'Você pode pedir uma cópia dos seus dados antes disso.',
            'button' => 'Assinar novamente',
            'support' => 'Para pedir uma exportação,',
            'support_link' => 'fale com o suporte.',
        ],
        'deletion_in_1_day' => [
            'subject' => 'Seus dados serão excluídos amanhã',
            'title' => 'Seus dados serão excluídos amanhã',
            'preview' => 'Esta é sua última chance de assinar novamente.',
            'heading' => 'Seus dados serão excluídos amanhã',
            'body' => 'Seus dados serão excluídos em :date, a menos que você assine novamente.',
            'export' => 'Você pode pedir uma cópia dos seus dados antes disso.',
            'button' => 'Assinar novamente',
            'support' => 'Para pedir uma exportação,',
            'support_link' => 'fale com o suporte.',
        ],
        'member_deletion_in_7_days' => [
            'subject' => 'Seu espaço de trabalho será excluído em 7 dias',
            'title' => 'Seu espaço de trabalho será excluído em 7 dias',
            'preview' => 'Salve o que precisar antes disso.',
            'heading' => 'Seu espaço de trabalho será excluído em 7 dias',
            'body' => 'A assinatura do dono da conta terminou, então seu espaço de trabalho será excluído em :date.',
            'export' => 'Salve o que precisar ou peça ao dono para assinar novamente. Você também pode pedir uma cópia dos seus dados.',
            'support' => 'Para pedir uma exportação,',
            'support_link' => 'fale com o suporte.',
        ],
    ],
    'mail' => [
        'footer' => [
            'operator' => 'The RX Desk é operado por :name, :address.',
        ],
    ],

];
