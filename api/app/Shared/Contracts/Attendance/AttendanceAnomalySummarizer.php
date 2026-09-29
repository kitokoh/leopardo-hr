<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Attendance;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Résumé des anomalies de pointage vues par l'appelant (BC-12 ATTENDANCE).
 *
 * Permet aux modules consommateurs (HR — endpoint self-service
 * `/me/attendance/anomalies`) de consommer le service d'anomalies SANS
 * importer `Attendance\Infrastructure\Services\AttendanceAnomalyService`,
 * ni la classe du modèle pour l'autorisation (règle d'isolation #5584,
 * chantier BOS-023 #8211, cycle 3 #8299).
 *
 * L'autorisation `viewOwnAnomalies` (Gate sur la classe du modèle) est
 * appliquée côté adapter, dans le module propriétaire — sémantique 403
 * strictement inchangée pour l'appelant.
 */
interface AttendanceAnomalySummarizer
{
    /**
     * Résumé des anomalies de l'employé appelant. Les filtres validés
     * (date_from, date_to, per_page…) sont fusionnés avec l'identifiant
     * de l'appelant — forcé côté adapter, jamais celui de la requête.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summarizeOwnAnomalies(Employee $employee, array $filters): array;
}
