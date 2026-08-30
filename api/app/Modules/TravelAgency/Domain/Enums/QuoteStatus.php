<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * TRAVEL-803 (#6094) — Cycle de vie d'un devis de groupe.
 */
enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case BOOKED = 'booked';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
}
