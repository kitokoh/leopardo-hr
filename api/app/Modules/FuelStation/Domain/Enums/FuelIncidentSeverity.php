<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * #5804 — Sévérité d'un incident équipement (FUEL-010).
 */
enum FuelIncidentSeverity: string
{
    case Low = 'low';

    case Medium = 'medium';

    case High = 'high';

    case Critical = 'critical';
}
