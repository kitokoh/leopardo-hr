<?php

namespace App\Services;

use App\Exceptions\AbsenceDateConflictException;
use App\Exceptions\AbsenceNotPendingException;
use App\Exceptions\InsufficientLeaveBalanceException;
use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\LeaveBalanceLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AbsenceService
{
    public function create(Employee $employee, array $data): Absence
    {
        $type = AbsenceType::findOrFail($data['absence_type_id']);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $daysCount = $startDate->diffInDays($endDate) + 1;

        if ($type->deducts_leave) {
            $balance = $this->currentBalance($employee);
            if ($balance < $daysCount) {
                throw new InsufficientLeaveBalanceException($balance, $daysCount);
            }
        }

        if ($this->hasDateConflict($employee, $data['start_date'], $data['end_date'])) {
            throw new AbsenceDateConflictException();
        }

        return Absence::create([
            'company_id'      => $employee->company_id,
            'employee_id'     => $employee->id,
            'absence_type_id' => $type->id,
            'start_date'      => $data['start_date'],
            'end_date'        => $data['end_date'],
            'days_count'      => $daysCount,
            'status'          => 'pending',
            'reason'          => $data['reason'] ?? null,
        ]);
    }

    public function approve(Absence $absence, Employee $approver): Absence
    {
        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException();
        }

        DB::transaction(function () use ($absence, $approver) {
            $type = $absence->absenceType;

            if ($type->deducts_leave) {
                // Lock last balance row to prevent race conditions
                $lastLog = LeaveBalanceLog::where('employee_id', $absence->employee_id)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();

                $currentBalance = $lastLog ? (float) $lastLog->balance_after : 0.0;
                $newBalance = $currentBalance - $absence->days_count;

                $this->logBalanceChange(
                    $absence->employee_id,
                    $absence->company_id,
                    -(float) $absence->days_count,
                    'absence_approved',
                    $absence->id,
                    $newBalance
                );
            }

            $absence->update([
                'status'      => 'approved',
                'approved_by' => $approver->id,
            ]);
        });

        return $absence->fresh();
    }

    public function reject(Absence $absence, string $reason): Absence
    {
        if (!in_array($absence->status, ['pending', 'approved'])) {
            throw new AbsenceNotPendingException();
        }

        DB::transaction(function () use ($absence, $reason) {
            // If already approved and balance was deducted, restore it
            if ($absence->status === 'approved' && $absence->absenceType->deducts_leave) {
                $lastLog = LeaveBalanceLog::where('employee_id', $absence->employee_id)
                    ->orderByDesc('id')
                    ->first();

                $currentBalance = $lastLog ? (float) $lastLog->balance_after : 0.0;
                $newBalance = $currentBalance + $absence->days_count;

                $this->logBalanceChange(
                    $absence->employee_id,
                    $absence->company_id,
                    (float) $absence->days_count,
                    'absence_rejected',
                    $absence->id,
                    $newBalance
                );
            }

            $absence->update([
                'status'          => 'rejected',
                'rejected_reason' => $reason,
            ]);
        });

        return $absence->fresh();
    }

    public function cancel(Absence $absence): Absence
    {
        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException();
        }

        $absence->update(['status' => 'cancelled']);

        return $absence->fresh();
    }

    public function currentBalance(Employee $employee): float
    {
        $lastLog = LeaveBalanceLog::where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->first();

        return $lastLog ? (float) $lastLog->balance_after : 0.0;
    }

    private function hasDateConflict(Employee $employee, string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        $query = Absence::where('employee_id', $employee->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function logBalanceChange(int $employeeId, string $companyId, float $delta, string $reason, int $referenceId, float $balanceAfter): LeaveBalanceLog
    {
        return LeaveBalanceLog::create([
            'company_id'    => $companyId,
            'employee_id'   => $employeeId,
            'delta'         => $delta,
            'reason'        => $reason,
            'reference_id'  => $referenceId,
            'balance_after' => $balanceAfter,
        ]);
    }
}
