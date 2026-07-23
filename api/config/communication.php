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
        'auto_check_out',
        'category',
        'company_id',
        'date',
        'employee_id',
        'feature_key',
        'hours_worked',
        'locale',
        'payroll_run_id',
        'redirect_url',
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
        'attendance_auto_closed' => [
            'category' => 'hr',
            'title' => 'Journée de pointage clôturée automatiquement',
            'body' => 'Nous avons détecté un oubli de départ et clôturé votre journée automatiquement selon la règle de votre entreprise. Vérifiez les heures calculées et demandez une correction si besoin.',
        ],
    ],
];
