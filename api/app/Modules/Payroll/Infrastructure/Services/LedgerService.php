<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * PA2-PAY-007 — Ledger financier employee.
 *
 * Writes immutable journal entries for every advance, payment, or manual
 * adjustment affecting an employee's balance, and exposes read access to
 * the resulting auditable history.
 */
class LedgerService
{
    /**
     * Record a new ledger entry for an employee and return it with the
     * freshly computed running balance.
     *
     * @param  float  $amount  Signed amount: negative debits the employee
     *                         (e.g. an advance granted), positive credits
     *                         the employee (e.g. a payment received).
     * @param  Model|null  $source  The domain record this entry originates
     *                              from (SalaryAdvance, PaySlip, PaymentItem…).
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Employee $employee,
        string $entryType,
        float $amount,
        ?string $description = null,
        ?Model $source = null,
        ?int $paymentDocumentId = null,
        ?int $createdBy = null,
        array $metadata = [],
        ?string $currency = null,
    ): LedgerEntry {
        return DB::transaction(function () use (
            $employee,
            $entryType,
            $amount,
            $description,
            $source,
            $paymentDocumentId,
            $createdBy,
            $metadata,
            $currency,
        ): LedgerEntry {
            $currentBalance = $this->currentBalance($employee);
            $balanceAfter = round($currentBalance + $amount, 2);

            return LedgerEntry::query()->create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'entry_type' => $entryType,
                'amount' => round($amount, 2),
                'currency' => $currency ?? $employee->company?->currency ?? 'DZD',
                'balance_after' => $balanceAfter,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'payment_document_id' => $paymentDocumentId,
                'created_by' => $createdBy,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);
        });
    }

    /**
     * The employee's current ledger balance (sum of all signed entries).
     * Returns 0.0 when the employee has no ledger history yet.
     */
    public function currentBalance(Employee $employee): float
    {
        $last = LedgerEntry::query()
            ->forEmployee($employee->id)
            ->where('company_id', $employee->company_id)
            ->newestFirst()
            ->first();

        return $last !== null ? (float) $last->balance_after : 0.0;
    }

    /**
     * Paginated, newest-first ledger history for one employee.
     *
     * @return LengthAwarePaginator<int, LedgerEntry>
     */
    public function history(Employee $employee, int $perPage = 20, ?string $entryType = null): LengthAwarePaginator
    {
        $query = LedgerEntry::query()
            ->forEmployee($employee->id)
            ->where('company_id', $employee->company_id)
            ->newestFirst();

        if ($entryType !== null) {
            $query->ofType($entryType);
        }

        return $query->paginate($perPage);
    }
}
