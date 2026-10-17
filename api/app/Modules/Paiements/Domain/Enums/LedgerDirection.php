<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Enums;

/**
 * Sens d'une ecriture de ledger (BC-38, #8681).
 */
enum LedgerDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }
}
