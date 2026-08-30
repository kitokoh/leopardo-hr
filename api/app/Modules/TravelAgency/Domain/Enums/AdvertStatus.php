<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Statut d'une annonce payante (TRAVEL-907/908, issues #6110/#6111).
 *
 * Cycle de vie : draft → submitted → paid → validated | rejected →
 * expired | archived. Une annonce n'est visible qu'une fois payée ET
 * validée, et tant que `expires_at` n'est pas dépassé.
 */
enum AdvertStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case PAID = 'paid';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SUBMITTED => 'Soumise',
            self::PAID => 'Payée',
            self::VALIDATED => 'Validée',
            self::REJECTED => 'Rejetée',
            self::EXPIRED => 'Expirée',
            self::ARCHIVED => 'Archivée',
        };
    }
}
