<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\DTOs;

/**
 * Résultat du calcul des heures d'un pointage (issue #5265).
 *
 * Produit par AttendanceHoursCalculator::workedHours() — source de
 * vérité unique pour tous les modes de pointage.
 */
final readonly class WorkedHoursDTO
{
    public function __construct(
        public float $hours_worked,
        public float $overtime_hours,
    ) {}
}
