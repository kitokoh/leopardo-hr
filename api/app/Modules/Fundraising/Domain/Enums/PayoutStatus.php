<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Enums;

/**
 * Statut d'un reversement au bénéficiaire (spec §3.3) :
 * requested → processing → paid | failed ; requested → cancelled.
 */
enum PayoutStatus: string
{
    case REQUESTED = 'requested';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'Demandé',
            self::PROCESSING => 'En traitement',
            self::PAID => 'Versé',
            self::FAILED => 'Échoué',
            self::CANCELLED => 'Annulé',
        };
    }

    /** Occupe-t-il du solde disponible ? (règle §3.3) */
    public function locksBalance(): bool
    {
        return in_array($this, [self::REQUESTED, self::PROCESSING, self::PAID], true);
    }
}
