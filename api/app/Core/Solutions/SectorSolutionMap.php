<?php

declare(strict_types=1);

namespace App\Core\Solutions;

/**
 * #8454 — carte canonique secteur déclaré ↔ code de verticale du catalogue.
 *
 * Historiquement privée au `SetupInterviewPlanner` (Modules/Onboarding), elle
 * est promue dans Core depuis que Billing (provisioning des inscriptions
 * vitrine, `?solution=`) pré-ensemence la réponse `sector` de l'entretien de
 * préparation : la garde d'isolation (#5584) interdit tout nouvel import
 * croisé Modules → Modules, Core est la direction autorisée.
 *
 * `services`/`other` n'ont pas de verticale : outils horizontaux uniquement.
 */
final class SectorSolutionMap
{
    /**
     * Secteur déclaré → code de solution verticale (`SolutionCatalogue`).
     *
     * @var array<string, string>
     */
    private const SECTOR_SOLUTIONS = [
        'restaurant' => 'restaurant',
        'fuel_station' => 'fuel_station',
        'education' => 'edumanager',
        'travel' => 'travelagency',
        'commerce' => 'retail',
        // FUND-080 (#8400) : association / ONG / mutuelle → verticale
        // « Cagnottes solidaires » (collecte de fonds par lien public).
        'association' => 'fundraising',
    ];

    /**
     * Code de solution verticale pour un secteur déclaré, `null` si le
     * secteur n'a pas de verticale (outils horizontaux uniquement).
     */
    public static function solutionForSector(string $sector): ?string
    {
        return self::SECTOR_SOLUTIONS[$sector] ?? null;
    }

    /**
     * Carte inverse : code de verticale du catalogue → réponse `sector` de
     * l'entretien. Sert à pré-ensemencer la réponse quand le métier est connu
     * AVANT l'entretien (inscription depuis une page pack de la vitrine) :
     * l'entretien reprend à la première question NON répondue, la question
     * secteur n'est donc plus posée. `null` pour une verticale sans
     * équivalent secteur (ou un code inconnu) : rien à pré-ensemencer.
     */
    public static function sectorForSolution(?string $solutionCode): ?string
    {
        if ($solutionCode === null || $solutionCode === '') {
            return null;
        }

        /** @var array<string, string> $flipped */
        $flipped = array_flip(self::SECTOR_SOLUTIONS);

        return $flipped[strtolower(trim($solutionCode))] ?? null;
    }
}
