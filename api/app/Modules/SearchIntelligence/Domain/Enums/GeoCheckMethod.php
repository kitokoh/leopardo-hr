<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-5 (#8516) — niveau de preuve d'un check de présence GEO.
 *
 * Trois niveaux, du plus faible au plus fort, et ils ne sont PAS
 * interchangeables :
 *
 * | méthode             | ce que ça veut dire                                        |
 * |---------------------|------------------------------------------------------------|
 * | `spot_test`         | un essai ponctuel, à un instant T, sur une requête         |
 * | `observed_data`     | une observation réitérée, avec trace textuelle             |
 * | `measured_indicator`| une mesure instrumentée, avec série et source vérifiable    |
 *
 * Le risque que cette énumération neutralise : présenter un test ponctuel
 * comme un indicateur mesuré. C'est la façon la plus courante de mentir avec
 * des données de visibilité — « notre marque apparaît dans 40 % des réponses »
 * alors qu'on a posé la question une fois. Le niveau est donc OBLIGATOIRE, il
 * voyage dans la réponse API (`method_badge`), et la base refuse toute valeur
 * hors de ces trois-là.
 */
enum GeoCheckMethod: string
{
    case SpotTest = 'spot_test';
    case ObservedData = 'observed_data';
    case MeasuredIndicator = 'measured_indicator';

    /** Rang de solidité : 0 = le plus faible. Sert au tri et aux comparaisons. */
    public function strength(): int
    {
        return match ($this) {
            self::SpotTest => 0,
            self::ObservedData => 1,
            self::MeasuredIndicator => 2,
        };
    }

    /**
     * Ce niveau autorise-t-il une conclusion chiffrée publique ? Un test
     * ponctuel ne le permet pas : il décrit une observation, pas une tendance.
     */
    public function supportsQuantifiedClaim(): bool
    {
        return $this !== self::SpotTest;
    }

    /**
     * Libellé affiché dans l'interface. Formulé pour qu'un lecteur non
     * technique comprenne immédiatement la solidité de la preuve.
     */
    public function badge(): string
    {
        return match ($this) {
            self::SpotTest => 'Test ponctuel',
            self::ObservedData => 'Observation réitérée',
            self::MeasuredIndicator => 'Indicateur mesuré',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SpotTest => 'Un essai unique, à un instant donné. Ne démontre pas de tendance.',
            self::ObservedData => 'Plusieurs observations concordantes, avec trace textuelle datée.',
            self::MeasuredIndicator => 'Mesure instrumentée et répétée, avec série et source vérifiable.',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
