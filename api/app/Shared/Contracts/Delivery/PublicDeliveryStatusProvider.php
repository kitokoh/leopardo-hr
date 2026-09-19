<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Delivery;

/**
 * Port PUBLIC en LECTURE SEULE du module Delivery (BC-26) — issue #7811.
 *
 * Permet à un module source (ex. Retail BC-17, page de suivi publique
 * marketplace) de lire l'état d'une livraison créée pour SA commande
 * (source + source_reference) SANS import croisé ni requête directe sur les
 * tables Delivery (règle d'isolation #5584, pattern documenté
 * `App\Shared\Contracts\Crm\EmailContactDirectory`).
 *
 * Implémenté par `Delivery\Infrastructure\Services\EloquentPublicDeliveryStatusProvider`
 * et bindé par `DeliveryServiceProvider`. Surface volontairement MINIMALE et
 * fail-closed : statut + horodatages publics uniquement, `null` si aucune
 * livraison n'existe.
 */
interface PublicDeliveryStatusProvider
{
    /**
     * État public de la livraison du tenant pour (source, source_reference),
     * ou null si aucune livraison n'existe pour cette commande source.
     */
    public function findBySourceReference(
        string $companyId,
        string $source,
        string $sourceReference,
    ): ?PublicDeliveryStatus;
}
