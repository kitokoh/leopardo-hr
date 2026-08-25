<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\DTOs;

/**
 * Résultat du calcul de retard d'un pointage (issue #5265).
 *
 * Produit par AttendanceHoursCalculator::lateAssessment() — source de
 * vérité unique pour tous les modes de pointage (mobile, kiosque, géo,
 * ZKTeco, import externe).
 */
final readonly class LateAssessmentDTO
{
    /**
     * @param 'late'|'ontime' $status Statut déduit du retard (source unique #5265).
     */
    public function __construct(
        public int $late_minutes,
        public string $status,
    ) {}
}
