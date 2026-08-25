<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand un relevé bancaire avec la même clé d'import
 * (company_id, statement_period, import_reference) existe déjà — le
 * ré-import est refusé (409), aucune ligne n'est insérée.
 */
class BankStatementDuplicateImportException extends DomainException
{
    public function __construct(string $statementPeriod, string $importReference)
    {
        parent::__construct(
            sprintf(
                'BANK_STATEMENT_DUPLICATE_IMPORT: statement %s / %s already imported',
                $statementPeriod,
                $importReference
            ),
            409,
            'BANK_STATEMENT_DUPLICATE_IMPORT'
        );
    }
}
