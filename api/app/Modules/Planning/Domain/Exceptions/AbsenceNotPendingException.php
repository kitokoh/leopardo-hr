<?php

declare(strict_types=1);

namespace App\Modules\Planning\Domain\Exceptions;

use App\Exceptions\DomainException;

class AbsenceNotPendingException extends DomainException
{
    /**
     * Issue #6573 — le parametre id est optionnel pour rester compatible avec
     * les sites d'appel legacy (message enrichi quand il est fourni).
     */
    public function __construct(string $id = '')
    {
        parent::__construct(
            $id !== ''
                ? "Absence [{$id}] is not in pending status."
                : "Cette absence n'est pas en attente d'approbation.",
            422
        );
    }

    public function errorCode(): string
    {
        return 'ABSENCE_NOT_PENDING';
    }
}
