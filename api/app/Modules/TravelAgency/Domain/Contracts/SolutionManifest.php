<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Contracts;

/**
 * Contrat de manifest d'une solution verticale (TRAVEL-106, issue #6011).
 *
 * @deprecated #7220-bis (2026-09-14) — contrat historique local au module,
 * incompatible avec le catalogue core (`App\Core\Solutions\Contracts\SolutionManifest`) :
 * il manquait `description()` et `permissions()` retournait une liste au lieu
 * d'une map `code => libellé`. `TravelAgencyManifest` implémente désormais le
 * contrat core. Ce fichier n'est plus référencé : ne pas l'utiliser pour de
 * nouveaux manifests, et ne pas le supprimer tant que des greffons externes
 * peuvent le résoudre (cf. Delivery/RestaurantManager, mêmes contrats locaux).
 *
 * Implémentation de référence : TravelAgencyManifest.
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

    /** Permissions déclarées par la solution (ex. travel.manage).
     *
     * @return array<int, string>
     */
    public function permissions(): array;
}
