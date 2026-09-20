<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-002 (#7786) — suppression bloquée : la ressource du référentiel est
 * encore utilisée (service avec salles/praticiens, salle avec lits, lit
 * occupé, spécialité affectée, praticien avec activité clinique) → 422.
 */
class HealthResourceInUseException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Suppression impossible : la ressource est encore en usage.',
            422,
            'HEALTH_RESOURCE_IN_USE'
        );
    }
}
