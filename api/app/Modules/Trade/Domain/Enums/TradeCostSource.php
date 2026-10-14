<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Provenance d'un poste de cout — BC-36 TRADE, TRADE-007/#8482.
 *
 * C'est le pilier de l'HONNETETE du calcul du cout rendu : l'utilisateur voit
 * pour chaque poste s'il est confirme, estime, saisi a la main, ou simplement
 * INDISPONIBLE. Une ligne `Unavailable` est listee mais EXCLUE du total — le
 * systeme ne comble jamais un trou par une valeur inventee, et l'interface
 * affiche la couverture reelle de l'estimation (exigence mission §« Chaque
 * poste marquable comme confirme / estime / saisi / indisponible »).
 */
enum TradeCostSource: string
{
    case Confirmed = 'confirmed';
    case Estimated = 'estimated';
    case UserInput = 'user_input';
    case Unavailable = 'unavailable';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $source): string => $source->value, self::cases());
    }

    /**
     * Un poste indisponible n'entre jamais dans le total.
     */
    public function countsTowardTotal(): bool
    {
        return $this !== self::Unavailable;
    }
}
