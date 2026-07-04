<?php

declare(strict_types=1);

namespace App\Modules\Absence\Infrastructure\Services;

use App\Modules\Absence\Application\DTOs\RequestAbsenceDTO;
use App\Modules\Absence\Domain\Exceptions\AbsenceDateConflictException;
use App\Modules\Absence\Domain\Exceptions\AbsenceNotPendingException;
use App\Modules\Absence\Domain\Exceptions\InsufficientLeaveBalanceException;
use App\Modules\Absence\Domain\Models\Absence;
use App\Modules\Absence\Domain\Models\LeaveBalance;
use Carbon\CarbonPeriod;

class AbsenceService
{
    /**
     * @throws AbsenceDateConflictException
     * @throws InsufficientLeaveBalanceException
     */
    public function request(RequestAbsenceDTO $dto): Absence
    {
        // Check for date conflict
        $conflict = Absence::query()
            ->where('employee_id', $dto->employeeId)
            ->where('status', '!=', 'rejected')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($dto) {
                $q->whereBetween('start_date', [$dto->startDate, $dto->endDate])
                  ->orWhereBetween('end_date', [$dto->startDate, $dto->endDate]);
            })
            ->exists();

        if ($conflict) {
            throw new AbsenceDateConflictException();
        }

        // Check leave balance
        $days = CarbonPeriod::create($dto->startDate, $dto->endDate)
            ->filter('isWeekday')
            ->count();

        $balance = LeaveBalance::query()
            ->where('employee_id', $dto->employeeId)
            ->where('absence_type_id', $dto->absenceTypeId)
            ->where('year', $dto->startDate->year)
            ->first();

        if ($balance) {
            $available = ($balance->allocated + ($balance->carried_over ?? 0)) - $balance->used;
            if ($days > $available) {
                throw new InsufficientLeaveBalanceException($days, $available);
            }
        }

        return Absence::create([
            'employee_id'      => $dto->employeeId,
            'absence_type_id'  => $dto->absenceTypeId,
            'start_date'       => $dto->startDate,
            'end_date'         => $dto->endDate,
            'days_count'       => max(1, $days),
            'status'           => 'pending',
            'reason'           => $dto->reason,
        ]);
    }

    /**
     * @throws AbsenceNotPendingException
     */
    public function approve(Absence $absence, int $approvedBy, ?string $comment = null): Absence
    {
        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException();
        }

        $absence->update([
            'status'      => 'approved',
            'approved_by' => $approvedBy,
        ]);

        return $absence->fresh();
    }

    /**
     * @throws AbsenceNotPendingException
     */
    public function reject(Absence $absence, int $rejectedBy, string $comment): Absence
    {
        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException();
        }

        $absence->update([
            'status'          => 'rejected',
            'approved_by'     => $rejectedBy,
            'rejected_reason' => $comment,
        ]);

        return $absence->fresh();
    }
}
