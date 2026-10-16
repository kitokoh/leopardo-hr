<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — provenance d'un indicateur de visibilite (#8531, brief §H).
 *
 * Regle du brief : « Les indicateurs doivent etre mesures ou clairement
 * identifies comme estimations. » Trois etats, aucun autre :
 *
 *   - `Measured`    : valeur issue d'une source de mesure reelle (Search
 *                     Console, analytics du site, API publicitaire).
 *   - `Estimated`   : valeur approchee, explicitement presentee comme telle.
 *   - `Unavailable` : aucune donnee — l'interface affiche « indisponible ».
 *
 * Aucune visibilite n'est jamais GARANTIE : ce n'est pas un etat de donnee,
 * c'est une promesse que le produit ne fait pas.
 */
enum MarketingVisibilityMetricsSource: string
{
    case Measured = 'measured';
    case Estimated = 'estimated';
    case Unavailable = 'unavailable';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Measured => 'Mesure',
            self::Estimated => 'Estimation',
            self::Unavailable => 'Indisponible',
        };
    }

    /** La valeur est-elle presentable comme un fait mesure ? */
    public function isMeasured(): bool
    {
        return $this === self::Measured;
    }
}
