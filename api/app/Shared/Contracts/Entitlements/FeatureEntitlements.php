<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Entitlements;

/**
 * Lecture des entitlements d'un tenant, exposée aux modules qui ne possèdent
 * pas le plan produit (BC PAGES, #8726).
 *
 * ## Pourquoi un contrat partagé
 *
 * `App\Modules\Billing\Domain\Services\EntitlementGuard` est le propriétaire
 * légitime de cette règle, mais un module ne peut pas importer un autre module
 * (`ARCHITECTURE.md` §2, garde #5584). Avant ce contrat, `PageCustomDomainService`
 * importait directement `Billing` — ce qui rendait le module Pages **solidaire
 * d'une implémentation** qu'il n'a pas le droit de connaître, et empêchait de
 * tester Pages sans charger Billing.
 *
 * L'inversion de dépendance passe par `Shared` : Pages dépend d'une interface,
 * Billing en fournit l'implémentation, et le câblage vit dans le provider du
 * module. Aucune des deux parties ne connaît l'autre.
 *
 * ## Pourquoi `?int` et non `int`
 *
 * Un quota absent n'est pas un quota nul. `featureLimit()` renvoie `null`
 * quand le plan ne borne pas la fonctionnalité ; le convertir en `0` fermerait
 * la porte à tout le monde, et en `PHP_INT_MAX` la laisserait ouverte sans
 * qu'on sache pourquoi. L'appelant décide, en connaissance de cause.
 */
interface FeatureEntitlements
{
    /**
     * La fonctionnalité est-elle activée pour ce tenant ?
     */
    public function isFeatureEnabled(string $companyId, string $featureKey): bool;

    /**
     * Plafond de la fonctionnalité pour ce tenant, ou `null` si le plan
     * ne la borne pas.
     */
    public function featureLimit(string $companyId, string $featureKey): ?int;
}
