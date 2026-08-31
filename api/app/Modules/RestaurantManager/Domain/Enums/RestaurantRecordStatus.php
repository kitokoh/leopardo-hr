<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Statut générique d'une entité restaurant (actif/désactivé) (BC-25, RESTO-214, issue #6179).
 */
enum RestaurantRecordStatus: string
{
    case ACTIVE = 'active';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::DISABLED => 'Désactivé',
        };
    }
}
