<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.',
    'password' => 'Das angegebene Passwort ist falsch.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuche es in :seconds Sekunden erneut.',

    'flash' => [
        'welcome' => 'Willkommen bei The RX Desk!',
        'welcome_trial' => 'Willkommen bei The RX Desk! Deine Testphase hat begonnen.',
    ],

    'legal' => 'Indem du fortfährst, stimmst du unseren <a href=":terms_url" target="_blank">Nutzungsbedingungen</a> und unserer <a href=":privacy_url" target="_blank">Datenschutzerklärung</a> zu.',
    'preview_alt' => 'Eine Vorschau der RX Desk App',

    'or_continue_with' => 'Oder fortfahren mit',
    'or_continue_with_email' => 'Oder mit E-Mail fortfahren',
    'google_login' => 'Mit Google anmelden',
    'google_signup' => 'Mit Google registrieren',
    'github_login' => 'Mit GitHub anmelden',
    'github_signup' => 'Mit GitHub registrieren',
    'github_email_unavailable' => 'Deine E-Mail-Adresse konnte nicht von GitHub abgerufen werden. Mache deine GitHub-E-Mail-Adresse öffentlich oder erteile die Berechtigung für den E-Mail-Zugriff und versuche es dann erneut.',

    'login' => [
        'title' => 'Bei The RX Desk anmelden',
        'page_title' => 'Anmelden',
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'show_password' => 'Passwort anzeigen',
        'hide_password' => 'Passwort verbergen',
        'forgot_password' => 'Passwort vergessen?',
        'remember_me' => 'Angemeldet bleiben',
        'submit' => 'Anmelden',
        'no_account' => 'Noch kein Konto?',
        'sign_up' => 'Registrieren',
    ],

    'register' => [
        'title' => 'The RX Desk-Konto erstellen',
        'page_title' => 'Registrieren',
        'name' => 'Name',
        'name_placeholder' => 'Vollständiger Name',
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'show_password' => 'Passwort anzeigen',
        'hide_password' => 'Passwort verbergen',
        'submit' => 'Konto erstellen',
        'has_account' => 'Hast du bereits ein Konto?',
        'log_in' => 'Anmelden',
        'password_requirements' => [
            'min' => 'Mindestens :count Zeichen',
            'mixed_case' => 'Groß- und Kleinbuchstaben',
            'number' => 'Mindestens 1 Zahl',
            'symbol' => 'Mindestens 1 Sonderzeichen',
        ],
    ],

    'forgot_password' => [
        'title' => 'Passwort vergessen',
        'description' => 'Gib deine E-Mail-Adresse ein, um einen Link zum Zurücksetzen des Passworts zu erhalten',
        'page_title' => 'Passwort vergessen',
        'email' => 'E-Mail-Adresse',
        'submit' => 'Link zum Zurücksetzen des Passworts senden',
        'return_to' => 'Oder zurück zur',
        'log_in' => 'Anmeldung',
    ],

    'reset_password' => [
        'title' => 'Passwort zurücksetzen',
        'description' => 'Bitte gib unten dein neues Passwort ein',
        'page_title' => 'Passwort zurücksetzen',
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'confirm_password' => 'Passwort bestätigen',
        'confirm_placeholder' => 'Passwort bestätigen',
        'submit' => 'Passwort zurücksetzen',
    ],

    'verify_email' => [
        'title' => 'E-Mail bestätigen',
        'description' => 'Bitte bestätige deine E-Mail-Adresse, indem du auf den Link klickst, den wir dir gerade per E-Mail gesendet haben.',
        'page_title' => 'E-Mail-Bestätigung',
        'link_sent' => 'Ein neuer Bestätigungslink wurde an die E-Mail-Adresse gesendet, die du bei der Registrierung angegeben hast.',
        'resend' => 'Bestätigungs-E-Mail erneut senden',
        'log_out' => 'Abmelden',
    ],

    'accept_invite' => [
        'page_title' => 'Einladung annehmen',
        'title' => 'Du wurdest eingeladen!',
        'description' => 'Du wurdest eingeladen, dem Workspace :workspace beizutreten.',
        'workspace' => 'Workspace',
        'your_role' => 'Deine Rolle',
        'email' => 'E-Mail',
        'accept' => 'Einladung annehmen',
        'decline' => 'Einladung ablehnen',
        'login_prompt' => 'Melde dich an oder erstelle ein Konto, um diese Einladung anzunehmen.',
        'log_in' => 'Anmelden',
        'create_account' => 'Konto erstellen',
        'expired_title' => 'Diese Einladung ist nicht mehr gültig',
        'expired_description' => 'Der Workspace für diese Einladung wurde gelöscht. Bitte den Kontoinhaber um eine neue Einladung, falls du weiterhin Zugriff brauchst.',
        'expired_action' => 'Zur Startseite',
    ],

];
