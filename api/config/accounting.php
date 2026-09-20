<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rétention des documents comptables (#5273)
    |--------------------------------------------------------------------------
    |
    | Durée de conservation légale des documents comptables finalisés
    | (factures, avoirs, reçus...) avant purge.
    |
    | Issue #7929 : la durée est résolue PAR PAYS du tenant — voir
    | AccountingRetentionService::RETENTION_MONTHS_BY_COUNTRY (OHADA 120,
    | FR 120, TR 120, CA 72 — sources dans
    | docs/security/ACCOUNTING_RETENTION.md). `retention_months` (env
    | ACCOUNTING_RETENTION_MONTHS) est le défaut conservateur (120 mois =
    | 10 ans) pour un pays sans durée dédiée ; l'override opérateur ponctuel
    | est l'option `--older-than` de accounting:purge-expired.
    */
    'retention_months' => (int) env('ACCOUNTING_RETENTION_MONTHS', 120),

    /*
    |--------------------------------------------------------------------------
    | Audit des actions comptables
    |--------------------------------------------------------------------------
    |
    | Préfixe des événements d'audit du module (DataAccessAuditLogger →
    | audit_logs.metadata.resource). Utilisé par GET /accounting/audit-logs.
    */
    'audit_resource_prefix' => 'accounting.',

];
