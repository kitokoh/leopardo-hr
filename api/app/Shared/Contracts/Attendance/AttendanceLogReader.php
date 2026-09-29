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
}
