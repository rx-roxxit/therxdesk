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
            'landing' => 'Planeje, escreva e agende posts para cada marca que você gerencia, com um espaço de trabalho, um calendário e aprovações para cada cliente.',
            'privacy' => 'O que o The RX Desk coleta e por quê, o que ele lê do Google, do YouTube e da Meta, e como excluir seus dados.',
            'terms' => 'Os termos de uso do The RX Desk: quem pode usá-lo, seu conteúdo, as redes que você conecta e pelo que somos responsáveis.',
        ],
        'nav' => [
            'sign_in' => 'Entrar',
        ],
        'hero' => [
            'title' => 'Todas as marcas que você gerencia em um só lugar.',
            'lead' => 'Escreva uma vez e adapte para cada rede. Os canais, o calendário e as aprovações de cada cliente ficam no próprio espaço de trabalho.',
            'signup_soon' => 'O cadastro abre para todos em breve.',
        ],
        'hook' => [
            'workspaces' => 'Espaços de trabalho',
            'channels' => 'Canais',
            'unlimited' => 'Ilimitados',
        ],
        'networks' => [
            'title' => 'Conecte as redes em que você já publica.',
            'lead' => 'Conecte as contas de cada marca uma vez. Sua equipe publica nelas sem trocar senhas.',
        ],
        'composer' => [
            'title' => 'Escreva uma vez. Ajuste para cada rede.',
            'body' => 'Escreva o rascunho em um só editor e depois ajuste o texto, as mídias e as configurações que cada rede pede. As pré-visualizações mostram o post como ele vai aparecer, e os limites são verificados enquanto você digita, para você achar problemas antes de agendar.',
            'points' => [
                'per_network' => [
                    'title' => 'Texto e mídia por rede.',
                    'text' => 'Um rascunho pode ter legenda, imagem ou título diferentes em cada rede.',
                ],
                'checked' => [
                    'title' => 'Verificado enquanto você digita.',
                    'text' => 'Limites de caracteres, hashtags e regras de mídia dos canais que você escolheu.',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'Um calendário e uma fila para cada marca.',
            'body' => 'Defina os horários de postagem de cada canal e adicione posts à fila: cada um fica com o próximo horário livre. Se os planos mudarem, arraste um post para outro dia.',
            'points' => [
                'one_per_time' => [
                    'title' => 'Um post por horário.',
                    'text' => 'Um post na fila mantém seu horário, e nada é agendado por cima.',
                ],
                'drag' => [
                    'title' => 'Arraste para reagendar.',
                    'text' => 'Mova um post para outro dia direto no calendário.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'Um espaço de trabalho para cada cliente.',
            'body' => 'Cada marca tem seus próprios canais, calendário, mídias e equipe. Os posts de alguém da equipe podem esperar sua aprovação antes de sair.',
            'points' => [
                'unlimited' => [
                    'title' => 'Quantos espaços de trabalho você precisar.',
                    'text' => 'Um por cliente, ou um por marca que você mesmo cuida.',
                ],
                'approvals' => [
                    'title' => 'Aprovações por pessoa.',
                    'text' => 'Ative para quem acabou de chegar e desative quando confiar nos posts dessa pessoa.',
                ],
            ],
        ],
        'assistant' => [
            'title' => 'Um assistente no editor. Sua própria IA também pode trabalhar aqui.',
            'body' => 'O assistente integrado escreve e reescreve rascunhos no editor. Conecte o Claude ou o ChatGPT, e ele pode criar rascunhos, agendar e ler resultados nos seus espaços de trabalho.',
            'points' => [
                'rewrite' => [
                    'title' => 'Reescrita quando você pedir.',
                    'text' => 'Encurte um rascunho, reformule ou mude o tom.',
                ],
                'api' => [
                    'title' => 'MCP e API REST.',
                    'text' => 'Suas ferramentas de IA e seus próprios scripts trabalham nos mesmos espaços de trabalho que você.',
                ],
            ],
        ],
        'closing' => [
            'title' => 'Comece com uma marca. Adicione a próxima quando conquistá-la.',
        ],
        'screenshots' => [
            'calendar_list' => 'A fila de posts de uma padaria. Primeiro vem um post do Facebook de alguém da equipe, esperando aprovação para amanhã às 9:00, com botões para adicioná-lo à fila ou rejeitá-lo.',
            'composer' => 'O editor com um rascunho para Instagram, Facebook e YouTube, a imagem dele e as pré-visualizações do Instagram e do Facebook ao lado.',
            'calendar_month' => 'O calendário do mês com os posts de Instagram e Facebook de uma padaria, um por dia.',
            'approvals' => 'A aba Aprovações com dois posts de alguém da equipe: um para a fila e outro em um horário definido, cada um com um botão para rejeitá-lo.',
            'assistant' => 'O Assistente de IA, ao lado do editor, sugere uma versão mais curta do rascunho.',
        ],
        'footer' => [
            'terms' => 'Termos',
            'privacy' => 'Privacidade',
            'source_code' => 'Código-fonte',
            'built_on' => 'Baseado no :upstream (AGPL-3.0)',
            'status' => 'Status',
        ],
        'skip_to_content' => 'Ir para o conteúdo',
    ],
];
