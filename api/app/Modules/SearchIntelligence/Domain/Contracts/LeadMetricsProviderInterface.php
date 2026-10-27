<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Contracts;

/**
 * GSI-9 (#8520) — source des métriques de leads et de funnel.
 *
 * La vue d'ensemble « Growth & Search Intelligence » doit afficher des KPIs de
 * leads, or les leads appartiennent au BC-11 GROWTH / au CRM : le module de
 * recherche ne peut pas les lire en important directement leur modèle (règle
 * d'isolation inter-modules, #7959). On passe donc par ce contrat, résolu par
 * l'infrastructure.
 *
 * **Fail-closed** : tant qu'aucune implémentation réelle n'est branchée, le
 * provider par défaut (`NullLeadMetricsProvider`) renvoie `null` et la vue
 * d'ensemble affiche « non disponible » — jamais des zéros qui se liraient
 * comme de mauvais résultats. C'est la même philosophie que
 * `GeocodingProviderInterface` (GEO-08, #8466).
 */
interface LeadMetricsProviderInterface
{
    /**
     * Métriques de leads sur une fenêtre glissante.
     *
     * @return array{
     *     total: int,
     *     qualified: int,
     *     converted: int,
     *     conversion_rate: float|null
     * }|null null si aucune source de leads n'est branchée.
     */
    public function leadMetrics(int $days = 30): ?array;

    /** Identifiant de la source (`null` | `growth` | …) — diagnostic et tests. */
    public function name(): string;
}
