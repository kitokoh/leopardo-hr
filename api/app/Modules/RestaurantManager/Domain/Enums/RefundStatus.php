<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Statut d'un remboursement (BC-25, RESTO-214, issue #6179).
 */
enum RefundStatus: string
{
    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::PROCESSED => 'Traité',
            self::REJECTED => 'Rejeté',
        };
    }
}
