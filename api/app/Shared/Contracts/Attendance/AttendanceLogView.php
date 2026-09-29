<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Attendance;

use Carbon\Carbon;

/**
 * Vue en lecture seule d'un journal de pointage (BC-12 ATTENDANCE).
 *
 * Permet aux modules consommateurs (Planning — estimations d'heures…) de
 * lire des journaux de présence SANS importer le modèle
 * `Attendance\Domain\Models\AttendanceLog` (règle d'isolation #5584,
 * chantier BOS-023 #8211, cycle 2 #8254).
 *
 * Le modèle `AttendanceLog` **implémente** cette interface : les appelants
 * historiques continuent de passer des modèles Eloquent là où une
 * `AttendanceLogView` est attendue — aucun changement chez les
 * consommateurs, aucune conversion DTO, comportement strictement identique.
 *
 * Les montants horaires (`hours_worked`/`overtime_hours`, cast `decimal:2`)
 * sont exposés en `float`, comme le faisaient les castings `(float)` des
 * consommateurs historiques.
 *
 * Les dates sont typées `Carbon\Carbon` — le type effectif des casts Eloquent
 * `date`/`datetime` sous Larastan (Carbon v3).
 */
interface AttendanceLogView
{
    /** Date du journal (jour de pointage, timezone-naïve par conception). */
    public function date(): ?Carbon;

    /** Horodatage d'arrivée (UTC) — null si session sans pointage d'entrée. */
    public function checkIn(): ?Carbon;

    /** Horodatage de sortie (UTC) — null tant que la session est ouverte. */
    public function checkOut(): ?Carbon;

    /** Numéro de session (1 = session normale, 2+ = split-shift). */
    public function sessionNumber(): ?int;

    /** Heures travaillées enregistrées (null si non encore calculées). */
    public function hoursWorked(): ?float;

    /** Heures supplémentaires enregistrées. */
    public function overtimeHours(): ?float;

    /** Statut du journal (ex. 'late', 'complete'…). */
    public function status(): ?string;

    /** Minutes de retard enregistrées. */
    public function lateMinutes(): ?int;
}
