<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Events\AbsenceRequested;
use App\Exceptions\AbsenceDateConflictException;
use App\Exceptions\AbsenceNotPendingException;
use App\Exceptions\InsufficientLeaveBalanceException;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeaveBalanceLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AbsenceService
{
    public function create(Employee $employee, array $data, ?UploadedFile $proof = null): Absence
    {
        $type = AbsenceType::findOrFail($data['absence_type_id']);

        if ($type->company_id !== $employee->company_id) {
            abort(404);
        }

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
            throw new AbsenceDateConflictException;
        }

        // PA2-MOB-006: persist the optional supporting document (medical
        // note, justification letter, etc.) under a company-scoped path so
        // it is visible to both the employee and the deciding manager.
        $proofPath = $proof?->store('absences/proofs/'.$employee->company_id, 'local');

        $absence = Absence::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days_count' => $daysCount,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
            'proof_path' => $proofPath,
        ]);

        if ($type->deducts_leave) {
            // Snapshot leave_balances (served by /me/leave-balances) : a
            // pending request reserves the days (issue #2329).
            $this->adjustLeaveBalanceSnapshot(
                $employee->id,
                $employee->company_id,
                $type->id,
                $data['start_date'],
                'pending',
                (float) $daysCount
            );
        }

        AbsenceRequested::dispatch($absence);

        return $absence;
    }

    public function approve(Absence $absence, Employee $approver): Absence
    {
        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException;
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

                if ($currentBalance < $absence->days_count) {
                    throw new InsufficientLeaveBalanceException($currentBalance, (float) $absence->days_count);
                }

                $newBalance = $currentBalance - $absence->days_count;

                $this->logBalanceChange(
                    $absence->employee_id,
                    $absence->company_id,
                    -(float) $absence->days_count,
                    'absence_approved',
                    $absence->id,
                    $newBalance
                );

                // Snapshot leave_balances : pending → used (issue #2329).
                $this->adjustLeaveBalanceSnapshot(
                    $absence->employee_id,
                    $absence->company_id,
                    $type->id,
                    (string) $absence->start_date,
                    'pending',
                    -(float) $absence->days_count
                );
                $this->adjustLeaveBalanceSnapshot(
                    $absence->employee_id,
                    $absence->company_id,
                    $type->id,
                    (string) $absence->start_date,
                    'used',
                    (float) $absence->days_count
                );
            }

            $absence->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
            ]);
        });

        $absence = $absence->fresh();

        AbsenceApproved::dispatch($absence, $approver);

        return $absence;
    }

    public function reject(Absence $absence, string $reason): Absence
    {
        if (! in_array($absence->status, ['pending', 'approved'])) {
            throw new AbsenceNotPendingException;
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

                // Snapshot leave_balances : used restored (issue #2329).
                $this->adjustLeaveBalanceSnapshot(
                    $absence->employee_id,
                    $absence->company_id,
                    $absence->absenceType->id,
                    (string) $absence->start_date,
                    'used',
                    -(float) $absence->days_count
                );
            } elseif ($absence->status === 'pending' && $absence->absenceType->deducts_leave) {
                // Snapshot leave_balances : pending released (issue #2329).
                $this->adjustLeaveBalanceSnapshot(
                    $absence->employee_id,
                    $absence->company_id,
                    $absence->absenceType->id,
                    (string) $absence->start_date,
                    'pending',
                    -(float) $absence->days_count
                );
            }

            $absence->update([
                'status' => 'rejected',
                'rejected_reason' => $reason,
            ]);
        });

        $absence = $absence->fresh();

        AbsenceRejected::dispatch($absence);

        return $absence;
    }

    public function cancel(Absence $absence): Absence
    {
        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException;
        }

        DB::transaction(function () use ($absence): void {
            if ($absence->absenceType->deducts_leave) {
                // Snapshot leave_balances : pending released on cancel
                // (issue #2329).
                $this->adjustLeaveBalanceSnapshot(
                    $absence->employee_id,
                    $absence->company_id,
                    $absence->absenceType->id,
                    (string) $absence->start_date,
                    'pending',
                    -(float) $absence->days_count
                );
            }

            $absence->update(['status' => 'cancelled']);
        });

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
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function logBalanceChange(int $employeeId, int|string|null $companyId, float $delta, string $reason, int $referenceId, float $balanceAfter): LeaveBalanceLog
    {
        return LeaveBalanceLog::create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'delta' => $delta,
            'reason' => $reason,
            'reference_id' => $referenceId,
            'balance_after' => $balanceAfter,
        ]);
    }

    /**
     * Keep the leave_balances snapshot (served by /me/leave-balances) in sync
     * with the leave_balance_logs chain of truth (issue #2329).
     *
     * The snapshot is keyed per (company, employee, absence_type, year of the
     * absence start). `pending`/`used` are clamped at 0 so a snapshot row
     * created after the fact (e.g. historical absence approved before this
     * sync existed) never goes negative.
     */
    private function adjustLeaveBalanceSnapshot(
        int $employeeId,
        int|string|null $companyId,
        int $absenceTypeId,
        string $startDate,
        string $column,
        float $delta
    ): void {
        if ($delta == 0.0) {
            return;
        }

        $balance = LeaveBalance::firstOrCreate(
            [
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'absence_type_id' => $absenceTypeId,
                'year' => (int) Carbon::parse($startDate)->format('Y'),
            ],
            ['balance' => 0, 'used' => 0, 'pending' => 0]
        );

        $current = (float) $balance->getAttribute($column);
        $balance->setAttribute($column, max(0.0, $current + $delta));
        $balance->save();
    }
}
