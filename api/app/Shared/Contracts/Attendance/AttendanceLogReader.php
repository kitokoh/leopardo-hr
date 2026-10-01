<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Attendance;

/**
 * Lecteur partagé des journaux de pointage (BC-12 ATTENDANCE).
 *
 * Permet aux modules consommateurs (Planning — estimations d'heures…) de
 * requêter les journaux SANS import croisé `Modules/X -> Modules/Attendance`
 * (règle d'isolation #5584, chantier BOS-023 #8211, cycle 2 #8254) — ils ne
 * dépendent que de ce contrat, implémenté par
 * `Attendance\Infrastructure\Services\AttendanceLogReaderAdapter`.
 *
 * Les requêtes reprennent à l'identique celles qu'`EstimationService`
 * (Planning) exécutait historiquement : mêmes colonnes projetées, mêmes
 * tris — comportement strictement préservé.
 */
interface AttendanceLogReader
{
    /**
     * Journaux d'un employé pour une date (Y-m-d), triés par numéro de
     * session croissant.
     *
     * @return array<int, AttendanceLogView>
     */
    public function logsForEmployeeOnDate(int $employeeId, string $date): array;

    /**
     * Journaux d'un employé sur une plage de dates inclusive (Y-m-d),
     * triés par date puis numéro de session croissants.
     *
     * @return array<int, AttendanceLogView>
     */
    public function logsForEmployeeBetween(int $employeeId, string $fromDate, string $toDate): array;

    /**
     * Existence d'au moins un journal pour un employé dans une entreprise
     * (requête historique de `MobileExperienceService`, HR — cycle 3 #8299).
     */
    public function hasAnyLogForEmployee(string $companyId, int $employeeId): bool;

    /**
     * Dernier journal du jour par employé, pour un ensemble d'employés
     * (requête historique d'`EmployeeController::attachOperationalState`,
     * HR — cycle 3 #8299 : tri session_number puis check_in décroissants,
     * gardes `Schema::hasColumn` conservées, sans projection restrictive).
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, AttendanceLogView> indexé par employee_id
     */
    public function latestLogsPerEmployeeOnDate(array $employeeIds, string $date): array;

    /**
     * Journal « du jour » d'un employé : session ouverte d'abord, puis
     * numéro de session décroissant (requête historique de
     * `MeController::today`, HR — cycle 3 #8299, mêmes colonnes projetées).
     */
    public function latestLogForEmployeeOnDate(int $employeeId, string $date): ?AttendanceLogView;

    /**
     * Totaux d'heures supplémentaires par employé sur une plage de dates
     * inclusive (Y-m-d) — agrégat historique de `HrReportController::overtime`
     * (HR — cycle 3 #8299) : mêmes filtres, même tri (total décroissant),
     * même limite. Valeurs brutes du driver (sum() PG décimal → string),
     * JSON strictement identique.
     *
     * @return array<int, array{employee_id: int, total_overtime: mixed, days_with_overtime: int}>
     */
    public function overtimeTotalsBetween(string $fromDate, string $toDate, int $limit = 50): array;
}
