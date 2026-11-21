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

    'failed' => 'Deze gegevens komen niet overeen met onze administratie.',
    'password' => 'Het opgegeven wachtwoord is onjuist.',
    'throttle' => 'Te veel inlogpogingen. Probeer het over :seconds seconden opnieuw.',

    'flash' => [
        'welcome' => 'Welkom bij The RX Desk!',
        'welcome_trial' => 'Welkom bij The RX Desk! Je proefperiode is gestart.',
    ],

    'legal' => 'Door door te gaan ga je akkoord met onze <a href=":terms_url" target="_blank">Servicevoorwaarden</a> en <a href=":privacy_url" target="_blank">Privacybeleid</a>.',
    'preview_alt' => 'Een voorbeeld van de RX Desk-app',

    'or_continue_with' => 'Of ga verder met',
    'or_continue_with_email' => 'Of ga verder met e-mail',
    'google_login' => 'Inloggen met Google',
    'google_signup' => 'Aanmelden met Google',
    'github_login' => 'Inloggen met GitHub',
    'github_signup' => 'Aanmelden met GitHub',
    'github_email_unavailable' => 'Kan je e-mailadres niet ophalen van GitHub. Maak je GitHub-e-mailadres openbaar of verleen de e-mailscope en probeer het opnieuw.',

    'login' => [
        'title' => 'Inloggen bij The RX Desk',
        'page_title' => 'Inloggen',
        'email' => 'E-mailadres',
        'password' => 'Wachtwoord',
        'show_password' => 'Wachtwoord tonen',
        'hide_password' => 'Wachtwoord verbergen',
        'forgot_password' => 'Wachtwoord vergeten?',
        'remember_me' => 'Ingelogd blijven',
        'submit' => 'Inloggen',
        'no_account' => 'Nog geen account?',
        'sign_up' => 'Aanmelden',
    ],

    'register' => [
        'title' => 'Maak een The RX Desk-account',
        'page_title' => 'Registreren',
        'name' => 'Naam',
        'name_placeholder' => 'Volledige naam',
        'email' => 'E-mailadres',
        'password' => 'Wachtwoord',
        'show_password' => 'Wachtwoord tonen',
        'hide_password' => 'Wachtwoord verbergen',
        'submit' => 'Account aanmaken',
        'has_account' => 'Heb je al een account?',
        'log_in' => 'Inloggen',
        'password_requirements' => [
            'min' => 'Minstens :count tekens',
            'mixed_case' => 'Hoofdletters en kleine letters',
            'number' => 'Minstens 1 cijfer',
            'symbol' => 'Minstens 1 symbool',
        ],
    ],

    'forgot_password' => [
        'title' => 'Wachtwoord vergeten',
        'description' => 'Voer je e-mailadres in om een link te ontvangen om je wachtwoord opnieuw in te stellen',
        'page_title' => 'Wachtwoord vergeten',
        'email' => 'E-mailadres',
        'submit' => 'Stuur reset-link per e-mail',
        'return_to' => 'Of ga terug naar',
        'log_in' => 'inloggen',
    ],

    'reset_password' => [
        'title' => 'Wachtwoord opnieuw instellen',
        'description' => 'Voer hieronder je nieuwe wachtwoord in',
        'page_title' => 'Wachtwoord opnieuw instellen',
        'email' => 'E-mail',
        'password' => 'Wachtwoord',
        'confirm_password' => 'Bevestig wachtwoord',
        'confirm_placeholder' => 'Bevestig wachtwoord',
        'submit' => 'Wachtwoord opnieuw instellen',
    ],

    'verify_email' => [
        'title' => 'E-mail verifiëren',
        'description' => 'Verifieer je e-mailadres door op de link te klikken die we je zojuist per e-mail hebben gestuurd.',
        'page_title' => 'E-mailverificatie',
        'link_sent' => 'Er is een nieuwe verificatielink verstuurd naar het e-mailadres dat je bij registratie hebt opgegeven.',
        'resend' => 'Verificatiemail opnieuw versturen',
        'log_out' => 'Uitloggen',
    ],

    'accept_invite' => [
        'page_title' => 'Uitnodiging accepteren',
        'title' => 'Je bent uitgenodigd!',
        'description' => 'Je bent uitgenodigd om deel te nemen aan de workspace :workspace.',
        'workspace' => 'Workspace',
        'your_role' => 'Jouw rol',
        'email' => 'E-mail',
        'accept' => 'Uitnodiging accepteren',
        'decline' => 'Uitnodiging weigeren',
        'login_prompt' => 'Log in of maak een account aan om deze uitnodiging te accepteren.',
        'log_in' => 'Inloggen',
        'create_account' => 'Account aanmaken',
        'expired_title' => 'Deze uitnodiging is niet meer geldig',
        'expired_description' => 'De workspace van deze uitnodiging is verwijderd. Vraag de accounteigenaar om een nieuwe uitnodiging als je nog toegang nodig hebt.',
        'expired_action' => 'Naar home',
    ],

];
