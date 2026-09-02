<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Finalité d'un traitement de communication — Issue #5722 (consentements).
 *
 * - `marketing` : consentement explicite requis avant tout envoi ;
 * - `transactional` : lié à une relation/service existant (facture, relance,
 *   modification de contrat…) — aucun consentement marketing requis, mais les
 *   suppressions (bounce/complaint/désabonnement) restent appliquées.
 */
enum ConsentPurpose: string
{
    case Marketing = 'marketing';
    case Transactional = 'transactional';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $purpose): string => $purpose->value, self::cases());
    }
}
