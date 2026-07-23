<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Communication orchestration
    |--------------------------------------------------------------------------
    |
    | The internal communication layer starts every message from the same
    | service so preferences, audit logs and provider fallbacks stay coherent
    | across web, mobile, kiosk and future AI-triggered actions.
    |
    */

    'queue' => env('COMMUNICATION_QUEUE', 'notifications'),

    'providers' => [
        'email' => env('COMMUNICATION_EMAIL_PROVIDER', 'mail'),
        'push' => env('COMMUNICATION_PUSH_PROVIDER', 'firebase'),
        'sms' => env('COMMUNICATION_SMS_PROVIDER', 'audit'),
        'whatsapp' => env('COMMUNICATION_WHATSAPP_PROVIDER', 'audit'),
    ],

    'default_channels' => ['app', 'push'],

    'quiet_hours' => [
        'bypass_categories' => ['security'],
        'defer_status' => 'skipped',
    ],

    'monthly_channel_quotas' => [
        'sms' => (int) env('COMMUNICATION_SMS_MONTHLY_QUOTA', 0),
        'whatsapp' => (int) env('COMMUNICATION_WHATSAPP_MONTHLY_QUOTA', 0),
    ],

    'public_metadata_keys' => [
        'absence_id',
        'attendance_log_id',
        'category',
        'company_id',
        'employee_id',
        'feature_key',
        'locale',
        'payment_reference',
        'payroll_run_id',
        'redirect_url',
        'salary_advance_id',
        'severity',
        'source',
        'status',
    ],

    'templates' => [
        'generic' => [
            'category' => 'system',
            'title' => 'Notification Leopardo RH',
            'body' => 'Une nouvelle information est disponible dans votre espace.',
        ],
        'absence_approved' => [
            'category' => 'hr',
            'title' => 'Demande d’absence approuvée',
            'body' => 'Votre demande d’absence a été approuvée.',
        ],
        'absence_rejected' => [
            'category' => 'hr',
            'title' => 'Demande d’absence refusée',
            'body' => 'Votre demande d’absence a été refusée.',
        ],
        'payroll_ready' => [
            'category' => 'payroll',
            'title' => 'Bulletin de paie disponible',
            'body' => 'Votre nouveau bulletin de paie est disponible dans votre espace.',
        ],
        'security_alert' => [
            'category' => 'security',
            'title' => 'Alerte de sécurité',
            'body' => 'Une action sensible vient d’être détectée sur votre compte.',
        ],
        'task_comment_added' => [
            'category' => 'task',
            'title' => 'Nouveau commentaire sur une tâche',
            'body' => 'Un nouveau commentaire a été ajouté à une tâche que vous suivez.',
        ],
        'platform_announcement' => [
            'category' => 'platform',
            'title' => 'Annonce de la plateforme Leopardo',
            'body' => 'Une nouvelle annonce de la plateforme est disponible.',
        ],
        'salary_advance_manager_approved' => [
            'category' => 'payroll',
            'title' => 'Avance sur salaire approuvée',
            'body' => 'Votre demande d’avance sur salaire a été approuvée par votre manager. Le paiement sera déclaré prochainement.',
        ],
        'salary_advance_rejected' => [
            'category' => 'payroll',
            'title' => 'Avance sur salaire refusée',
            'body' => 'Votre demande d’avance sur salaire a été refusée.',
        ],
        'salary_advance_payment_declared' => [
            'category' => 'payroll',
            'title' => 'Paiement de l’avance déclaré',
            'body' => 'Le paiement de votre avance sur salaire a été déclaré. Merci de confirmer sa réception dans l’application.',
        ],
        'salary_advance_received' => [
            'category' => 'payroll',
            'title' => 'Réception d’avance confirmée',
            'body' => 'L’employé a confirmé avoir reçu l’avance sur salaire.',
        ],
    ],
];
