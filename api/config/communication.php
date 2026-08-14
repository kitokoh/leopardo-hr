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
        // PA2-JOB-003 - real Twilio-backed SMS/WhatsApp providers now exist
        // (see Notification/Infrastructure/Services/Providers) but default
        // to the safe audit-only fallback, same as an unset/unknown provider
        // name. Operators opt in explicitly via
        // COMMUNICATION_SMS_PROVIDER=twilio / COMMUNICATION_WHATSAPP_PROVIDER=twilio
        // plus the TWILIO_* credentials once a real account is available.
        'sms' => env('COMMUNICATION_SMS_PROVIDER', 'audit'),
        // 'audit' (default) logs every dispatch without calling any real
        // provider; 'whatsapp_cloud' switches to the Meta WhatsApp Business
        // Cloud API once WHATSAPP_PHONE_NUMBER_ID/WHATSAPP_ACCESS_TOKEN are
        // set (PA2-COMM-008) — falls back to 'audit' automatically if either
        // secret is missing.
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

    /*
    |--------------------------------------------------------------------------
    | Email provider retry (PA2-COMM-007)
    |--------------------------------------------------------------------------
    |
    | Bounded caller-side retry applied by `CommunicationService` when the
    | configured email provider is `mail` (`MailMessageProvider`). A
    | transient SMTP/API error is retried up to `max_attempts` times with
    | exponential backoff starting at `base_delay_ms`, before the dispatch
    | is recorded as a final `failed` audit event.
    |
    */

    'email_retry' => [
        'max_attempts' => (int) env('COMMUNICATION_EMAIL_MAX_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('COMMUNICATION_EMAIL_RETRY_BASE_DELAY_MS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS / WhatsApp provider retry (PA2-JOB-003)
    |--------------------------------------------------------------------------
    |
    | Same bounded caller-side retry policy as `email_retry` above, applied
    | by `CommunicationService` to the Twilio-backed SMS and WhatsApp
    | providers.
    |
    */

    'sms_retry' => [
        'max_attempts' => (int) env('COMMUNICATION_SMS_MAX_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('COMMUNICATION_SMS_RETRY_BASE_DELAY_MS', 500),
    ],

    'whatsapp_retry' => [
        'max_attempts' => (int) env('COMMUNICATION_WHATSAPP_MAX_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('COMMUNICATION_WHATSAPP_RETRY_BASE_DELAY_MS', 500),
    ],

    'public_metadata_keys' => [
        'absence_id',
        'attendance_log_id',
        'auto_check_out',
        'category',
        'company_id',
        'conversation_message_id',
        'conversation_thread_id',
        'date',
        'document_type',
        'employee_id',
        'feature_key',
        'hours_worked',
        'locale',
        'payment_document_id',
        'payment_reference',
        'payroll_run_id',
        'redirect_url',
        'salary_advance_id',
        'severity',
        'source',
        'status',
    ],

    /*
    |--------------------------------------------------------------------------
    | Localizable templates
    |--------------------------------------------------------------------------
    |
    | PA2-COMM-006 — Every template only carries a `category` plus the
    | translation keys (`lang/{locale}/notifications.php`) used to resolve
    | its title/body for the recipient's locale. `vars` lists the caller
    | context keys that get forwarded to `trans()` as replacement
    | parameters (e.g. `:task`, `:author`, `:period`), so a caller can
    | still pass an explicit `title`/`body` in `$context` to override the
    | localized text (used by callers with fully custom content, like
    | manager-authored announcements).
    |
    */

    'templates' => [
        'generic' => [
            'category' => 'system',
            'title_key' => 'notifications.generic_title',
            'body_key' => 'notifications.generic_body',
        ],
        'absence_approved' => [
            'category' => 'hr',
            'title_key' => 'notifications.absence_approved_title',
            'body_key' => 'notifications.absence_approved_body',
        ],
        'absence_rejected' => [
            'category' => 'hr',
            'title_key' => 'notifications.absence_rejected_title',
            'body_key' => 'notifications.absence_rejected_body',
        ],
        'payroll_ready' => [
            'category' => 'payroll',
            'title_key' => 'notifications.payroll_ready_title',
            'body_key' => 'notifications.payroll_ready_body',
        ],
        'bulk_payment_completed' => [
            'category' => 'payroll',
            'title_key' => 'notifications.bulk_payment_completed_title',
            'body_key' => 'notifications.bulk_payment_completed_body',
            'vars' => ['succeeded', 'total', 'failed'],
        ],
        'bulk_payment_completed_with_errors' => [
            'category' => 'payroll',
            'title_key' => 'notifications.bulk_payment_completed_with_errors_title',
            'body_key' => 'notifications.bulk_payment_completed_with_errors_body',
            'vars' => ['succeeded', 'total', 'failed'],
        ],
        'security_alert' => [
            'category' => 'security',
            'title_key' => 'notifications.security_alert_title',
            'body_key' => 'notifications.security_alert_body',
        ],
        'task_comment_added' => [
            'category' => 'task',
            'title_key' => 'notifications.task_comment_added_title',
            'body_key' => 'notifications.task_comment_added_body',
            'vars' => ['task', 'author'],
        ],
        'conversation_message_received' => [
            'category' => 'communication',
            'title_key' => 'notifications.conversation_message_received_title',
            'body_key' => 'notifications.conversation_message_received_body',
            'vars' => ['author', 'thread'],
        ],
        'platform_announcement' => [
            'category' => 'platform',
            'title_key' => 'notifications.platform_announcement_title',
            'body_key' => 'notifications.platform_announcement_body',
        ],
        'company_announcement' => [
            'category' => 'system',
            'title_key' => 'notifications.company_announcement_title',
            'body_key' => 'notifications.company_announcement_body',
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
        'salary_advance_disputed' => [
            'category' => 'payroll',
            'title' => 'Avance sur salaire contestée',
            'body' => 'Un employé a contesté le paiement de son avance sur salaire.',
        ],
        'salary_advance_dispute_resolved' => [
            'category' => 'payroll',
            'title' => 'Contestation d\'avance résolue',
            'body' => 'La contestation de l\'avance sur salaire a été résolue par votre manager.',
        ],
        // PA2-COMM-010 — Payment document lifecycle: lets the employee know a
        // receipt/payslip/bordereau is being prepared, then that it is ready,
        // without the UI having to block or poll blindly for it.
        'payment_document_processing' => [
            'category' => 'payroll',
            'title_key' => 'notifications.payment_document_processing_title',
            'body_key' => 'notifications.payment_document_processing_body',
        ],
        'payment_document_ready' => [
            'category' => 'payroll',
            'title_key' => 'notifications.payment_document_ready_title',
            'body_key' => 'notifications.payment_document_ready_body',
        ],
        'payment_document_failed' => [
            'category' => 'payroll',
            'title_key' => 'notifications.payment_document_failed_title',
            'body_key' => 'notifications.payment_document_failed_body',
        ],
        'attendance_auto_closed' => [
            'category' => 'hr',
            'title' => 'Journée de pointage clôturée automatiquement',
            'body' => 'Nous avons détecté un oubli de départ et clôturé votre journée automatiquement selon la règle de votre entreprise. Vérifiez les heures calculées et demandez une correction si besoin.',
        ],
        'attendance_geofence_alert' => [
            'category' => 'attendance',
            'title' => 'Pointage hors zone',
            'body' => 'Un employé a pointé hors de la zone géographique attendue.',
        ],
        'payment_document_ready' => [
            // PA2-PAY-004 — async payslip/advance-receipt PDF generation:
            // notifies the employee as soon as the document is stored and
            // downloadable, instead of them having to poll for it.
            'category' => 'payroll',
            'title_key' => 'notifications.payment_document_ready_title',
            'body_key' => 'notifications.payment_document_ready_body',
        ],
        'tax_rate_approved' => [
            // ADMIN-PAIE (#1813) — workflow de validation des taux légaux :
            // notifie le soumissionnaire quand le platform_admin approuve.
            'category' => 'payroll',
            'title_key' => 'notifications.tax_rate_approved_title',
            'body_key' => 'notifications.tax_rate_approved_body',
            'vars' => ['rate_name', 'country'],
        ],
        'tax_rate_rejected' => [
            // ADMIN-PAIE (#1813) — notifie le soumissionnaire du motif de
            // rejet d'une modification de taux légal.
            'category' => 'payroll',
            'title_key' => 'notifications.tax_rate_rejected_title',
            'body_key' => 'notifications.tax_rate_rejected_body',
            'vars' => ['rate_name', 'country', 'reason'],
        ],
    ],
];
