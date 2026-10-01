<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Shared\Contracts\Attendance\AttendanceLogReader;
use App\Shared\Contracts\Attendance\AttendanceLogView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Requête reprise à l'identique de `MobileExperienceService` (HR,
     * cycle 3 #8299) — filtre explicite company_id conservé bien que le
     * scope tenant global s'applique déjà.
     */
    public function hasAnyLogForEmployee(string $companyId, int $employeeId): bool
    {
        return AttendanceLog::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->exists();
    }

    /**
     * Requête reprise à l'identique d'`EmployeeController::attachOperationalState`
     * (HR, cycle 3 #8299) : aucune projection restrictive, gardes
     * `Schema::hasColumn` défensives conservées, regroupement par employé
     * après récupération (comportement strictement identique).
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, AttendanceLogView>
     */
    public function latestLogsPerEmployeeOnDate(array $employeeIds, string $date): array
    {
        return AttendanceLog::query()
            ->whereIn('employee_id', $employeeIds)
            ->when(Schema::hasColumn('attendance_logs', 'date'), fn (Builder $query) => $query->whereDate('date', $date))
            ->when(Schema::hasColumn('attendance_logs', 'session_number'), fn (Builder $query) => $query->orderByDesc('session_number'))
            ->when(Schema::hasColumn('attendance_logs', 'check_in'), fn (Builder $query) => $query->orderByDesc('check_in'))
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($logs) => $logs->first())
            ->all();
    }

    /**
     * Requête reprise à l'identique de `MeController::today` (HR, cycle 3
     * #8299) : mêmes colonnes projetées, session ouverte d'abord puis
     * numéro de session décroissant.
     */
    public function latestLogForEmployeeOnDate(int $employeeId, string $date): ?AttendanceLogView
    {
        return AttendanceLog::query()
            ->select(self::COLUMNS)
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->orderByRaw('CASE WHEN check_out IS NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('session_number')
            ->first();
    }

    /**
     * Agrégat repris à l'identique de `HrReportController::overtime` (HR,
     * cycle 3 #8299) : mêmes filtres, groupement, tri décroissant sur le
     * total et limite. `total_overtime` reste la valeur brute du driver
     * (sum() PG → string décimale) : JSON de réponse inchangé.
     *
     * @return array<int, array{employee_id: int, total_overtime: mixed, days_with_overtime: int}>
     */
    public function overtimeTotalsBetween(string $fromDate, string $toDate, int $limit = 50): array
    {
        return AttendanceLog::query()
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->where('overtime_hours', '>', 0)
            ->select([
                'employee_id',
                DB::raw('sum(overtime_hours) as total_overtime'),
                DB::raw('count(*) as days_with_overtime'),
            ])
            ->groupBy('employee_id')
            ->orderByDesc('total_overtime')
            ->limit($limit)
            ->get()
            ->map(fn (AttendanceLog $row): array => [
                'employee_id' => (int) $row->employee_id,
                'total_overtime' => $row->getAttribute('total_overtime'),
                'days_with_overtime' => (int) $row->getAttribute('days_with_overtime'),
            ])
            ->all();
    }
}
