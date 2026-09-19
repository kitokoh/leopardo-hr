<?php

return [
    'notifications' => [
        'booking_confirmed' => 'Réservation confirmée :reference',
        'booking_cancelled' => 'Réservation annulée :reference',
        'ticket_issued' => 'Billet émis :ticket',
        'reminder_title' => 'Rappel de voyage',
        'reminder_body' => 'Votre trajet :code part à :time.',
    ],
    'reports' => [
        'export_job_queued' => 'Export programmé',
        'export_job_failed' => 'Export en échec',
    ],
    'content' => [
        'article_published' => 'Article publié',
        'article_flagged' => 'Article signalé pour modération',
        'comment_pending' => 'Commentaire en attente de modération',
    ],
    'console' => [
        'expire_adverts_description' => 'Expire les annonces validées dont expires_at est dépassé, puis archive les expirées anciennes (TRAVEL-908/#6111).',
        'expire_adverts_no_tenant' => 'Aucun tenant — rien à expirer.',
        'expire_adverts_tenant_summary' => 'Tenant :company : :expired expirée(s), :archived archivée(s).',
        'expire_adverts_total' => 'Total : :expired annonce(s) expirée(s), :archived archivée(s).',
    ],
    'marketplace' => [
        'booking_not_found' => 'Réservation marketplace introuvable.',
    ],
];
