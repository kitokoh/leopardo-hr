<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Exceptions;

use RuntimeException;

/**
 * Un mouvement de ledger n'est pas equilibre (BC-38, #8681).
 *
 * Levee avant toute ecriture : un groupe desequilibre ne doit jamais atteindre
 * la base, meme partiellement.
 */
class UnbalancedLedgerEntryException extends RuntimeException
{
    public static function forGroup(string $transferGroupId, int $debits, int $credits): self
    {
        return new self(sprintf(
            'Mouvement desequilibre (groupe %s) : debits=%d, credits=%d.',
            $transferGroupId,
            $debits,
            $credits,
        ));
    }

    public static function forAmount(int $amount): self
    {
        return new self(sprintf('Montant de mouvement invalide : %d (doit etre strictement positif).', $amount));
    }
}
