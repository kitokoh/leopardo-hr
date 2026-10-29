<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Support;

use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;

/**
 * Sérialisation des demandes de correction de pointage (issue #7962).
 *
 * Extrait de `AttendanceController::correctionPayload()` /
 * `correctionAnomaly()` : la mise en forme n'est pas de la responsabilité du
 * contrôleur, et le signalement anti-fraude (#5267) est une règle métier
 * réutilisable (liste, décision, export).
 */
class AttendanceCorrectionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function payload(AttendanceCorrectionRequest $correction): array
    {
        return [
            'id' => $correction->id,
            'company_id' => $correction->company_id,
            'employee_id' => $correction->employee_id,
            'attendance_log_id' => $correction->attendance_log_id,
            'employee' => $correction->relationLoaded('employee') && $correction->employee ? [
                'id' => $correction->employee->id,
                'name' => trim(($correction->employee->first_name ?? '').' '.($correction->employee->last_name ?? '')),
                'matricule' => $correction->employee->matricule,
            ] : null,
            'date' => $correction->date->format('Y-m-d'),
            'requested_check_in' => $correction->requested_check_in->toIso8601String(),
            'requested_check_out' => $correction->requested_check_out?->toIso8601String(),
            'reason' => $correction->reason,
            // Issue #5267 : justificatif + signalement anti-fraude.
            'proof_url' => $correction->proof_path !== null
                ? route('attendance.corrections.proof', ['correction' => $correction->id])
                : null,
            'anomaly' => $this->anomaly($correction),
            'status' => $correction->status,
            'reviewed_by' => $correction->reviewed_by,
            'reviewed_at' => $correction->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * Issue #5267 — anti-fraude : signale une correction dont les horaires
     * demandés contredisent une session géo VALIDÉE (écart > 15 min).
     *
     * @return array{flagged: bool, reason: string|null, session: array<string, mixed>|null}|null
     */
    public function anomaly(AttendanceCorrectionRequest $correction): ?array
    {
        $session = GeoAttendanceSession::query()
            ->where('company_id', $correction->company_id)
            ->where('employee_id', $correction->employee_id)
            ->where('status', 'approved')
            ->whereDate('started_at', $correction->date->toDateString())
            ->first();

        if ($session === null) {
            return null;
        }

        $thresholdMinutes = 15;
        $conflicts = [];

        if (abs($correction->requested_check_in->diffInMinutes($session->started_at)) > $thresholdMinutes) {
            $conflicts[] = 'check_in';
        }
        if ($session->ended_at !== null && $correction->requested_check_out !== null
            && abs($correction->requested_check_out->diffInMinutes($session->ended_at)) > $thresholdMinutes) {
            $conflicts[] = 'check_out';
        }

        if ($conflicts === []) {
            return null;
        }

        return [
            'flagged' => true,
            'reason' => 'geo_session_conflict',
            'session' => [
                'id' => $session->id,
                'started_at' => $session->started_at->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
            ],
        ];
    }
}
