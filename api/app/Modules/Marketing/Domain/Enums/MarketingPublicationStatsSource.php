<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — provenance des statistiques d'une publication (#8530).
 *
 * Regle non negociable du brief (§A, §H) : un indicateur est soit MESURE,
 * soit declare indisponible. Il n'est jamais estime puis presente comme un
 * fait.
 *
 *   - `Platform`    : valeurs renvoyees par l'API de la plateforme (ou de
 *                     l'agregateur) lors d'un appel reel.
 *   - `Manual`      : valeurs saisies par un humain (releve manuel depuis
 *                     l'interface de la plateforme).
 *   - `Unavailable` : aucune donnee obtenue — l'interface doit afficher
 *                     « indisponible » et jamais un chiffre invente.
 */
enum MarketingPublicationStatsSource: string
{
    case Platform = 'platform';
    case Manual = 'manual';
    case Unavailable = 'unavailable';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Mesuree par la plateforme',
            self::Manual => 'Relevee manuellement',
            self::Unavailable => 'Indisponible',
        };
    }

    /** La donnee est-elle reellement mesuree (et non pas seulement declaree) ? */
    public function isMeasured(): bool
    {
        return $this !== self::Unavailable;
    }
}
