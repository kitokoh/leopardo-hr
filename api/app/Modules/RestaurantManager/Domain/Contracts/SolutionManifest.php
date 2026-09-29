<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

/**
 * Contrat de manifest d'une solution verticale (RESTO-106, issue #6163).
 *
 * @deprecated BOS-014 (#8201) — contrat LOCAL non conforme et jamais
 *             enregistré au catalogue (anti-pattern #7220-bis vivant) :
 *             `RestaurantManagerManifest` implémente désormais le contrat
 *             CORE v2 `App\Core\Solutions\Contracts\SolutionManifest` et est
 *             enregistré au `SolutionCatalogue` (clé `restaurantmanager`).
 *             Ce fichier est conservé temporairement (note de rollback de
 *             l'issue : « les anciens contrats locaux ne sont supprimés
 *             qu'après validation des activations ») ; il n'est plus
 *             implémenté ni bindé. Ne pas réintroduire d'implémentation :
 *             la garde CI `check-solution-manifest-conformance.sh` le refuse.
 *
 * Déclare l'identité, la maturité, les dépendances et les permissions d'une
 * solution opérationnelle activable par tenant. Implémentation de référence :
 * RestaurantManagerManifest (même contrat que la verticale sœur
 * TravelAgency, TRAVEL-106/#6011).
 *
 * Le catalogue central des solutions (PLAT-001, provisioning orchestrator)
 * n'étant pas encore sur main, ce contrat vit DANS le module et sera branché
 * sur le catalogue lorsqu'il sera livré — aucun couplage vers du code absent.
 */
interface SolutionManifest
{
    /** Identifiant machine de la solution (feature flag companies.features.*). */
    public function code(): string;

    /** Nom lisible (i18n). */
    public function name(): string;

    /** Maturité déclarée : pilot | stable. */
    public function maturity(): string;

    /** Modules transversaux requis (codes) pour que la solution fonctionne.
     *
     * @return array<int, string>
     */
    public function requiredModules(): array;

    /** Modules transversaux optionnels (codes).
     *
     * @return array<int, string>
     */
    public function optionalModules(): array;

    /** Catégories de données sensibles traitées (RGPD / audit).
     *
     * @return array<int, string>
     */
    public function sensitiveData(): array;

    /** Permissions déclarées par la solution (ex. restaurant.manage).
     *
     * @return array<int, string>
     */
    public function permissions(): array;
}
