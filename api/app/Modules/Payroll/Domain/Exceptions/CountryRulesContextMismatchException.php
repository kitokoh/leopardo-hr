<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * MULTI-PAYS (#1868) — levée quand la règle résolue pour un code pays
 * expose un `country_code` différent de celui demandé (incohérence de
 * contexte). Une règle résolue doit TOUJOURS correspondre au pays demandé.
 */
class CountryRulesContextMismatchException extends DomainException
{
    public function __construct(string $requestedCountryCode, string $resolvedCountryCode)
    {
        parent::__construct(
            sprintf(
                'Incohérence de contexte : règles demandées pour « %s » mais résolues pour « %s ».',
                $requestedCountryCode,
                $resolvedCountryCode
            ),
            422,
            'COUNTRY_RULES_CONTEXT_MISMATCH'
        );
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'COUNTRY_RULES_CONTEXT_MISMATCH';
    }
}
