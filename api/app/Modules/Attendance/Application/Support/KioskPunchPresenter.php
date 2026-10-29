<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Support;

use App\Modules\Attendance\Domain\Models\AttendanceLog;

/**
 * Sérialisation de la réponse « pointage » du kiosque.
 *
 * Issue #7962 — `punch()` et `verifyFace()` sérialisaient le même objet
 * `EmployeeAttendanceLog` en deux copies du même tableau, avec des clés qui
 * divergeaient déjà (seul `verifyFace` rendait `correlation_id`, seul `punch`
 * rendait `unverified` et `replayed`). Un client kiosque qui bascule d'un mode
 * à l'autre — c'est précisément ce que fait le repli biométrique — devait donc
 * gérer deux formes de réponse pour le même événement métier.
 *
 * Le tableau commun vit ici une seule fois ; les clés propres à un flux sont
 * ajoutées par l'appelant.
 */
final class KioskPunchPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(AttendanceLog $log): array
    {
        return [
            'employee_id' => $log->employee_id,
            'date' => $log->date?->format('Y-m-d'),
            'check_in' => self::iso($log->check_in),
            'check_out' => self::iso($log->check_out),
            'method' => $log->method,
            'work_type' => $log->work_type,
            'session_number' => $log->session_number,
            'status' => $log->status,
        ];
    }

    /**
     * Les colonnes `check_in`/`check_out` sont typées `mixed` par Eloquent ;
     * on ne convertit qu'un vrai `DateTimeInterface` (une valeur inattendue
     * reste sérialisée telle quelle au lieu de faire tomber la réponse).
     */
    private static function iso(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DATE_ATOM) : $value;
    }
}
