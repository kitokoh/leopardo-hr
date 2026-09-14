<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            // Issue #1766 : une MAIL_URL vide (ex. .env.example par défaut) ne
            // doit pas être traitée comme une URL — Laravel 12 parse le DSN
            // vide et écrase `transport` par null → « Unsupported mail
            // transport [] » à chaque envoi. `?: null` préserve le transport
            // smtp configuré ci-dessous tant que MAIL_URL est absent/vide.
            'url' => env('MAIL_URL') ?: null,
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            // Issue #5115 : `encryption` manquait → MAIL_ENCRYPTION (tls/ssl)
            // était ignoré et les envois SMTP partaient en clair / échouaient.
            // `timeout` borné (15s) : sans lui, un connect SMTP bloqué attend
            // le défaut socket et finit en fatal max_execution_time (30s).
            // `stream.socket.bindto` IPv4 : un connect SMTP vers Mailgun
            // blackhole sur IPv6 (Render) — 0.0.0.0:0 force le résolveur IPv4.
            'encryption' => env('MAIL_ENCRYPTION'),
            'timeout' => env('MAIL_SMTP_TIMEOUT', 15),
            'stream' => ['socket' => ['bindto' => env('MAIL_SMTP_BINDTO', '0.0.0.0:0')]],
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        // Issue #5139 : l'egress Render bloque le SMTP sortant (587 comme
        // 465 — timeout TCP vers smtp.mailgun.org). Bascule sur l'API HTTP
        // Mailgun (port 443) : transport natif Laravel (symfony/mailgun-mailer
        // déjà présent), idempotent avec le reste de la config.
        'mailgun' => [
            'transport' => 'mailgun',
            'domain' => env('MAILGUN_DOMAIN'),
            'secret' => env('MAILGUN_SECRET'),
            'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
            // 'scheme' => 'https',
            'timeout' => env('MAIL_SMTP_TIMEOUT', 15),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@leopardo-rh.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Leopardo RH')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Identité de marque des e-mails (issue #7346)
    |--------------------------------------------------------------------------
    |
    | Source UNIQUE de la charte e-mail, consommée par
    | `resources/views/emails/layouts/base.blade.php` : plus aucune couleur,
    | police ou nom de marque ne doit être écrit en dur dans un template.
    |
    | ⚠️ `name` ne retombe JAMAIS sur `config('app.name')` ni sur `APP_NAME` :
    | le défaut de Laravel est la chaîne « Laravel », qui s'est déjà retrouvée
    | dans des e-mails clients. Le repli est la marque produit, explicitement.
    |
    | ⚠️ `support_address` est DISTINCT de `from.address` : `from` reçoit
    | `noreply@…` (adresse d'envoi, non relevée). Répondre à un e-mail doit
    | écrire à une adresse réellement lue — le pied de page utilisait `from`,
    | donc « écrivez au support » envoyait vers une boîte sans lecteur.
    |
    */

    'brand' => [
        // Marque produit, PAS `APP_NAME` : le défaut de Laravel est la chaîne
        // « Laravel », qui s'est déjà retrouvée dans des e-mails clients. La
        // marque e-mail est donc explicite et vérifiée par un test.
        'name' => env('MAIL_BRAND_NAME', 'Leopardo RH'),
        'tagline' => env('MAIL_BRAND_TAGLINE'),
        // Logo facultatif : les clients de messagerie bloquent les images par
        // défaut, le nom de marque reste donc toujours affiché en texte.
        'logo_url' => env('MAIL_BRAND_LOGO_URL'),
        // Tokens produit (teal) — miroir de `--color-brand-*` côté web/admin.
        'primary_color' => env('MAIL_BRAND_PRIMARY_COLOR', '#0d9488'),
        // Pile de polices des e-mails — ici et pas dans le layout : c'est un
        // token de charte, et la surface `resources/views/emails` est surveillée
        // par la garde i18n (aucun littéral ajouté dans un template).
        'font_stack' => env(
            'MAIL_BRAND_FONT_STACK',
            "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
        ),
        'primary_dark_color' => env('MAIL_BRAND_PRIMARY_DARK_COLOR', '#042f2e'),
        'support_address' => env('MAIL_SUPPORT_ADDRESS', 'support@leopardo-rh.com'),
        'website_url' => env('MAIL_BRAND_WEBSITE_URL', env('FRONTEND_URL', 'https://leopardo-rh.com')),
        // Mentions légales (raison sociale, adresse) : affichées si renseignées.
        'legal_name' => env('MAIL_BRAND_LEGAL_NAME'),
        'legal_address' => env('MAIL_BRAND_LEGAL_ADDRESS'),
    ],

];
