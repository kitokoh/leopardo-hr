<?php

declare(strict_types=1);

/**
 * Configuration du module CRM client — Issue #5726 (canal email).
 *
 * Le fournisseur email est interchangeable (contrat EmailProviderInterface) :
 * `log` (défaut — aucun email réellement envoyé, idéal pour les tests et la
 * CI) ou `mail` (Laravel Mail, MAIL_MAILER de l'environnement).
 */
return [
    'email' => [
        'provider' => env('CRM_EMAIL_PROVIDER', 'log'),
        'webhook_secret' => env('CRM_EMAIL_WEBHOOK_SECRET'),
        'rate_limit_per_hour' => (int) env('CRM_EMAIL_RATE_LIMIT_PER_HOUR', 500),
        'transactional_rate_limit_per_hour' => (int) env('CRM_EMAIL_TRANSACTIONAL_RATE_LIMIT_PER_HOUR', 2000),
    ],
];
