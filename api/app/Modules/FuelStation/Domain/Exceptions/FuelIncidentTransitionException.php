<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * FUEL-010 (#5804) — transition de statut d'incident invalide.
 * Le cycle open → assigned → in_progress → resolved → closed est validé en
 * application : toute transition illégale échoue avec ce code explicite.
 */
class FuelIncidentTransitionException extends DomainException
{
    public function __construct(string $message = 'Transition de statut d\'incident invalide.')
    {
        parent::__construct($message, 422, 'FUEL_INCIDENT_BAD_TRANSITION');
    }
}
