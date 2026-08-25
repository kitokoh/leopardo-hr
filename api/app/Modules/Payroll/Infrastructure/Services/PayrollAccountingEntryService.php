<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Exceptions\UnbalancedPayrollEntriesException;
use App\Modules\Payroll\Domain\Models\PayrollAccountingEntry;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Exports\PayrollAccountingExportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Issue #5239 — Phase C : écritures salariales automatiques.
 *
 * Persiste les lignes d'écriture produites par
 * `PayrollAccountingExportService::journalLines()` (socle #5256) à la
 * validation d'un `PayrollRun`. Le module Payroll reste maître du calcul ;
 * le module Accounting consomme ces lignes.
 *
 * - Équilibre débit = crédit garanti (exception sinon) ;
 * - régénération IDEMPOTENTE (remplacement des lignes du run, contrainte
 *   d'unicité (run, bulletin, compte)) ;
 * - traçabilité : chaque ligne porte `reference = PAYROLL-RUN-{id}` et le
 *   `payroll_run_id` ; isolation tenant par `company_id`.
 */
class PayrollAccountingEntryService
{
    public function __construct(
        private readonly PayrollAccountingExportService $exportService,
    ) {}

    /**
     * Génère (ou régénère) les écritures salariales d'un run validé.
     *
     * @return int nombre de lignes persistées
     *
     * @throws UnbalancedPayrollEntriesException si débit ≠ crédit
     */
    public function generateForRun(PayrollRun $run, ?Employee $actor = null): int
    {
        if ($run->status !== PayrollRun::STATUS_VALIDATED && $run->status !== PayrollRun::STATUS_LOCKED) {
            throw new \RuntimeException(
                'Un run doit être validé avant génération des écritures comptables (statut actuel : '.$run->status.').'
            );
        }

        $lines = $this->exportService->journalLines($run);
        if ($lines === []) {
            // Pays sans plan comptable (ou run sans bulletins validés) : rien à persister.
            return 0;
        }

        $balance = $this->balanceOf($lines);
        if (abs($balance) > 0.004) {
            throw new UnbalancedPayrollEntriesException(
                "Journal déséquilibré pour le run {$run->id} : débit − crédit = {$balance}"
            );
        }

        return DB::transaction(function () use ($run, $lines, $actor): int {
            // Idempotence : on remplace les lignes existantes du run.
            PayrollAccountingEntry::query()
                ->where('payroll_run_id', $run->id)
                ->delete();

            $now = now();
            $rows = [];
            foreach ($lines as $line) {
                $rows[] = [
                    'company_id' => $run->company_id,
                    'payroll_run_id' => $run->id,
                    'pay_slip_id' => $line['pay_slip_id'] ?? null,
                    'employee_id' => $line['employee_id'] ?? null,
                    'date' => $line['date'] ?? $run->period_end->toDateString(),
                    'account_code' => $line['account_code'],
                    'account_label' => $line['account_label'] ?? '',
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'reference' => $line['reference'] ?? sprintf('PAYROLL-RUN-%d', $run->id),
                    'created_by' => $actor?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            PayrollAccountingEntry::query()->insert($rows);

            Log::info('payroll.accounting_entries.generated', [
                'payroll_run_id' => $run->id,
                'company_id' => $run->company_id,
                'lines' => count($rows),
                'by' => $actor?->id,
            ]);

            return count($rows);
        });
    }

    /** @return Collection<int, PayrollAccountingEntry> */
    public function entriesForRun(PayrollRun $run): Collection
    {
        return PayrollAccountingEntry::query()
            ->where('company_id', $run->company_id)
            ->where('payroll_run_id', $run->id)
            ->orderBy('pay_slip_id')
            ->orderBy('account_code')
            ->get();
    }

    /** Écart débit − crédit du run (0.0 = équilibré). */
    public function balanceForRun(PayrollRun $run): float
    {
        return (float) PayrollAccountingEntry::query()
            ->where('payroll_run_id', $run->id)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS balance')
            ->value('balance');
    }

    /**
     * Écart débit − crédit d'un tableau de lignes `journalLines()`.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function balanceOf(array $lines): float
    {
        $debits = array_sum(array_map(fn (array $l): float => (float) ($l['debit'] ?? 0), $lines));
        $credits = array_sum(array_map(fn (array $l): float => (float) ($l['credit'] ?? 0), $lines));

        return round($debits - $credits, 2);
    }
}
