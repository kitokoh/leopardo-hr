<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Manifests;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;

/**
 * Manifest de la solution « Cagnottes solidaires » (verticale FUNDRAISING,
 * FUND-080 — spec docs/specifications/SOLUTION_FUNDRAISING.md).
 *
 * Déclare l'identité, les dépendances modulaires, les données sensibles et
 * les permissions de la verticale de collecte de fonds (lien public de
 * collecte, contributions carte + mobile money, reversements au
 * bénéficiaire). Sans cet enregistrement au `SolutionCatalogue`,
 * `SolutionActivator` — fail-closed — ne pouvait pas activer la verticale
 * au provisioning (onboarding) : seul l'admin plateforme pouvait poser le
 * flag manuellement.
 *
 * @see \App\Core\Solutions\SolutionCatalogue
 * @see \App\Modules\Fundraising\Providers\FundraisingServiceProvider
 */
final class FundraisingManifest implements SolutionManifest
{
    public const CODE = 'fundraising';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'Cagnottes solidaires';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function industry(): SolutionIndustry
    {
        return SolutionIndustry::Solidarity;
    }

    public function description(): string
    {
        return 'Collecte de fonds solidaire : cagnotte publiée par lien public court, contributions par carte bancaire et mobile money, reversements au bénéficiaire — pour associations, ONG, mutuelles et entreprises citoyennes.';
    }

    /** @return list<string> */
    public function requiredModules(): array
    {
        // Socle minimal : la verticale est autonome (4 tables tenant + 2
        // annuaires publics propres) ; `notifications` porte les alertes de
        // contributions et de reversements au responsable.
        return ['notifications'];
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        return ['accounting', 'crm', 'marketing'];
    }

    /** @return list<string> */
    public function sensitiveData(): array
    {
        return [
            'identité et situation du bénéficiaire',
            'contributions (montants, coordonnées des contributeurs)',
            'données de paiement tokenisées par les passerelles',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [
            'fundraising.manage' => 'Cagnottes : création, publication, cycle de vie et paramétrage du module',
            'fundraising.contributions' => 'Contributions : suivi, confirmation des dons manuels (espèces/virements)',
            'fundraising.payouts' => 'Reversements : demande et traitement des reversements au bénéficiaire',
        ];
    }
}
