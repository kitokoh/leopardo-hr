<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Shared\Contracts\Attendance\AttendanceLogReader;
use App\Shared\Contracts\Attendance\AttendanceLogView;

/**
 * Adapter du contrat partagé `AttendanceLogReader` (#8254, BOS-023 cycle 2).
 *
 * Le module Attendance est propriétaire du modèle `AttendanceLog` : toute
 * lecture cross-module passe par ce contrat au lieu d'importer le modèle
 * directement (règle d'isolation #5584).
 *
 * Les deux requêtes reprennent à l'identique celles qu'`EstimationService`
 * (Planning) exécutait historiquement : mêmes colonnes projetées, mêmes
 * filtres, mêmes tris. Les modèles retournés implémentent
 * `AttendanceLogView` — aucun DTO intermédiaire, comportement préservé.
 */
final class AttendanceLogReaderAdapter implements AttendanceLogReader
{
    /** Colonnes projetées — historiquement sélectionnées par EstimationService. */
    private const COLUMNS = [
        'id', 'employee_id', 'date', 'session_number', 'check_in', 'check_out',
        'hours_worked', 'overtime_hours', 'status', 'work_type', 'late_minutes',
    ];

    public function logsForEmployeeOnDate(int $employeeId, string $date): array
    {
        return AttendanceLog::query()
            ->select(self::COLUMNS)
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->orderBy('session_number')
            ->get()
            ->all();
    }

    public function logsForEmployeeBetween(int $employeeId, string $fromDate, string $toDate): array
    {
        return AttendanceLog::query()
            ->select(self::COLUMNS)
            ->where('employee_id', $employeeId)
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date')
            ->orderBy('session_number')
            ->get()
            ->all();
    }
}
