<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * MULTI-PAYS (#1868) — levée quand aucun ensemble de règles pays n'est
 * enregistré pour un code pays demandé. AUCUN fallback silencieux vers une
 * autre juridiction (ex. DZ) : une règle indisponible produit une erreur
 * métier typée, journalisable et exploitable par l'API (422).
 */
class UnsupportedCountryRulesException extends DomainException
{
    public function __construct(string $countryCode)
    {
        parent::__construct(
            sprintf('Aucune règle de paie enregistrée pour le pays « %s ».', $countryCode),
            422,
            'UNSUPPORTED_COUNTRY_RULES'
        );
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'UNSUPPORTED_COUNTRY_RULES';
    }
}
