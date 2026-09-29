<?php

declare(strict_types=1);

namespace App\Core\Solutions\Contracts;

use App\Core\Solutions\Enums\SolutionIndustry;

/**
 * Manifest d'une solution sectorielle (FuelStation, EduManager, …).
 *
 * Une solution verticale est un pack de configuration activable par tenant
 * (feature flag) qui déclare ses modules requis/optionnels, sa maturité,
 * ses permissions et ses données sensibles — elle ne remplace pas les
 * modules transversaux (spec PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md).
 *
 * @see docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md
 */
interface SolutionManifest
{
    /** Code de solution unique (allowlist) — ex. `fuel_station`. */
    public function code(): string;

    /** Nom lisible de la solution. */
    public function name(): string;

    /** Niveau de maturité : `pilot` | `production` | `placeholder`. */
    public function maturity(): string;

    /** Industrie de la solution (registre fermé versionné) — BOS-013 (#8200).
     *
     * Sert au référentiel de solutions et à l'onboarding intelligent (Léa) ;
     * distincte du `code()` : deux solutions peuvent partager une industrie.
     */
    public function industry(): SolutionIndustry;

    /** Description courte (affichage onboarding). */
    public function description(): string;

    /** Modules transversaux requis — tous doivent être actifs au tenant.
     *
     * @return list<string>
     */
    public function requiredModules(): array;

    /** Modules optionnels (jamais bloquants).
     *
     * @return list<string>
     */
    public function optionalModules(): array;

    /** Données sensibles manipulées par la solution (RGPD).
     *
     * @return list<string>
     */
    public function sensitiveData(): array;

    /** Permissions / rôles spécifiques installés par la solution.
     *
     * @return array<string, string>
     */
    public function permissions(): array;
}
