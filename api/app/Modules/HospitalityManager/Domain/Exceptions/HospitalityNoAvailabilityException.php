<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HOSP-004 (#7946) — aucune unité disponible du type demandé sur
 * l'intervalle (anti-overbooking transactionnel, refus fail-closed).
 */
class HospitalityNoAvailabilityException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            __('Aucune disponibilité pour ce type de chambre sur cet intervalle.'),
            409,
            'HOSPITALITY_NO_AVAILABILITY'
        );
    }
}
