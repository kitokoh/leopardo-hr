<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * État courant d'un consentement CRM — Issue #5722.
 *
 * - `granted` : consentement explicite actif ;
 * - `denied` : refus explicite (l'utilisateur a dit non) ;
 * - `withdrawn` : retrait d'un consentement précédemment accordé.
 */
enum ConsentStatus: string
{
    case Granted = 'granted';
    case Denied = 'denied';
    case Withdrawn = 'withdrawn';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
