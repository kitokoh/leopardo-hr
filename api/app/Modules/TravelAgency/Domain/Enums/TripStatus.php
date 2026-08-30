<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Statut d'un trajet daté (TRAVEL-207, issue #6020).
 *
 * Cycle de vie : `draft` (préparation) → `scheduled` (dates confirmées) →
 * `published` (visible à la vente) → `cancelled` (annulé, terminal).
 */
enum TripStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PUBLISHED = 'published';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SCHEDULED => 'Planifié',
            self::PUBLISHED => 'Publié',
            self::CANCELLED => 'Annulé',
        };
    }
}
