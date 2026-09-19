<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Type d'etablissement expose sur le profil public d'une branche
 * (RESTO-901, issue #7746).
 */
enum RestaurantEstablishmentType: string
{
    case RESTAURANT = 'restaurant';
    case FAST_FOOD = 'fast_food';
    case PIZZERIA = 'pizzeria';
    case BRASSERIE = 'brasserie';
    case CAFE = 'cafe';
    case PATISSERIE = 'patisserie';
    case TRAITEUR = 'traiteur';
    case AUTRE = 'autre';

    /**
     * Valeurs autorisees (validation FormRequest / filtre annuaire public).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::RESTAURANT => 'Restaurant',
            self::FAST_FOOD => 'Fast-food',
            self::PIZZERIA => 'Pizzeria',
            self::BRASSERIE => 'Brasserie',
            self::CAFE => 'Café',
            self::PATISSERIE => 'Pâtisserie',
            self::TRAITEUR => 'Traiteur',
            self::AUTRE => 'Autre',
        };
    }
}
