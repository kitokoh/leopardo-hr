<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Events\AbsenceRequested;
use App\Exceptions\AbsenceDateConflictException;
use App\Exceptions\AbsenceNotPendingException;
use App\Exceptions\InsufficientLeaveBalanceException;
use App\Modules\Payroll\Infrastructure\Services\PublicHolidayService;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeaveBalanceLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AbsenceService
{
    public function __construct(
        private readonly PublicHolidayService $publicHolidays,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Employee $employee, array $data, ?UploadedFile $proof = null): Absence
    {
        $type = AbsenceType::query()->where('id', $data['absence_type_id'])->firstOrFail();

        if ($type->company_id !== $employee->company_id) {
            abort(404);
        }

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        // Issue #2671 (T010) : days_count = JOURS OUVRÉS (week-ends et fériés
        // du pays de l'entreprise exclus) au lieu des jours calendaires — un
        // congé vendredi→lundi consommait 4 jours. Convention documentée :
        // la déduction de solde et l'indemnité portent sur les jours ouvrés
        // (calendrier entreprise via PublicHolidayService, fallback week-ends
        // seuls quand le pays est inconnu ou qu'aucun férié n'est configuré).
        $countryCode = $employee->company->country ?? null;
        $daysCount = $this->publicHolidays->workingDaysBetween(
            $startDate,
            $endDate,
            (string) ($countryCode ?? ''),
            null,
            $employee->company_id !== null ? (string) $employee->company_id : null,
        );

        // Issue #2676 (QA 2026-08-15) — la garde de solde était en
        // check-then-insert sans verrou : deux demandes simultanées pouvaient
        // toutes deux passer la garde et sur-réserver le solde. La vérification
        // se fait désormais dans une transaction avec verrouillage de la ligne
        // snapshot (même pattern que approve(), #2666).
        return DB::transaction(function () use ($employee, $data, $proof, $type, $startDate, $daysCount): Absence {
            if ($type->deducts_leave) {
                $year = (int) $startDate->format('Y');
                $typeId = (int) $data['absence_type_id'];

                $snapshot = LeaveBalance::query()
                    ->where('company_id', $employee->company_id)
                    ->where('employee_id', $employee->id)
                    ->where('absence_type_id', $typeId)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                $balance = $snapshot !== null
                    ? (float) $snapshot->balance - (float) $snapshot->used - (float) $snapshot->pending
                    : $this->currentAvailableBalance($employee, $typeId, $year);

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
                'absence_type_id' => (int) $data['absence_type_id'],
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
        });
    }

    /**
     * #4933 — modification d'une demande de congé en attente (dates et/ou
     * raison). Le nombre de jours est recalculé sur les jours ouvrés du pays
     * (même règle que create(), #2671). La revalidation du solde en cas
     * d'extension est laissée à approve() (verrou ligne, #2666) — la demande
     * reste 'pending' tant qu'elle n'est pas approuvée.
     */
    /** @param array<string, mixed> $data */
    public function update(Absence $absence, array $data): Absence
    {
        $startDate = Carbon::parse($data['start_date'] ?? $absence->start_date->toDateString());
        $endDate = Carbon::parse($data['end_date'] ?? $absence->end_date?->toDateString() ?? now()->toDateString());

        $daysCount = $absence->days_count;
        if (isset($data['start_date']) || isset($data['end_date'])) {
            $employee = $absence->employee()->firstOrFail();
            $company = $employee->company()->first();
            $countryCode = $company?->country;
            $daysCount = $this->publicHolidays->workingDaysBetween(
                $startDate,
                $endDate,
                (string) ($countryCode ?? ''),
                null,
                $absence->company_id !== null ? (string) $absence->company_id : null,
            );
        }

        $absence->update([
            'start_date' => $data['start_date'] ?? $absence->start_date,
            'end_date' => $data['end_date'] ?? $absence->end_date,
            'reason' => array_key_exists('reason', $data) ? $data['reason'] : $absence->reason,
            'days_count' => $daysCount,
        ]);

        $absence->refresh();

        return $absence;
    }

    public function approve(Absence $absence, Employee $approver): Absence
    {
        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException;
        }

        DB::transaction(function () use ($absence, $approver) {
            $type = $absence->absenceType;

            if ($type !== null && $type->deducts_leave) {
                // Issue #2666 (QA 2026-08-15) — le snapshot `leave_balances` est
                // la source de vérité du solde : les chemins de crédit
                // (LeavePolicyController::credit, accruals, carry-forward)
                // n'écrivent PAS de log, donc la chaîne `leave_balance_logs`
                // est vide après un crédit et la première approbation échouait
                // à tort (INSUFFICIENT_LEAVE_BALANCE). On vérifie et déduit sur
                // le snapshot (balance − used − pending, même formule que
                // currentAvailableBalance), ligne verrouillée pour éviter les
                // courses ; le log reste une piste d'audit.
                $year = (int) Carbon::parse($absence->start_date)->format('Y');
                $days = (float) $absence->days_count;
                $typeId = (int) $absence->absence_type_id;

                $snapshot = LeaveBalance::query()
                    ->where('company_id', $absence->company_id)
                    ->where('employee_id', $absence->employee_id)
                    ->where('absence_type_id', $typeId)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if ($snapshot === null) {
                    // Données héritées sans snapshot : le solde est reconstruit
                    // depuis la chaîne de logs (comportement historique, sans
                    // réservation pending — les absences héritées créées hors
                    // service n'ont pas de pending_add) et le snapshot est
                    // initialisé sur cette valeur pour rester cohérent ensuite.
                    $lastLog = LeaveBalanceLog::query()
                        ->where('employee_id', $absence->employee_id)
                        ->where('company_id', $absence->company_id)
                        ->orderByDesc('id')
                        ->first();
                    $legacyBalance = $lastLog ? (float) $lastLog->balance_after : 0.0;

                    $snapshot = LeaveBalance::query()->create([
                        'company_id' => $absence->company_id,
                        'employee_id' => $absence->employee_id,
                        'absence_type_id' => $typeId,
                        'year' => $year,
                        'balance' => max(0.0, $legacyBalance),
                        'used' => 0,
                        'pending' => 0,
                    ]);
                }

                $available = (float) $snapshot->balance - (float) $snapshot->used - (float) $snapshot->pending;

                if ($available < $days) {
                    throw new InsufficientLeaveBalanceException(max(0.0, $available), $days);
                }

                $snapshot->update([
                    'pending' => max(0, (float) $snapshot->pending - $days),
                    'used' => (float) $snapshot->used + $days,
                ]);

                $newBalance = max(0.0, (float) $snapshot->balance - (float) $snapshot->used);

                $this->logBalanceChange(
                    (int) $absence->employee_id,
                    $absence->company_id,
                    -$days,
                    'absence_approved',
                    $absence->id,
                    $newBalance
                );
            }

            $absence->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
            ]);
        });

        $absence = $absence->fresh()
            ?? throw new \RuntimeException('Absence introuvable après approbation.');

        AbsenceApproved::dispatch($absence, $approver);

        // #5439 — journal d'audit global : approbation d'une absence (planning).
        AuditLog::record(
            'planning',
            'planning.absence.approve',
            $absence,
            $approver,
            ['status' => 'pending'],
            ['status' => $absence->status, 'approved_by' => $approver->id],
        );

        return $absence;
    }

    public function reject(Absence $absence, string $reason): Absence
    {
        if (! in_array($absence->status, ['pending', 'approved'])) {
            throw new AbsenceNotPendingException;
        }

        DB::transaction(function () use ($absence, $reason) {
            // If already approved and balance was deducted, restore it
            if ($absence->status === 'approved' && $absence->absenceType?->deducts_leave) {
                // Issue #2666 — même correction que approve() : le solde vit
                // dans le snapshot leave_balances (source de vérité), pas dans
                // la chaîne de logs. On restaure used -= days depuis le
                // snapshot (verrouillé) ; le log reste une piste d'audit.
                $type = $absence->absenceType;
                $year = (int) Carbon::parse($absence->start_date)->format('Y');
                $days = (float) $absence->days_count;

                $snapshot = LeaveBalance::query()
                    ->where('company_id', $absence->company_id)
                    ->where('employee_id', $absence->employee_id)
                    ->where('absence_type_id', (int) $absence->absence_type_id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if ($snapshot !== null) {
                    $usedAfter = max(0.0, (float) $snapshot->used - $days);
                    $newBalance = max(0.0, (float) $snapshot->balance - $usedAfter);
                } else {
                    // Données héritées sans snapshot : comportement historique
                    // (chaîne de logs).
                    $lastLog = LeaveBalanceLog::where('employee_id', $absence->employee_id)
                        ->orderByDesc('id')
                        ->first();
                    $newBalance = ($lastLog ? (float) $lastLog->balance_after : 0.0) + $days;
                }

                $this->logBalanceChange(
                    (int) $absence->employee_id,
                    $absence->company_id,
                    $days,
                    'absence_rejected',
                    $absence->id,
                    $newBalance
                );
            }

            // Issue #2329: keep the leave_balances snapshot in sync — a rejected
            // pending absence releases its pending days; a rejected approved
            // absence restores the used days.
            if ($absence->absenceType?->deducts_leave) {
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

<<<<<<< HEAD
        $absence = $absence->fresh()
            ?? throw new \RuntimeException('Absence introuvable après rejet.');

        AbsenceRejected::dispatch($absence);

        // #5439 — journal d'audit global : rejet d'une absence (planning).
        AuditLog::record(
            'planning',
            'planning.absence.reject',
            $absence,
            null,
            ['status' => 'pending'],
            ['status' => $absence->status, 'rejected_reason' => $absence->rejected_reason],
        );

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

        // #5439 — journal d'audit global : annulation d'une absence (planning).
        AuditLog::record(
            'planning',
            'planning.absence.cancel',
            $absence,
            null,
            ['status' => 'pending'],
            ['status' => 'cancelled'],
        );

        return $absence->fresh()
            ?? throw new \RuntimeException('Absence introuvable après annulation.');
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
