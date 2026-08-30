<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Contracts\TravelCustomerContactResolver;

/**
 * TRAVEL-416 (#6068) — résolveur de contact par défaut (aucune donnée).
 *
 * Le BC CRM client fournit l'implémentation réelle du contrat
 * TravelCustomerContactResolver (jamais d'import direct depuis la
 * verticale). En attendant l'intégration, ce résolveur vide garantit que le
 * module fonctionne sans couplage : les notifications sont émises avec la
 * référence de contact, la résolution des coordonnées reste un contrat.
 */
final class NullTravelCustomerContactResolver implements TravelCustomerContactResolver
{
    public function resolve(string $companyId, string $contactReference): ?array
    {
        return null;
    }
}
