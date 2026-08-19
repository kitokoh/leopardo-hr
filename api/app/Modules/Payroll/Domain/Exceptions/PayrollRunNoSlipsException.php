<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Issue #1767 / #1912 — un run sans aucun bulletin ne doit jamais être validé
 * ni verrouillé. Code stable + message localisé (contrat #3810/#4310) : le
 * message brut est conservé pour les logs, le client consomme
 * `localized_message` depuis le catalogue errors.*.
 */
class PayrollRunNoSlipsException extends DomainException
{
    public function __construct(
        ?string $message = null,
        private readonly string $errorCode = 'PAYROLL_RUN_NO_SLIPS'
    ) {
        parent::__construct(
            $message ?? 'Ce run ne contient aucun bulletin — impossible de valider/clôturer une paie vide. Vérifiez les structures salariales actives et recalculez.',
            422,
            $errorCode
        );
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
