<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Services;

use App\Modules\SearchIntelligence\Domain\Contracts\LeadMetricsProviderInterface;

/**
 * GSI-9 (#8520) — implémentation par défaut : aucune source de leads.
 *
 * Elle existe pour que la vue d'ensemble soit **honnête par construction**.
 * Tant que le BC-11 GROWTH n'a pas exposé ses leads via ce contrat, l'écran
 * affiche `leads: null` + `leads_source: 'null'` et le front rend « non
 * disponible ». Un zéro serait indistinguable de « aucun lead ce mois-ci »,
 * ce qui est exactement le mensonge que l'audit produit reproche aux
 * tableaux de bord de démonstration.
 */
final class NullLeadMetricsProvider implements LeadMetricsProviderInterface
{
    public function leadMetrics(int $days = 30): ?array
    {
        return null;
    }

    public function name(): string
    {
        return 'null';
    }
}
