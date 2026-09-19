<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-004 (#7788) — transition de statut absente de la machine à états
 * (HealthAppointment::TRANSITIONS ou cycle de vie d'une admission HC-006)
 * → 422 fail-closed.
 */
class HealthInvalidStatusTransitionException extends DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(
            sprintf('Transition de statut invalide: "%s" -> "%s".', $from, $to),
            422,
            'HEALTH_INVALID_STATUS_TRANSITION'
        );
    }
}
