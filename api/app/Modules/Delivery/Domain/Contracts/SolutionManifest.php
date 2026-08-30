<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Contracts;

/**
 * Contrat de manifest d'une solution verticale (DELIVERY-101, issue #6282).
 *
 * Déclare l'identité, la maturité, les dépendances et les permissions d'une
 * solution opérationnelle activable par tenant. Même contrat que les
 * verticales sœurs TravelAgency (TRAVEL-106/#6011) et RestaurantManager
 * (RESTO-106/#6163).
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

    /** Permissions déclarées par la solution (ex. delivery.dispatcher).
     *
     * @return array<int, string>
     */
    public function permissions(): array;
}
