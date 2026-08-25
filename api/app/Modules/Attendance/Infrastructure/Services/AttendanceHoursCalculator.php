<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Application\DTOs\LateAssessmentDTO;
use App\Modules\Attendance\Application\DTOs\WorkedHoursDTO;
use Illuminate\Support\Carbon;

/**
 * Calculateur pur des règles de pointage (issue #5265).
 *
 * Source de vérité UNIQUE pour le calcul des heures travaillées, retards et
 * heures supplémentaires — consommé par tous les modes de pointage (mobile,
 * kiosque, géo, ZKTeco, import externe) via AttendanceService et
 * ApproveGeoSession. Les formules reproduisent à l'identique les blocs
 * historiques (aucun changement de comportement pour les modes existants ;
 * le mode géo converge vers les mêmes règles, notamment la déduction des
 * pauses du planning).
 *
 * Note #2686 (QA 2026-08-15) : les types non travaillés ne doivent pas
 * compter dans hours_worked/overtime — `resume` marque la fin d'une pause
 * (pas une session de travail), au même titre que `break`.
 */
final class AttendanceHoursCalculator
{
    /** @var array<int, string> */
    private const NON_WORK_TYPES = ['break', 'resume'];

    /**
     * Évalue le retard d'un check-in par rapport à l'horaire planifié.
     *
     * Formule historique : max(0, floor(diffMinutes - tolerance)) — le retard
     * négatif (arrivée avant l'heure) est ramené à 0 et le statut est
     * 'late' dès que le retard dépasse la tolérance.
     *
     * @param  Carbon  $checkInLocal  heure de pointage dans le fuseau de l'entreprise
     * @param  Carbon  $scheduledStartLocal  début de service planifié (même fuseau)
     */
    public function lateAssessment(Carbon $checkInLocal, Carbon $scheduledStartLocal, int $toleranceMinutes): LateAssessmentDTO
    {
        $diffMinutes = $scheduledStartLocal->diffInMinutes($checkInLocal, false);
        $lateMinutes = max(0, (int) floor($diffMinutes - $toleranceMinutes));

        return new LateAssessmentDTO(
            late_minutes: $lateMinutes,
            status: $lateMinutes > 0 ? 'late' : 'ontime',
        );
    }

    /**
     * Calcule les heures travaillées et les heures supplémentaires.
     *
     * Formule historique : heures brutes (différence check-in/check-out)
     * moins les pauses déduites ; heures supplémentaires = heures − seuil
     * quotidien, sauf `work_type = overtime` (heures pleines) ; types non
     * travaillés (`break`/`resume`) → 0 h / 0 HS (#2686).
     *
     * @param  Carbon  $checkIn  timestamp UTC du check-in
     * @param  Carbon  $checkOut  timestamp UTC du check-out
     * @param  string  $workType  type de session (normal|overtime|break|resume)
     * @param  int  $breakMinutes  minutes de pause déduites (0 si non applicables)
     * @param  float  $threshold  seuil quotidien d'heures supplémentaires
     */
    public function workedHours(Carbon $checkIn, Carbon $checkOut, string $workType, int $breakMinutes, float $threshold): WorkedHoursDTO
    {
        $seconds = $checkIn->diffInSeconds($checkOut);
        $grossHours = $seconds / 3600;
        $hours = round(max(0.0, $grossHours - ($breakMinutes / 60)), 2);

        $overtime = $workType === 'overtime'
            ? $hours
            : max(0.0, round($hours - $threshold, 2));

        if (in_array($workType, self::NON_WORK_TYPES, true)) {
            $hours = 0.0;
            $overtime = 0.0;
        }

        return new WorkedHoursDTO(
            hours_worked: $hours,
            overtime_hours: $overtime,
        );
    }

    /**
     * Détermine les minutes de pause applicables à une session.
     *
     * Règle historique (ex-bloc privé `AttendanceService::breakMinutesForLog`) :
     * la pause du planning ne s'applique qu'à la première session de la
     * journée en type normal, et jamais quand le pointage de sortie est lui
     * même un `break`.
     */
    public function effectiveBreakMinutes(
        int $sessionNumber,
        string $workType,
        ?int $scheduleBreakMinutes,
        ?string $dtoWorkType = null,
    ): int {
        if ($sessionNumber > 1 || $workType !== 'normal') {
            return 0;
        }

        if ($dtoWorkType === 'break') {
            return 0;
        }

        return $scheduleBreakMinutes ?? 0;
    }
}
