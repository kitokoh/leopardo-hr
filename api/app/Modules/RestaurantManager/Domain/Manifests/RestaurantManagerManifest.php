<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Manifests;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;

/**
 * Manifest de la verticale RestaurantManager (RESTO-106, issue #6163).
 *
 * Identité : code `restaurantmanager` (feature flag
 * companies.features.restaurantmanager), industrie `restaurant`, maturité
 * `pilot`, modules requis rh/documents/notifications/crm, données sensibles
 * PII clients + paiements, permissions restaurant.* (personas de la spec §1.2).
 *
 * BOS-014 (#8201) — convergence sur le contrat CORE v2 (`industry()`,
 * `description()`, permissions en map `code => libellé`) + enregistrement au
 * `SolutionCatalogue` (clé `restaurantmanager`) : fin du contrat local non
 * conforme et non enregistré (anti-pattern #7220-bis vivant). L'ancien
 * contrat local `App\Modules\RestaurantManager\Domain\Contracts\SolutionManifest`
 * est conservé DEPRECATED en attendant la validation des activations
 * (rollback note de l'issue) — il n'est plus implémenté ni bindé.
 *
 * UN SEUL MANIFEST RESTAURANT — clarification des rôles (BOS-014) : la
 * verticale restaurant n'a qu'UNE identité d'industrie (`restaurant`), mais
 * deux codes de solution distincts et complémentaires :
 *  - `restaurant` (Modules/Restaurant) = DESCRIPTEUR du pack d'onboarding
 *    (survey, SECTOR_SOLUTIONS) — l'activation par ce code CASCADE ici :
 *    elle pose le flag opérationnel `restaurantmanager` et amorce le
 *    référentiel via `ActivateRestaurantManagerAction` (listener du
 *    provider) ;
 *  - `restaurantmanager` (ce manifest) = verticale OPÉRATIONNELLE (POS &
 *    caisse, commandes, réservations, stock/COGS, livraison, fidélité),
 *    activable aussi directement (console plateforme).
 *
 * @see \App\Core\Solutions\SolutionCatalogue
 * @see \App\Modules\RestaurantManager\Providers\RestaurantManagerServiceProvider
 * @see \App\Modules\Restaurant\Domain\Solution\RestaurantManifest
 */
final class RestaurantManagerManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'restaurantmanager';
    }

    public function name(): string
    {
        return 'RestaurantManager';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function industry(): SolutionIndustry
    {
        return SolutionIndustry::Restaurant;
    }

    public function description(): string
    {
        return 'Verticale restaurant opérationnelle : POS & caisse, commandes salle/cuisine, réservations, stock & COGS, livraison et fidélité.';
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return ['rh', 'documents', 'notifications', 'crm'];
    }

    /**
     * @return list<string>
     */
    public function optionalModules(): array
    {
        return ['accounting', 'marketing'];
    }

    /**
     * @return list<string>
     */
    public function sensitiveData(): array
    {
        return ['customer_pii', 'payments'];
    }

    /**
     * Permissions / rôles spécifiques installés par la solution (personas de
     * la spec SOLUTION_RESTAURANT_MANAGER.md §1.2, matrice
     * `RestaurantPermissions`) — installés à l'activation via le socle de
     * grants (BOS-013).
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [
            'restaurant.manage' => 'Configuration : établissements, catalogue, tarifs, rapports, clôtures',
            'restaurant.manager' => 'Pilotage opérationnel de la salle : zones, tables, menus, horaires',
            'restaurant.server' => 'Opérations serveur / caisse : lecture et prise de commande',
            'restaurant.kitchen' => 'File de commandes en cuisine (écran)',
            'restaurant.rider' => 'Tournées de livraison',
            'restaurant.reports' => 'Lecture / rapports (croise tous les rôles opérationnels)',
        ];
    }
}
