<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Catégorie d'âge d'un passager (TRAVEL-209, issue #6022).
 */
enum AgeCategory: string
{
    case INFANT = 'infant';
    case CHILD = 'child';
    case ADULT = 'adult';

    public function label(): string
    {
        return match ($this) {
            self::INFANT => 'Bébé',
            self::CHILD => 'Enfant',
            self::ADULT => 'Adulte',
        };
    }
}
