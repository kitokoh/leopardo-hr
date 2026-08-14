<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand une transition du workflow de validation des taux légaux
 * (issue #1813) est invalide : mauvais statut source, motif de rejet
 * manquant, table inconnue, etc. Rendu JSON standard (422).
 */
class TaxRateValidationException extends DomainException
{
    public function __construct(string $message, string $errorCode = 'TAX_RATE_VALIDATION_ERROR')
    {
        parent::__construct($message, 422, $errorCode);
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'TAX_RATE_VALIDATION_ERROR';
    }
}
