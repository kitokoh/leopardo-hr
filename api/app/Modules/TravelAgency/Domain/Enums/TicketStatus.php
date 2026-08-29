<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Statut d'un billet nominatif (TRAVEL-210, issue #6023).
 */
enum TicketStatus: string
{
    case ISSUED = 'issued';
    case CHECKED_IN = 'checked_in';
    case VOID = 'void';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Émis',
            self::CHECKED_IN => 'Enregistré',
            self::VOID => 'Annulé',
        };
    }
}
