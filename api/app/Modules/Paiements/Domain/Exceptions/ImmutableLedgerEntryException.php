<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Exceptions;

use RuntimeException;

/**
 * Tentative de modification ou suppression d'une ecriture de ledger (BC-38, #8681).
 *
 * Le ledger est un journal : une erreur se corrige par une ecriture de
 * contre-passation, jamais par un update ou un delete.
 */
class ImmutableLedgerEntryException extends RuntimeException
{
    public static function onUpdate(int $id): self
    {
        return new self(sprintf(
            'Ecriture de ledger #%d immuable : aucune modification autorisee (utiliser une contre-passation).',
            $id,
        ));
    }

    public static function onDelete(int $id): self
    {
        return new self(sprintf(
            'Ecriture de ledger #%d immuable : aucune suppression autorisee (utiliser une contre-passation).',
            $id,
        ));
    }
}
