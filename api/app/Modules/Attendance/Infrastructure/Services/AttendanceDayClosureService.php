<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Exceptions\AttendanceDayClosedException;
use App\Modules\Attendance\Domain\Models\AttendanceDayClosure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Fermeture de journée du pointage (issue #5265).
 *
 * Un verrou `attendance_day_closures` par (company, employee, date) :
 * - `lockDay()` — verrouille (idempotent) ;
 * - `validateDay()` — marque le jour verrouillé comme validé (review manager) ;
 * - `unlockDay()` — lève le verrou ;
 * - `assertDayOpen()` — garde 409 sur tout nouveau pointage d'un jour clos.
 *
 * Complémentaire du verrouillage de période mensuelle (#5267,
 * `AttendancePeriodClosureService`) qui cible les corrections de pointage.
 */
final class AttendanceDayClosureService
{
    /**
     * Le jour est-il verrouillé pour cet employé ?
     *
     * Garde défensive : si la table n'existe pas (environnements de test à
     * schéma partiel — pattern `AttendanceService::ensurePunchPhotoProvided`),
     * aucun verrou n'est considéré actif.
     */
    public function isDayClosed(int $employeeId, string $date): bool
    {
        if (! Schema::hasTable('attendance_day_closures')) {
            return false;
        }

        return AttendanceDayClosure::query()
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->exists();
    }

    /**
     * Garde d'écriture : refuse tout nouveau pointage sur un jour clos.
     *
     * @throws AttendanceDayClosedException
     */
    public function assertDayOpen(int $employeeId, string $date): void
    {
        if ($this->isDayClosed($employeeId, $date)) {
            throw new AttendanceDayClosedException();
        }
    }

    /**
     * Verrouille la journée d'un employé (idempotent : un verrou existant
     * est retourné tel quel, jamais dupliqué — unique company/employee/date).
     */
    public function lockDay(Employee $employee, string $date, Employee $actor, ?string $note = null): AttendanceDayClosure
    {
        /** @var AttendanceDayClosure|null $existing */
        $existing = AttendanceDayClosure::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        /** @var AttendanceDayClosure $closure */
        $closure = AttendanceDayClosure::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'date' => $date,
            'status' => AttendanceDayClosure::STATUS_LOCKED,
            'locked_by' => $actor->id,
            'locked_at' => now(),
            'note' => $note,
        ]);

        return $closure;
    }

    /**
     * Marque un jour verrouillé comme validé (review manager/RH).
     * Idempotent : re-valider un jour déjà validé est sans effet.
     */
    public function validateDay(AttendanceDayClosure $closure, Employee $actor, ?string $note = null): AttendanceDayClosure
    {
        if (! $closure->isValidated()) {
            $closure->update([
                'status' => AttendanceDayClosure::STATUS_VALIDATED,
                'validated_by' => $actor->id,
                'validated_at' => now(),
                'note' => $note ?? $closure->note,
            ]);
        }

        return $closure->fresh() ?? $closure;
    }

    /**
     * Lève le verrou (la journée redevient ouverte aux pointages).
     */
    public function unlockDay(AttendanceDayClosure $closure): void
    {
        $closure->delete();
    }

    /**
     * Liste les fermetures de l'entreprise, filtrables par date et/ou employé.
     *
     * @return Collection<int, AttendanceDayClosure>
     */
    public function listFor(string $companyId, ?string $date = null, ?int $employeeId = null): Collection
    {
        $query = AttendanceDayClosure::query()
            ->with(['employee:id,first_name,last_name', 'lockedBy:id,first_name,last_name', 'validatedBy:id,first_name,last_name'])
            ->where('company_id', $companyId)
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($date !== null) {
            $query->where('date', $date);
        }

        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }

        /** @var Collection<int, AttendanceDayClosure> $closures */
        $closures = $query->get();

        return $closures;
    }
}
