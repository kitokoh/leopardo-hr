<?php

declare(strict_types=1);

namespace App\Modules\Planning\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Issue #5289 — levée quand aucun ensemble de règles légales de congés n'est
 * enregistré pour un code pays demandé. AUCUN fallback silencieux vers une
 * autre juridiction (constitution §III / spec MULTI_PAYS_RULES_ENGINE.md) :
 * une règle indisponible produit une erreur métier typée.
 */
class UnsupportedLeaveCountryException extends DomainException
{
    public function __construct(string $countryCode)
    {
        parent::__construct(
            sprintf('Aucune règle légale de congés enregistrée pour le pays « %s ».', $countryCode),
            422,
            'UNSUPPORTED_LEAVE_COUNTRY'
        );
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'UNSUPPORTED_LEAVE_COUNTRY';
    }
}
