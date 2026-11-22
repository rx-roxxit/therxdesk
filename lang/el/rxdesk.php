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
        'label' => 'Χώρα',
        'select' => 'Επίλεξε τη χώρα σου',
        'blocked' => 'Δεν είναι ακόμη διαθέσιμο στην ΕΕ ή το Ηνωμένο Βασίλειο',
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
            'capacity_ram' => 'Υψηλή χρήση μνήμης',
            'capacity_load' => 'Υψηλό φορτίο διακομιστή',
            'capacity_wait' => 'Μεγάλη αναμονή στην ουρά',
            'capacity_disk_free' => 'Λίγος ελεύθερος χώρος δίσκου',
            'disk_guard' => 'Ο χώρος δίσκου σχεδόν εξαντλήθηκε',
            'nightwatch_burst' => 'Έξαρση νέων ζητημάτων',
        ],
        'messages' => [
            'capacity_ram' => 'Η μνήμη του διακομιστή είναι στο :percent%, πάνω από το όριο ειδοποίησης του :threshold%.',
            'capacity_load' => 'Το μέσο φορτίο 5 λεπτών είναι :load, πάνω από το όριο ειδοποίησης :threshold.',
            'capacity_wait' => 'Η μεγαλύτερη αναμονή στην ουρά είναι :minutes λεπτά, πάνω από το όριο ειδοποίησης :threshold_minutes λεπτά.',
            'capacity_disk_free' => 'Ο ελεύθερος χώρος δίσκου είναι :free, κάτω από το όριο ειδοποίησης :threshold.',
            'disk_guard' => 'Ο ελεύθερος χώρος δίσκου είναι :free, κοντά στο όριο προστασίας δίσκου :threshold. Οι μεταφορτώσεις θα σταματήσουν σύντομα.',
            'nightwatch_burst' => 'Την τελευταία ώρα άνοιξαν :count ζητήματα στο Nightwatch.',
        ],
        'resolved_body' => 'Αυτή η ειδοποίηση έκλεισε. Θα σταλεί ξανά αν η συνθήκη επανέλθει.',
        'digest' => [
            'title' => 'Ημερήσια σύνοψη',
            'cleared' => 'Ειδοποιήσεις που έκλεισαν',
            'cleared_line' => 'Αυτές οι ειδοποιήσεις έκλεισαν τις τελευταίες 24 ώρες.',
            'issues' => 'Νέα ζητήματα',
            'failed_jobs' => 'Αποτυχημένες εργασίες',
            'failed_jobs_line' => '{1} Μία εργασία απέτυχε χθες|[0,*] :count εργασίες απέτυχαν χθες',
            'restore_drill' => 'Δοκιμή επαναφοράς',
            'restore_drill_line' => 'Η τελευταία δοκιμή επαναφοράς έγινε στις :date. Εκτελέστε μία τώρα.',
            'restore_drill_never' => 'Δεν έχει καταγραφεί ακόμη καμία δοκιμή επαναφοράς. Εκτελέστε μία τώρα.',
        ],
        'preview' => [
            'urgent' => 'Ένα πρόβλημα χρειάζεται προσοχή τώρα.',
            'today' => 'Ένα πρόβλημα χρειάζεται προσοχή σήμερα.',
            'resolved' => 'Μια ειδοποίηση έκλεισε.',
            'digest' => 'Η ημερήσια σύνοψή σας.',
        ],
        'sent_at' => 'Στάλθηκε :time',
    ],
    'welcome_plan' => [
        'title' => 'Διάλεξε το πρόγραμμά σου',
        'description' => 'Πληρώνεις μηνιαία ή ετήσια. Αλλαγή ή ακύρωση οποιαδήποτε στιγμή.',
        'storage' => ':size αποθηκευτικός χώρος',
        'ai_credits' => ':credits μονάδες AI τον μήνα',
        'unlimited' => 'Απεριόριστοι χώροι εργασίας, κανάλια, αναρτήσεις και μέλη.',
        'start_trial' => 'Ξεκίνα δωρεάν δοκιμή :days ημερών',
        'subscribe' => 'Συνδρομή',
        'trial_terms' => 'Μετά :price από :date. Ακύρωση οποιαδήποτε στιγμή. Μία δωρεάν δοκιμή ανά κάρτα.',
        'subscribe_terms' => 'Θα χρεωθείς :price σήμερα. Ακύρωση οποιαδήποτε στιγμή.',
        'payment_retry' => [
            'title' => 'Η πρώτη σου πληρωμή δεν ολοκληρώθηκε',
            'description' => 'Ο λογαριασμός σου μένει σε παύση μέχρι να πληρωθεί. Ενημέρωσε την κάρτα σου για να ξεκινήσεις το πρόγραμμά σου.',
            'update_card' => 'Ενημέρωση κάρτας',
        ],
        'verify_email' => [
            'title' => 'Επιβεβαίωσε το email σου',
            'description' => 'Στείλαμε έναν σύνδεσμο στο :email. Άνοιξέ τον και επέστρεψε εδώ για να ξεκινήσεις.',
            'resend' => 'Αποστολή ξανά',
            'close' => 'Όχι τώρα',
        ],
    ],
];
