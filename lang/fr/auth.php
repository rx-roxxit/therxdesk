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

    'failed' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
    'password' => 'Le mot de passe fourni est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Veuillez réessayer dans :seconds secondes.',

    'flash' => [
        'welcome' => 'Bienvenue sur The RX Desk !',
        'welcome_trial' => 'Bienvenue sur The RX Desk ! Votre essai a commencé.',
    ],

    'legal' => 'En continuant, vous acceptez nos <a href=":terms_url" target="_blank">Conditions d\'utilisation</a> et notre <a href=":privacy_url" target="_blank">Politique de confidentialité</a>.',
    'preview_alt' => 'Un aperçu de l\'app The RX Desk',

    'or_continue_with' => 'Ou continuer avec',
    'or_continue_with_email' => 'Ou continuer avec l\'e-mail',
    'google_login' => 'Se connecter avec Google',
    'google_signup' => 'S\'inscrire avec Google',
    'github_login' => 'Se connecter avec GitHub',
    'github_signup' => 'S\'inscrire avec GitHub',
    'github_email_unavailable' => 'Impossible de récupérer votre e-mail depuis GitHub. Rendez votre e-mail GitHub public ou accordez l\'autorisation d\'accès à l\'e-mail, puis réessayez.',

    'login' => [
        'title' => 'Connexion à The RX Desk',
        'page_title' => 'Connexion',
        'email' => 'Adresse e-mail',
        'password' => 'Mot de passe',
        'show_password' => 'Afficher le mot de passe',
        'hide_password' => 'Masquer le mot de passe',
        'forgot_password' => 'Mot de passe oublié ?',
        'remember_me' => 'Se souvenir de moi',
        'submit' => 'Se connecter',
        'no_account' => 'Vous n\'avez pas de compte ?',
        'sign_up' => 'S\'inscrire',
    ],

    'register' => [
        'title' => 'Créer un compte The RX Desk',
        'page_title' => 'Inscription',
        'name' => 'Nom',
        'name_placeholder' => 'Nom complet',
        'email' => 'Adresse e-mail',
        'password' => 'Mot de passe',
        'show_password' => 'Afficher le mot de passe',
        'hide_password' => 'Masquer le mot de passe',
        'submit' => 'Créer un compte',
        'has_account' => 'Vous avez déjà un compte ?',
        'log_in' => 'Se connecter',
        'password_requirements' => [
            'min' => 'Au moins :count caractères',
            'mixed_case' => 'Majuscules et minuscules',
            'number' => 'Au moins 1 chiffre',
            'symbol' => 'Au moins 1 symbole',
        ],
    ],

    'forgot_password' => [
        'title' => 'Mot de passe oublié',
        'description' => 'Saisissez votre e-mail pour recevoir un lien de réinitialisation du mot de passe',
        'page_title' => 'Mot de passe oublié',
        'email' => 'Adresse e-mail',
        'submit' => 'Envoyer le lien de réinitialisation',
        'return_to' => 'Ou revenez à la',
        'log_in' => 'connexion',
    ],

    'reset_password' => [
        'title' => 'Réinitialiser le mot de passe',
        'description' => 'Veuillez saisir votre nouveau mot de passe ci-dessous',
        'page_title' => 'Réinitialiser le mot de passe',
        'email' => 'E-mail',
        'password' => 'Mot de passe',
        'confirm_password' => 'Confirmer le mot de passe',
        'confirm_placeholder' => 'Confirmer le mot de passe',
        'submit' => 'Réinitialiser le mot de passe',
    ],

    'verify_email' => [
        'title' => 'Vérifier l\'e-mail',
        'description' => 'Veuillez vérifier votre adresse e-mail en cliquant sur le lien que nous venons de vous envoyer.',
        'page_title' => 'Vérification de l\'e-mail',
        'link_sent' => 'Un nouveau lien de vérification a été envoyé à l\'adresse e-mail que vous avez fournie lors de l\'inscription.',
        'resend' => 'Renvoyer l\'e-mail de vérification',
        'log_out' => 'Se déconnecter',
    ],

    'accept_invite' => [
        'page_title' => 'Accepter l\'invitation',
        'title' => 'Vous avez été invité !',
        'description' => 'Vous avez été invité à rejoindre l\'espace de travail :workspace.',
        'workspace' => 'Espace de travail',
        'your_role' => 'Votre rôle',
        'email' => 'E-mail',
        'accept' => 'Accepter l\'invitation',
        'decline' => 'Refuser l\'invitation',
        'login_prompt' => 'Connectez-vous ou créez un compte pour accepter cette invitation.',
        'log_in' => 'Se connecter',
        'create_account' => 'Créer un compte',
        'expired_title' => 'Cette invitation n’est plus valide',
        'expired_description' => 'L’espace de travail de cette invitation a été supprimé. Demandez une nouvelle invitation au propriétaire du compte si vous avez encore besoin d’accès.',
        'expired_action' => 'Retour à l’accueil',
    ],

];
