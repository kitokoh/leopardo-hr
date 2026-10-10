<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Enums;

/**
 * Statut d'une contribution (spec SOLUTION_FUNDRAISING.md §3.2).
 * Seul COMPLETED crédite le compteur de la cagnotte (une seule fois).
 */
enum ContributionStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::COMPLETED => 'Confirmée',
            self::FAILED => 'Échouée',
            self::REFUNDED => 'Remboursée',
        };
    }
}
