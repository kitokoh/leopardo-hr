<?php

declare(strict_types=1);

namespace App\Modules\Planning\Domain\Exceptions;

use App\Exceptions\DomainException;

class InsufficientLeaveBalanceException extends DomainException
{
    /**
     * Issue #6573 — signature alignee sur l'exception legacy (available,
     * requested) pour que les sites d'appel existants fonctionnent sans
     * modification.
     */
    public function __construct(float $available, float $requested)
    {
        parent::__construct(
            "Solde de congés insuffisant. Solde disponible : {$available} jours, demandé : {$requested} jours.",
            422
        );
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_LEAVE_BALANCE';
    }
}
