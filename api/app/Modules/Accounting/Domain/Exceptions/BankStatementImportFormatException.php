<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand l'en-tête du CSV importé ne correspond pas au mapping configuré
 * (colonnes requises absentes) — aucune ligne n'est insérée (422).
 */
class BankStatementImportFormatException extends DomainException
{
    public function __construct(string $detail)
    {
        parent::__construct(
            sprintf('BANK_STATEMENT_IMPORT_FORMAT: %s', $detail),
            422,
            'BANK_STATEMENT_IMPORT_FORMAT'
        );
    }
}
