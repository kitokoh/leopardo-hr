<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-004 (#7788) — transition de statut de rendez-vous hors machine à
 * états (spec §4) : scheduled→confirmed|cancelled ;
 * confirmed→checked_in|cancelled|no_show ; checked_in→completed ;
 * terminaux : completed, cancelled, no_show → 422 HEALTH_INVALID_TRANSITION.
 */
class HealthInvalidTransitionException extends DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(
            "Transition de rendez-vous invalide : {$from} → {$to}.",
            422,
            'HEALTH_INVALID_TRANSITION'
        );
    }
}
