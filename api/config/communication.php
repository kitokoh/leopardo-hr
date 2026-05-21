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

    'public_metadata_keys' => [
        'absence_id',
        'attendance_log_id',
        'category',
        'company_id',
        'employee_id',
        'feature_key',
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
    ],
];
