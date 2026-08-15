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

        // #2329 : snapshot leave_balances synchronisé — la demande pending
        // réserve les jours (pending += days). La source de vérité reste la
        // chaîne leave_balance_logs (comptage réel).
        if ($type->deducts_leave) {
            $this->syncLeaveBalanceSnapshot($absence, 'pending_add');
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

                // #2329 : la demande pending passe en used (pending -= days,
                // used += days).
                $this->syncLeaveBalanceSnapshot($absence, 'pending_remove');
                $this->syncLeaveBalanceSnapshot($absence, 'used_add');
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

                // #2329 : snapshot — used -= days (approbation annulée).
                $this->syncLeaveBalanceSnapshot($absence, 'used_remove');
            }

            // #2329 : demande pending rejetée → pending -= days.
            if ($absence->status === 'pending' && $absence->absenceType?->deducts_leave === true) {
                $this->syncLeaveBalanceSnapshot($absence, 'pending_remove');
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

        // #2329 : snapshot — la demande annulée libère la réservation.
        if ($absence->absenceType?->deducts_leave === true) {
            $this->syncLeaveBalanceSnapshot($absence, 'pending_remove');
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
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * #2329 — synchronise le snapshot `leave_balances` (balance/used/pending)
     * servi par LeavePolicyController. La source de vérité reste la chaîne
     * `leave_balance_logs` (comptage réel) ; ce snapshot est un cache
     * lisible par l'API. Opérations : pending_add / pending_remove /
     * used_add / used_remove.
     */
    private function syncLeaveBalanceSnapshot(Absence $absence, string $operation): void
    {
        $type = $absence->absenceType;
        if ($type === null || ! $type->deducts_leave) {
            return;
        }

        $year = (int) Carbon::parse($absence->start_date)->format('Y');

        $balance = LeaveBalance::firstOrCreate(
            [
                'company_id' => $absence->company_id,
                'employee_id' => $absence->employee_id,
                'absence_type_id' => $type->id,
                'year' => $year,
            ],
            ['balance' => 0, 'used' => 0, 'pending' => 0]
        );

        $days = (float) $absence->days_count;

        match ($operation) {
            'pending_add' => $balance->increment('pending', $days),
            'pending_remove' => $balance->decrement('pending', $days),
            'used_add' => $balance->increment('used', $days),
            'used_remove' => $balance->decrement('used', $days),
            default => null,
        };
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
}
