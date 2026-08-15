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
            $balance = $this->currentAvailableBalance($employee, (int) $type->id, (int) $startDate->format('Y'));
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

        AbsenceRequested::dispatch($absence);

        $this->syncLeaveBalanceSnapshot($absence, 'pending_add');

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
            }

            $this->syncLeaveBalanceSnapshot($absence, 'approve');

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
            }

            // Issue #2329: keep the leave_balances snapshot in sync — a rejected
            // pending absence releases its pending days; a rejected approved
            // absence restores the used days.
            if ($absence->absenceType->deducts_leave) {
                $this->syncLeaveBalanceSnapshot(
                    $absence,
                    $absence->status === 'approved' ? 'reject_approved' : 'reject_pending'
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

        $absence->update(['status' => 'cancelled']);

        // Issue #2329: a cancelled pending absence releases its pending days.
        $this->syncLeaveBalanceSnapshot($absence, 'cancel');

        return $absence->fresh();
    }

    public function currentBalance(Employee $employee): float
    {
        $lastLog = LeaveBalanceLog::where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->first();

        return $lastLog ? (float) $lastLog->balance_after : 0.0;
    }

    /**
     * Issue #2418 — solde DISPONIBLE pour une nouvelle demande : le contrôle
     * de création doit réserver les jours `pending` (demandes en attente non
     * encore approuvées), sinon deux demandes parallèles peuvent passer la
     * garde et sur-réserver le solde.
     *
     * Source primaire : le snapshot `leave_balances` (balance − used −
     * pending, synchronisé par #2329) ; fallback chaîne `leave_balance_logs`
     * moins les absences pending pour les données héritées sans snapshot.
     */
    public function currentAvailableBalance(Employee $employee, int $absenceTypeId, int $year): float
    {
        $snapshot = LeaveBalance::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('absence_type_id', $absenceTypeId)
            ->where('year', $year)
            ->first();

        if ($snapshot !== null) {
            return max(0.0, (float) $snapshot->balance - (float) $snapshot->used - (float) $snapshot->pending);
        }

        $available = $this->currentBalance($employee);

        $pendingDays = (float) Absence::query()
            ->where('employee_id', $employee->id)
            ->where('absence_type_id', $absenceTypeId)
            ->where('status', 'pending')
            ->sum('days_count');

        return max(0.0, $available - $pendingDays);
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
     * Keep the leave_balances snapshot in sync with the leave_balance_logs
     * chain (source of truth). Issue #2329.
     *
     * The snapshot row is keyed by (company_id, employee_id, absence_type_id,
     * year) — the year being the year of the absence start date. Non-deductible
     * absence types never touch the snapshot.
     *
     * Supported actions:
     * - pending_add:     pending += days       (absence created)
     * - approve:         pending -= days, used += days
     * - reject_pending:  pending -= days
     * - reject_approved: used -= days
     * - cancel:          pending -= days
     */
    private function syncLeaveBalanceSnapshot(Absence $absence, string $action): void
    {
        $type = $absence->absenceType;

        if ($type === null || ! $type->deducts_leave) {
            return;
        }

        $year = (int) Carbon::parse($absence->start_date)->format('Y');
        $days = (float) $absence->days_count;

        $snapshot = LeaveBalance::query()->firstOrCreate(
            [
                'company_id' => $absence->company_id,
                'employee_id' => $absence->employee_id,
                'absence_type_id' => $type->id,
                'year' => $year,
            ],
            ['balance' => 0, 'used' => 0, 'pending' => 0]
        );

        match ($action) {
            'pending_add' => $snapshot->increment('pending', $days),
            'approve' => $snapshot->update([
                'pending' => max(0, (float) $snapshot->pending - $days),
                'used' => (float) $snapshot->used + $days,
            ]),
            'reject_pending' => $snapshot->update([
                'pending' => max(0, (float) $snapshot->pending - $days),
            ]),
            'reject_approved' => $snapshot->update([
                'used' => max(0, (float) $snapshot->used - $days),
            ]),
            'cancel' => $snapshot->update([
                'pending' => max(0, (float) $snapshot->pending - $days),
            ]),
            default => throw new \InvalidArgumentException("Unknown leave balance snapshot action [{$action}]."),
        };
    }
}
