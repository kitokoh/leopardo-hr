<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Enums;

/**
 * Statut d'une cagnotte (verticale FUNDRAISING — spec
 * docs/specifications/SOLUTION_FUNDRAISING.md §3.1).
 *
 * Transitions : draft→active (publish), active→paused (pause),
 * paused→active (resume), active→completed (objectif atteint, automatique),
 * active|paused|completed→closed (close), draft|active→cancelled.
 */
enum FundraiserStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::ACTIVE => 'En collecte',
            self::PAUSED => 'En pause',
            self::COMPLETED => 'Objectif atteint',
            self::CLOSED => 'Clôturée',
            self::CANCELLED => 'Annulée',
        };
    }

    /** La cagnotte accepte-t-elle des contributions ? */
    public function acceptsContributions(): bool
    {
        return $this === self::ACTIVE;
    }

    /** La cagnotte est-elle visible publiquement ? (closed reste lisible) */
    public function isPubliclyVisible(): bool
    {
        return in_array($this, [self::ACTIVE, self::COMPLETED, self::CLOSED], true);
    }

    /** Un reversement peut-il être demandé ? */
    public function allowsPayout(): bool
    {
        return in_array($this, [self::ACTIVE, self::COMPLETED, self::CLOSED], true);
    }
}
