<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * FUEL-010 — transition de workflow interdite (incident / tâche de
 * maintenance). Renvoyée en 422 pour que le client puisse afficher le
 * motif sans exposer de stack trace.
 */
class FuelWorkflowTransitionException extends DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            $reason,
            422,
            'FUEL_WORKFLOW_TRANSITION_FORBIDDEN'
        );
    }
}
