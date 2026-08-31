<?php

declare(strict_types=1);

/*
 * Registre RGPD CRM — manifeste versionné des traitements de données
 * personnelles du CRM client (issue #5739).
 *
 * Source documentaire : docs/security/CRM_REGISTRE_RGPD.md (registre
 * versionné lié à ce manifeste). Toute nouvelle donnée PII CRM doit être
 * déclarée ICI (finalité, base légale, durée, responsable) avant d'être
 * collectée — même règle que le registre RH #5713.
 *
 * Structure par entrée :
 *   - table          : table tenant concernée (le schéma dépend du tenant) ;
 *   - purpose        : finalité du traitement ;
 *   - legal_basis    : base légale (RGPD art. 6) ;
 *   - retention_days : durée de conservation maximale ;
 *   - responsible    : responsable du traitement ;
 *   - pii_columns    : colonnes PII (colonne => type : email|phone|name|generic) ;
 *   - anonymization  : mode de remplacement déterministe (rejouable).
 */

return [
    'version' => 1,

    'registry' => [
        'crm_accounts' => [
            'table' => 'crm_accounts',
            'purpose' => 'Gestion des comptes clients CRM (relations commerciales)',
            'legal_basis' => 'RGPD 6.1.b (exécution contractuelle) / intérêt légitime 6.1.f',
            'retention_days' => 3650,
            'responsible' => 'Client employeur (responsable) — Leopardo (sous-traitant)',
            'pii_columns' => [
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
                'address' => 'generic',
            ],
        ],
        'crm_contacts' => [
            'table' => 'crm_contacts',
            'purpose' => 'Gestion des contacts CRM (personnes de contact)',
            'legal_basis' => 'RGPD 6.1.b / 6.1.f (intérêt légitime)',
            'retention_days' => 3650,
            'responsible' => 'Client employeur (responsable) — Leopardo (sous-traitant)',
            'pii_columns' => [
                'first_name' => 'name',
                'last_name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
            ],
        ],
        'crm_leads' => [
            'table' => 'crm_leads',
            'purpose' => 'Gestion des prospects CRM',
            'legal_basis' => 'RGPD 6.1.a (consentement) / 6.1.f (intérêt légitime)',
            'retention_days' => 1825,
            'responsible' => 'Client employeur (responsable) — Leopardo (sous-traitant)',
            'pii_columns' => [
                'first_name' => 'name',
                'last_name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
            ],
        ],
        'crm_opportunities' => [
            'table' => 'crm_opportunities',
            'purpose' => 'Suivi des opportunités commerciales',
            'legal_basis' => 'RGPD 6.1.b',
            'retention_days' => 3650,
            'responsible' => 'Client employeur (responsable) — Leopardo (sous-traitant)',
            'pii_columns' => [
                'name' => 'name',
            ],
        ],
        'crm_activities' => [
            'table' => 'crm_activities',
            'purpose' => 'Historique d\'activités CRM (notes, comptes-rendus)',
            'legal_basis' => 'RGPD 6.1.f',
            'retention_days' => 3650,
            'responsible' => 'Client employeur (responsable) — Leopardo (sous-traitant)',
            'pii_columns' => [
                'description' => 'generic',
                'note' => 'generic',
            ],
        ],
        'crm_tasks' => [
            'table' => 'crm_tasks',
            'purpose' => 'Tâches et relances CRM',
            'legal_basis' => 'RGPD 6.1.b',
            'retention_days' => 3650,
            'responsible' => 'Client employeur (responsable) — Leopardo (sous-traitant)',
            'pii_columns' => [
                'description' => 'generic',
            ],
        ],
        'crm_consents' => [
            'table' => 'crm_consents',
            'purpose' => 'Gestion des consentements de communication (par canal et finalité)',
            'legal_basis' => 'RGPD 6.1.a (consentement) — retrait possible à tout moment',
            'retention_days' => 3650,
            'responsible' => 'Client employeur (responsable) — Leopardo (sous-traitant)',
            // Pas d'anonymisation sur place : les lignes de consentement sont
            // des références (contact_id, canal, finalité), pas des données
            // PII en clair — elles suivent la purge/rétention, pas l'écrasement.
            'pii_columns' => [],
        ],
        'crm_external_events' => [
            'table' => 'crm_external_events',
            'purpose' => 'Événements entrants des providers (webhooks)',
            'legal_basis' => 'RGPD 6.1.f',
            'retention_days' => 730,
            'responsible' => 'Client employeur (responsable) — Leopardo (sous-traitant)',
            'pii_columns' => [
                'payload' => 'generic',
            ],
        ],
        'crm_conversations' => [
            'table' => 'crm_conversations',
            'purpose' => 'Conversations CRM (email, WhatsApp, SMS)',
            'legal_basis' => 'RGPD 6.1.b / 6.1.a',
            'retention_days' => 1825,
            'responsible' => 'Client employeur (responsable) — Leopardo (sous-traitant)',
            'pii_columns' => [
                'content' => 'generic',
                'from_address' => 'email',
                'to_address' => 'email',
            ],
        ],
    ],
];
