<?php

/**
 * Configuration du CRM client (module tenant, issue #5742 / ADR-CRM-004).
 *
 * Règles :
 *  - `enabled` : commutateur global du module. Le flag TENANT (`crm` dans
 *    companies.features) reste l'autorisation par entreprise (opt-in plateforme,
 *    désactivé par défaut) ; ce commutateur global coupe tout le module.
 *  - `kill_switch` : frein d'urgence global. Activé → le module CRM est
 *    bloqué partout (403 CRM_KILL_SWITCH_ACTIVE), quel que soit le flag tenant.
 *    Runbook : docs/GESTION_PROJET/RUNBOOK_KILL_SWITCH_CRM.md.
 *  - `integrations` : canaux (whatsapp, email, sms) FERMÉS PAR DÉFAUT.
 *    Chaque canal est évalué côté serveur : commutateur global (env) ET flag
 *    tenant (companies.metadata.crm.integrations.<key>.enabled). Un canal
 *    n'est jamais autorisé par le seul frontend.
 */

return [
    'enabled' => (bool) env('CRM_ENABLED', true),

    'kill_switch' => [
        // Frein d'urgence GLOBAL (tous tenants, tous canaux).
        'enabled' => (bool) env('CRM_KILL_SWITCH', false),
    ],

    'integrations' => [
        'whatsapp' => [
            'enabled' => (bool) env('CRM_WHATSAPP_ENABLED', false),
        ],
        'email' => [
            'enabled' => (bool) env('CRM_EMAIL_ENABLED', false),
        ],
        'sms' => [
            'enabled' => (bool) env('CRM_SMS_ENABLED', false),
        ],
    ],
];
