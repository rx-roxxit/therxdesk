<?php

declare(strict_types=1);

/*
| The RX Desk's own copy. Every new key ships in all 16 locales; upstream
| never touches this file.
*/

return [
    'ai' => [
        'unavailable' => 'Nie można teraz połączyć się z AI. Spróbuj ponownie za kilka minut.',
    ],
    'ai_credits' => [
        'exhausted_owner' => 'Wykorzystano kredyty AI na ten miesiąc. Odnowią się :date.',
        'exhausted_member' => 'Wykorzystano kredyty AI na ten miesiąc. Poproś właściciela konta o zmianę planu.',
        'low' => 'W tym miesiącu zostały tylko :remaining z :total kredytów AI.',
        'change_plan' => 'Zmień plan',
    ],
    'help' => [
        'source_code' => 'Source code',
    ],
    'country' => [
        'label' => 'Kraj',
        'select' => 'Wybierz swój kraj',
        'blocked' => 'Jeszcze niedostępne w UE i Wielkiej Brytanii',
    ],
    'complimentary' => [
        'plan_given' => 'Plan przyznany przez The RX Desk',
        'until' => 'Do :date',
        'no_end' => 'Bez daty końca',
        'checkout_blocked' => 'Twój plan pochodzi od The RX Desk, więc aby subskrybować, skontaktuj się z The RX Desk.',
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
            'capacity_ram' => 'Wysokie użycie pamięci',
            'capacity_load' => 'Wysokie obciążenie serwera',
            'capacity_wait' => 'Długie oczekiwanie w kolejce',
            'capacity_disk_free' => 'Mało wolnego miejsca na dysku',
            'disk_guard' => 'Miejsce na dysku prawie wyczerpane',
            'ai_provider_failure' => 'Awaria dostawcy AI',
            'nightwatch_burst' => 'Nasilenie nowych problemów',
        ],
        'messages' => [
            'capacity_ram' => 'Pamięć serwera wynosi :percent%, powyżej progu alertu :threshold%.',
            'capacity_load' => 'Średnie obciążenie z 5 minut wynosi :load, powyżej progu alertu :threshold.',
            'capacity_wait' => 'Najdłuższe oczekiwanie w kolejce to :minutes min, powyżej progu alertu :threshold_minutes min.',
            'capacity_disk_free' => 'Wolne miejsce na dysku to :free, poniżej progu alertu :threshold.',
            'disk_guard' => 'Wolne miejsce na dysku to :free, blisko progu ochrony dysku :threshold. Przesyłanie wkrótce się zatrzyma.',
            'ai_provider_failure' => 'Model :model nie powiódł się: :error',
            'nightwatch_burst' => 'W ostatniej godzinie w Nightwatch otwarto problemy: :count.',
        ],
        'resolved_body' => 'Ten alert został wycofany. Wróci, jeśli warunek się powtórzy.',
        'digest' => [
            'title' => 'Dzienny przegląd',
            'cleared' => 'Alerty wycofane',
            'cleared_line' => 'Te alerty wycofano w ciągu ostatnich 24 godzin.',
            'issues' => 'Nowe problemy',
            'failed_jobs' => 'Nieudane zadania',
            'failed_jobs_line' => '{1} Jedno zadanie nie powiodło się wczoraj|[2,4] :count zadania nie powiodły się wczoraj|[0,*] :count zadań nie powiodło się wczoraj',
            'restore_drill' => 'Test odtwarzania',
            'restore_drill_line' => 'Ostatni test odtwarzania odbył się :date. Przeprowadź go teraz.',
            'restore_drill_never' => 'Nie zapisano jeszcze żadnego testu odtwarzania. Przeprowadź go teraz.',
        ],
        'preview' => [
            'urgent' => 'Problem wymaga teraz uwagi.',
            'today' => 'Problem wymaga uwagi dziś.',
            'resolved' => 'Alert został wycofany.',
            'digest' => 'Twój dzienny przegląd.',
        ],
        'sent_at' => 'Wysłano :time',
    ],
    'offer' => [
        'line' => [
            'once_monthly' => ':discount zniżki na pierwszy miesiąc',
            'once_yearly' => ':discount zniżki na pierwszy rok',
            'repeating_monthly' => '{1} :discount zniżki na pierwszy miesiąc|[2,4] :discount zniżki na pierwsze :months miesiące|[5,*] :discount zniżki na pierwsze :months miesięcy',
            'repeating_yearly' => ':discount zniżki na pierwszy rok',
            'forever' => ':discount zniżki przez cały okres subskrypcji',
            'forever_until' => ':discount zniżki przez cały okres subskrypcji, do :date',
            'trial' => '{1} :days dzień bezpłatnej próby|[2,*] :days dni bezpłatnej próby',
        ],
    ],

    'welcome_plan' => [
        'title' => 'Wybierz plan',
        'description' => 'Płać miesięcznie lub rocznie. Zmień lub anuluj w dowolnym momencie.',
        'storage' => ':size miejsca',
        'ai_credits' => ':credits kredytów AI miesięcznie',
        'unlimited' => 'Nieograniczone przestrzenie, kanały, posty i członkowie.',
        'start_trial' => 'Zacznij :days-dniowy okres próbny',
        'subscribe' => 'Subskrybuj',
        'trial_terms' => 'Potem :price od :date. Anuluj w dowolnym momencie. Jeden okres próbny na kartę.',
        'subscribe_terms' => 'Dziś pobierzemy :price. Anuluj w dowolnym momencie.',
        'lapsed_deletion' => 'Twoja subskrypcja się zakończyła. Twoje dane zostaną usunięte :date, jeśli nie subskrybujesz ponownie.',
        'payment_retry' => [
            'title' => 'Pierwsza płatność nie powiodła się',
            'description' => 'Konto pozostaje wstrzymane, dopóki płatność nie zostanie zaksięgowana. Zaktualizuj kartę, aby rozpocząć plan.',
            'update_card' => 'Zaktualizuj kartę',
        ],
        'verify_email' => [
            'title' => 'Potwierdź e-mail',
            'description' => 'Wysłaliśmy link na :email. Otwórz go i wróć tutaj, aby zacząć.',
            'resend' => 'Wyślij ponownie',
            'close' => 'Nie teraz',
        ],
    ],
    'suspension' => [
        'title' => 'Konto zawieszone',
        'description' => 'Konto jest zawieszone, skontaktuj się z pomocą techniczną. Dopóki pozostaje zawieszone, nic nie można opublikować, a połączone narzędzia nie mają dostępu do aplikacji.',
        'auto' => 'Ta strona odświeża się automatycznie — nie musisz jej przeładowywać.',
        'support_action' => 'Napisz do pomocy technicznej',
    ],
    'availability' => [
        'schedule_refused' => ':network jest teraz niedostępny. Zapisz ten post jako szkic albo spróbuj ponownie, gdy znów będzie dostępny.',
        'banner' => ':network jest teraz niedostępny. Możesz zapisywać szkice, ale ten kanał nie będzie publikować, dopóki nie wróci.',
    ],
    'publishing_block' => [
        'title' => 'Dlaczego post nie został opublikowany',
        'lapsed' => 'Twoja subskrypcja się zakończyła, więc ten post nie został opublikowany. Subskrybuj ponownie, a następnie spróbuj jeszcze raz.',
        'suspended' => 'Twoje konto jest zawieszone, więc ten post nie został opublikowany. Skontaktuj się z pomocą techniczną, a następnie spróbuj jeszcze raz.',
        'unavailable' => ':network jest teraz niedostępny. Spróbuj ponownie, gdy znów będzie dostępny.',
        'retry' => 'Spróbuj ponownie',
        'retry_started' => 'Ponawianie publikacji posta.',
        'retry_failed' => 'Nie udało się ponowić publikacji posta. Spróbuj ponownie.',
    ],
    'lifecycle' => [
        'trial_ending' => [
            'subject' => 'Twój okres próbny wkrótce się kończy',
            'title' => 'Twój okres próbny wkrótce się kończy',
            'preview' => 'Zbliża się Twoja pierwsza płatność.',
            'heading' => 'Twój okres próbny wkrótce się kończy',
            'body' => 'Twoja pierwsza płatność nastąpi :date.',
            'amount' => 'Pobierzemy :amount.',
            'button' => 'Zarządzaj rozliczeniami',
            'cancel' => 'Do tego czasu możesz anulować w portalu rozliczeniowym.',
        ],
        'access_stopped' => [
            'subject' => 'Twoja subskrypcja się zakończyła',
            'title' => 'Twoja subskrypcja się zakończyła',
            'preview' => 'Twoje dane zostaną usunięte, jeśli nie subskrybujesz ponownie.',
            'heading' => 'Twoja subskrypcja się zakończyła',
            'body' => 'Nie masz już dostępu do swojego konta. Twoje dane zostaną usunięte :date, jeśli nie subskrybujesz ponownie.',
            'export' => 'Do tego czasu możesz poprosić o kopię swoich danych.',
            'button' => 'Subskrybuj ponownie',
            'support' => 'Aby poprosić o eksport,',
            'support_link' => 'skontaktuj się z pomocą techniczną.',
        ],
        'deletion_in_7_days' => [
            'subject' => 'Twoje dane zostaną usunięte za 7 dni',
            'title' => 'Twoje dane zostaną usunięte za 7 dni',
            'preview' => 'Subskrybuj ponownie, aby zachować swoje dane.',
            'heading' => 'Twoje dane zostaną usunięte za 7 dni',
            'body' => 'Twoje dane zostaną usunięte :date, jeśli nie subskrybujesz ponownie.',
            'export' => 'Do tego czasu możesz poprosić o kopię swoich danych.',
            'button' => 'Subskrybuj ponownie',
            'support' => 'Aby poprosić o eksport,',
            'support_link' => 'skontaktuj się z pomocą techniczną.',
        ],
        'deletion_in_1_day' => [
            'subject' => 'Twoje dane zostaną usunięte jutro',
            'title' => 'Twoje dane zostaną usunięte jutro',
            'preview' => 'To Twoja ostatnia szansa na ponowną subskrypcję.',
            'heading' => 'Twoje dane zostaną usunięte jutro',
            'body' => 'Twoje dane zostaną usunięte :date, jeśli nie subskrybujesz ponownie.',
            'export' => 'Do tego czasu możesz poprosić o kopię swoich danych.',
            'button' => 'Subskrybuj ponownie',
            'support' => 'Aby poprosić o eksport,',
            'support_link' => 'skontaktuj się z pomocą techniczną.',
        ],
        'member_deletion_in_7_days' => [
            'subject' => 'Twoja przestrzeń zostanie usunięta za 7 dni',
            'title' => 'Twoja przestrzeń zostanie usunięta za 7 dni',
            'preview' => 'Zapisz to, czego potrzebujesz, zanim to nastąpi.',
            'heading' => 'Twoja przestrzeń zostanie usunięta za 7 dni',
            'body' => 'Subskrypcja właściciela konta się zakończyła, więc Twoja przestrzeń zostanie usunięta :date.',
            'export' => 'Zapisz to, czego potrzebujesz, lub poproś właściciela o ponowną subskrypcję. Możesz też poprosić o kopię swoich danych.',
            'support' => 'Aby poprosić o eksport,',
            'support_link' => 'skontaktuj się z pomocą techniczną.',
        ],
    ],
    'mail' => [
        'footer' => [
            'operator' => 'The RX Desk jest prowadzony przez :name, :address.',
        ],
    ],

];
