<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Enums;

/**
 * Verticale métier d'une Brand (BC-41, #9578).
 *
 * ## Registre FERMÉ et VERSIONNÉ
 *
 * Extension par nouvelle case uniquement — jamais de renommage, jamais de
 * retrait. La valeur est persistée en base et sert de clé de résolution du
 * profil de surface (`SurfaceProfileProvider::vertical()`). Renommer une case
 * rendrait orphelines toutes les Brands existantes de cette verticale, et une
 * Brand sans profil n'a pas de surface publique.
 *
 * ## Pourquoi une verticale et pas une industrie
 *
 * `SolutionIndustry` (BOS-013) décrit le SECTEUR d'une solution commerciale
 * (onboarding, catalogue). Ici on décrit la FORME de la surface publique : ce
 * qu'un visiteur peut y faire. Deux solutions peuvent partager une industrie
 * mais pas la même surface — un restaurant à emporter et un restaurant gastronomique
 * n'ont pas le même parcours public. Les deux registres sont volontairement
 * distincts et ne se déduisent pas l'un de l'autre.
 *
 * ## `generic`
 *
 * Toute Brand dont la verticale n'a pas de profil installé retombe sur
 * `generic` : hero, atouts, galerie, contact, pied de page. C'est ce qui rend
 * l'ajout d'une verticale non bloquant — une Brand peut être publiée avant que
 * sa verticale ne livre son profil.
 */
enum BrandVertical: string
{
    /** Repli : aucune spécialisation, socle `core.*` uniquement. */
    case Generic = 'generic';

    case Restaurant = 'restaurant';

    case Retail = 'retail';

    case Pharmacy = 'pharmacy';

    case Travel = 'travel';

    case Vtc = 'vtc';

    case Fuel = 'fuel';

    case Hospitality = 'hospitality';

    case Health = 'health';

    case Trade = 'trade';

    /** Billetterie / événementiel (billets). */
    case Ticketing = 'ticketing';

    /**
     * Toutes les verticales déclarables, `generic` inclus.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Verticales qui ont (ou auront) un profil de surface dédié.
     *
     * `generic` en est exclu : c'est le repli, pas une spécialisation. Un
     * provider qui déclarerait `generic` écraserait le socle pour tout le
     * monde — le registre le refuse.
     *
     * @return list<string>
     */
    public static function specialised(): array
    {
        return array_values(array_filter(
            self::values(),
            static fn (string $value): bool => $value !== self::Generic->value,
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::Generic => 'Générique',
            self::Restaurant => 'Restauration',
            self::Retail => 'Commerce de détail',
            self::Pharmacy => 'Pharmacie',
            self::Travel => 'Voyage',
            self::Vtc => 'VTC / mobilité',
            self::Fuel => 'Station-service',
            self::Hospitality => 'Hôtellerie',
            self::Health => 'Santé',
            self::Trade => 'Commerce B2B',
            self::Ticketing => 'Billetterie',
        };
    }
}
