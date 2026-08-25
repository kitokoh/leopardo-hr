<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rétention des documents comptables (#5273)
    |--------------------------------------------------------------------------
    |
    | Durée de conservation légale des documents comptables finalisés
    | (factures, avoirs, reçus...) avant purge. Défaut 120 mois = 10 ans
    | (code de commerce FR art. L123-22 / droit algérien — voir
    | docs/security/ACCOUNTING_RETENTION.md).
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
