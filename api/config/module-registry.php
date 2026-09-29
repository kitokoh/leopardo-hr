<?php

declare(strict_types=1);

/**
 * BOS-011 (#8198, ADR-0026, spec #8148) — bascule dual-read du registre
 * unifié modules / features / solutions.
 *
 * La source déclarative unique est {@see \App\Core\Feature\Domain\ModuleRegistry}
 * (PHP versionné, pas de table). Pendant la transition, les listes legacy
 * (`config/feature-flags.flags`, `Company::KNOWN_MODULES`,
 * `Company::HORIZONTAL_TOOL_FEATURES`) restent la référence servie ; une
 * garde CI (tests/Unit/Core/Feature) impose leur parité avec les dérivations
 * du registre.
 *
 * Modes (`MODULE_REGISTRY_MODE`) :
 *   - legacy   : chemins actuels inchangés (défaut — aucune bascule sans
 *                snapshot staging 0 diff, règle ADR-0026) ;
 *   - dual     : résolution calculée par les deux chemins, LEGACY SERVIE,
 *                divergences journalisées sans PII (company_id + clé +
 *                valeur legacy/registry) sur le canal d'audit ;
 *   - registry : le registre unifié est servi (après snapshot de parité
 *                staging, spec §5.3) — activable seulement après BOS-011.
 *
 * Rollback : à toute étape ≤ registry, retour au mode précédent par env —
 * aucune donnée tenant n'est réécrite avant BOS-012.
 */
return [
    'mode' => env('MODULE_REGISTRY_MODE', 'legacy'),

    // Canal de journalisation des divergences du mode dual (JSON, sans PII).
    'divergence_channel' => env('MODULE_REGISTRY_DIVERGENCE_CHANNEL', 'audit'),
];
