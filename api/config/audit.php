<?php

declare(strict_types=1);

/**
 * S-2 (#1662) — Journalisation des accès aux données sensibles.
 *
 * `DataAccessAuditLogger` trace les lectures de ressources sensibles
 * (bulletins, exports, journal de paie, certificat, fin de contrat,
 * exports bancaires) dans `audit_logs` (metadata.category =
 * 'hr_data_access').
 *
 * L'échantillonnage est configurable pour borner le volume :
 *   - 1.0 (défaut) : chaque accès est tracé ;
 *   - 0.0 : désactivé ;
 *   - 0.1 : ~10 % des accès (déterministe par acteur + action, un acteur
 *     donné est tracé de façon stable — pas d'aléa par requête).
 *
 * Variable d'environnement : DATA_ACCESS_AUDIT_SAMPLE_RATE
 */

return [
    'data_access' => [
        'sample_rate' => (float) env('DATA_ACCESS_AUDIT_SAMPLE_RATE', 1.0),
    ],
];
