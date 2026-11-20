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
            'sign_in' => 'Σύνδεση',
        ],
        'hero' => [
            'title' => 'Όλες οι μάρκες που διαχειρίζεστε, σε ένα σημείο.',
            'lead' => 'Γράψτε μία φορά και προσαρμόστε το κείμενο σε κάθε δίκτυο. Τα κανάλια, το ημερολόγιο και οι εγκρίσεις κάθε πελάτη μένουν στον δικό του χώρο εργασίας.',
            'signup_soon' => 'Η εγγραφή ανοίγει σύντομα για όλους.',
        ],
        'hook' => [
            'workspaces' => 'Workspaces',
            'channels' => 'Κανάλια',
            'unlimited' => 'Απεριόριστα',
        ],
        'networks' => [
            'title' => 'Συνδέστε τα δίκτυα όπου ήδη δημοσιεύετε.',
            'lead' => 'Συνδέστε μία φορά τους λογαριασμούς κάθε μάρκας. Η ομάδα σας δημοσιεύει σε αυτούς χωρίς να μοιράζεται κωδικούς.',
        ],
        'composer' => [
            'title' => 'Γράψτε μία φορά. Ολοκληρώστε για κάθε δίκτυο.',
            'body' => 'Γράψτε το προσχέδιο σε έναν επεξεργαστή και μετά αλλάξτε το κείμενο, τα πολυμέσα και τις ρυθμίσεις που χρειάζεται κάθε δίκτυο. Οι προεπισκοπήσεις δείχνουν τη δημοσίευση όπως θα εμφανιστεί και τα όρια ελέγχονται καθώς πληκτρολογείτε, ώστε τίποτα να μην απορριφθεί κατά τη δημοσίευση.',
            'points' => [
                'per_network' => [
                    'title' => 'Κείμενο και πολυμέσα ανά δίκτυο.',
                    'text' => 'Ένα προσχέδιο μπορεί να έχει διαφορετική λεζάντα, εικόνα ή τίτλο σε κάθε κανάλι.',
                ],
                'checked' => [
                    'title' => 'Έλεγχος καθώς πληκτρολογείτε.',
                    'text' => 'Όρια χαρακτήρων, hashtags και κανόνες πολυμέσων για τα κανάλια που επιλέξατε.',
                ],
            ],
        ],
        'calendar' => [
            'title' => 'Ένα ημερολόγιο και μια ουρά για κάθε μάρκα.',
            'body' => 'Ορίστε τις ώρες δημοσίευσης κάθε καναλιού και προσθέστε δημοσιεύσεις στην ουρά: καθεμία παίρνει την επόμενη ελεύθερη ώρα. Αν αλλάξουν τα σχέδια, σύρετε μια δημοσίευση σε άλλη μέρα.',
            'points' => [
                'one_per_time' => [
                    'title' => 'Μία δημοσίευση ανά ώρα δημοσίευσης.',
                    'text' => 'Μια δημοσίευση στην ουρά κρατά την ώρα της και τίποτα δεν προγραμματίζεται πάνω της.',
                ],
                'drag' => [
                    'title' => 'Σύρετε για αλλαγή ημέρας.',
                    'text' => 'Μετακινήστε μια δημοσίευση σε άλλη μέρα κατευθείαν στο ημερολόγιο.',
                ],
            ],
        ],
        'workspaces' => [
            'title' => 'Ένας χώρος εργασίας για κάθε πελάτη.',
            'body' => 'Κάθε μάρκα κρατά τα δικά της κανάλια, ημερολόγιο, πολυμέσα και ομάδα. Οι δημοσιεύσεις ενός μέλους της ομάδας μπορούν να περιμένουν την έγκρισή σας πριν βγουν.',
            'points' => [
                'unlimited' => [
                    'title' => 'Όσους χώρους εργασίας χρειάζεστε.',
                    'text' => 'Έναν ανά πελάτη ή έναν για κάθε μάρκα που τρέχετε μόνοι σας.',
                ],
                'approvals' => [
                    'title' => 'Εγκρίσεις ανά μέλος.',
                    'text' => 'Ενεργοποιήστε τις για κάποιον νέο και απενεργοποιήστε τις όταν εμπιστεύεστε τις δημοσιεύσεις του.',
                ],
            ],
        ],
        'assistant' => [
            'title' => 'Ένας βοηθός στον επεξεργαστή. Και η δική σας AI μπορεί να δουλέψει εδώ.',
            'body' => 'Ο ενσωματωμένος βοηθός γράφει και ξαναγράφει προσχέδια στον επεξεργαστή. Συνδέστε το Claude ή το ChatGPT και θα μπορεί να γράφει προσχέδια, να προγραμματίζει και να διαβάζει αποτελέσματα στους χώρους εργασίας σας.',
            'points' => [
                'rewrite' => [
                    'title' => 'Επαναδιατύπωση όταν τη ζητάτε.',
                    'text' => 'Συντομέψτε ένα προσχέδιο, διατυπώστε το αλλιώς ή αλλάξτε το ύφος του.',
                ],
                'api' => [
                    'title' => 'MCP και REST API.',
                    'text' => 'Τα εργαλεία AI και τα δικά σας scripts δουλεύουν στους ίδιους χώρους εργασίας με εσάς.',
                ],
            ],
        ],
        'closing' => [
            'title' => 'Ξεκινήστε με μία μάρκα. Προσθέστε την επόμενη όταν την κερδίσετε.',
        ],
        'screenshots' => [
            'calendar_list' => 'Η ουρά δημοσιεύσεων ενός αρτοποιείου. Πρώτη είναι μια δημοσίευση Facebook από μέλος της ομάδας, που περιμένει έγκριση για αύριο στις 9:00, με κουμπιά για προσθήκη στην ουρά ή απόρριψη.',
            'composer' => 'Ο επεξεργαστής με ένα προσχέδιο για Instagram, Facebook και YouTube, την εικόνα του και τη ζωντανή προεπισκόπηση Instagram δίπλα.',
            'calendar_month' => 'Το μηνιαίο ημερολόγιο με τις δημοσιεύσεις Instagram και Facebook ενός αρτοποιείου, μία τη μέρα.',
            'approvals' => 'Η καρτέλα Εγκρίσεις με δύο δημοσιεύσεις από μέλος της ομάδας: μία για την ουρά και μία σε συγκεκριμένη ώρα, η καθεμία με κουμπί απόρριψης.',
            'assistant' => 'Ο Βοηθός AI δίπλα στον επεξεργαστή προτείνει μια πιο σύντομη εκδοχή του προσχεδίου.',
        ],
        'footer' => [
            'terms' => 'Όροι',
            'privacy' => 'Απόρρητο',
            'source_code' => 'Πηγαίος κώδικας',
            'built_on' => 'Βασισμένο στο :upstream (AGPL-3.0)',
            'status' => 'Κατάσταση',
        ],
        'skip_to_content' => 'Μετάβαση στο περιεχόμενο',
    ],
];
